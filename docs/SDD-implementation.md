# BeaconHub — especificação de evolução

## Discovery (17/09/2026)

Base: Laravel 10, Livewire 3, AG Grid 35, Vite 4. Sem AGENTS.md no repositório. A árvore inicial está limpa, exceto o documento de requisitos fornecido. Branch: feat/bringpainel-sdd.

Reutilizar StoreForm, Actions, adapters, logs, snapshots, templates e jobs. As migrations existentes criam lojas/integrações, jobs, resultados legados de checks, logs, snapshots e templates. O README descreve paginação remota e gravação em fila, mas produtos carregam o catálogo completo e edições individuais são síncronas. Não existem autenticação nas rotas, autorização, drawers, infraestrutura ou checks configuráveis. TLS está desativado nos adapters. Credenciais são preenchidas no estado público do formulário de edição. O rollback envia o objeto normalizado inteiro.

## Especificação e design por macro etapa

### A. Acesso e interface
Objetivo: backoffice autenticado, identidade esmeralda/grafite, navegação Livewire. Entrada: sessão e preferência de tema. Saída: hub contextual, sidebar colapsável, tema persistido com fallback do sistema. N1 consulta e executa diagnósticos; N2/admin edita produtos/pedidos e checks; admin configura lojas/servidores. Gates no backend, inclusive métodos Livewire. Login limitado por tentativas e provisionamento de usuário por comando interativo, sem senha padrão. Secrets nunca retornam do formulário; campos vazios preservam credenciais. Auditoria redigida. Testes: visitante, N1, administrador, serialização e redação.

### B. Produtos e pedidos
Objetivo: paginação remota, filtros em URL, drawers sem perder listagem. Entradas: filtros validados, identificador e mudanças permitidas. Saídas: produto/pedido normalizado, diff e resultado verificado. Magento usa SKU no endpoint de produto, ID no pedido; operações cancel/hold/unhold em endpoints específicos. Não simular invoice/shipment/refund. WooCommerce usa campos graváveis traduzidos (price → regular_price). Rejeitar campos não suportados. Snapshot armazena somente campos alterados, rollback auditado. Erros preservam alterações pendentes. Testes com Guzzle MockHandler para normalização, payload parcial, transições, preview, rollback e lote.

### C. Infraestrutura e ferramentas
Objetivo: operações de leitura predefinidas. Contrato ServerConnectionInterface e factory com transporte de agente HTTPS autenticado. Agente PHP incluído para implantação junto ao Magento, com allowlist de logs e consultas cron somente leitura; nenhum shell. Entrada: conexão administrativa e operação enumerada. Saída: trecho limitado/cursor, métricas cron e diagnóstico. Limites de bytes, caminhos fixos, redação, auditoria, thresholds configuráveis. Transporte deve falhar explicitamente se não configurado. Testes: traversal, whitelist, redação, cron parado/backlog/travado. APIs de provedores/SSH ficam como extensões futuras, sem endpoints fictícios.

### D. Checks, agendamento e alertas
Objetivo: um único runner para checks legados e personalizados. Novas tabelas store_checks, store_check_runs, store_check_alerts, server_connections e audit_events; role em users. Contrato CheckStrategy, CheckResult e registry. Tipos HTTP_STATUS, CONTENT, META_ROBOTS, ROBOTS_TXT, SITEMAP_XML, MAINTENANCE, MAGENTO_CRON, API_HEALTH e RESPONSE_TIME. Categorias SERVER/APP/API/SITE; configuração validada por tipo e sem chaves arbitrárias. HTTP restrito a destinos públicos, TLS e redirects validados, respostas limitadas. UNKNOWN representa falha de execução, WARNING/CRITICAL falha detectada no alvo.

Scheduler central por minuto despacha vencidos com claim atômico e lease; job possui lock por check, timeout e isolamento. UI somente enfileira, inclusive teste do wizard (resultado temporário em cache, sem alertas). Execuções registram tempo, métricas e modo manual. Falhas consecutivas, aumento de severidade, cooldown e recuperação geram registros e e-mail via mailer configurado, destinatários explícitos de configuração. Compatibilidade: importação idempotente dos checks antigos e atualização da tabela legada pelo novo runner. Testes: todos os tipos, timeouts, erro interno, vencimento, desabilitado, threshold, cooldown, recovery e autorização.

### E. Interface operacional e entrega
Wizard em cinco passos, edição, teste assíncrono, detalhe/histórico, executar agora e habilitar. Hub mostra saúde/alertas/gateways; dashboard por loja agrega níveis, alertas e histórico. Ferramentas mostram diagnóstico real, status HTTP, duração e resposta redigida. AG Grid é destruído na navegação e listeners são removidos. Testes sem lojas reais; build Vite, compilação Blade, migrations em banco de testes isolado e revisão visual quando disponível. README final deve refletir comportamento implementado e implantação do agente, scheduler, worker e e-mail.

## Checkpoints

1. Documentação de discovery/spec/design.
2. Acesso, segurança e layout.
3. Produtos/pedidos e drawers.
4. Infraestrutura, checks, jobs e alertas.
5. Interface operacional, testes e documentação final.

Cada checkpoint registra arquivos, migrations, validação e limitações em commits e no relatório de entrega. Nenhuma referência visual foi anexada além da descrição textual; a direção visual seguirá essa descrição.
