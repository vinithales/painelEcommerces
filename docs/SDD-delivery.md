# Entrega SDD — BringPainel

Branch: `feat/bringpainel-sdd`. Base: `a6778ba`. O documento fornecido foi preservado e a descoberta/especificação precedeu a implementação. Alterações compatíveis de tradução e estilo surgidas durante o trabalho foram incorporadas com autorização do usuário.

## Macro etapas

| Etapa | Implementação e arquivos principais |
|---|---|
| Acesso e interface | Login, gates N1/N2/admin, provisionamento de operador, tema persistido, sidebar contextual, hub. `AuthServiceProvider`, `Login`, `CreateOperator`, `resources/views/layouts/app.blade.php`, CSS/JS e views de lojas. Credenciais não são preenchidas no frontend. |
| Produtos e pedidos | `ResourceTable`, `StoreProducts`, `StoreOrders`, `operations-grid.js`, adapters, `ProductFields`, `ResourceDetails`, Actions e jobs. Paginação remota, filtros, drawer, payload parcial, preview, snapshot, rollback e lote preservados. Operações Magento específicas e confirmação por releitura. |
| Infraestrutura | `ServerConnectionInterface`, factory, `AgentServerConnection`, `SupportOperations`, `SupportOperationJob`, `agent/bring-agent.php` e `LogReader`. Transporte HTTPS, logs em trechos, cron somente leitura, ferramentas e diagnóstico assíncronos. |
| Checks | `CheckConfiguration`, strategies, registry, dispatcher, runner, jobs, modelos e wizard. Nove tipos, quatro níveis, agendamento, claims, histórico, alertas por e-mail e recuperação. Integração com resultados legados. |
| Operação e entrega | `StoreOverview`, hub, telas de diagnóstico/ferramentas, scheduler no Compose, README e instruções do agente. Testes de acesso, fluxos comerciais, infraestrutura e checks. |

## Migrations

- `2026_09_17_000001_create_operations_tables.php`: role de usuário, conexões, auditoria, checks, execuções e alertas.
- `2026_09_17_000002_add_check_claim_token.php`: proteção contra jobs com lease antigo.
- Duas migrations legadas de renomeação receberam compatibilidade com SQLite, mantendo o caminho MySQL de produção.

## Decisões

Foram preservados Laravel/Livewire/AG Grid, Actions, adapters, templates, snapshots e filas. Diferenças comerciais ficam nos adapters/services. Infraestrutura usa agente somente leitura, sem terminal nem execução arbitrária. Checks e ferramentas executam fora da requisição web. O cliente HTTP de checks limita destino, redirects, TLS, tempo e bytes. Locks, claims e tokens evitam execução duplicada; jobs revalidam permissões. Auditoria e dados exibidos passam por redação de segredos.

Magento usa SKU na API de produto. Pedidos usam cancel/hold/unhold, sem alteração direta de state. Preço editável WooCommerce representa preço regular. Rollback exige snapshot confirmado e rejeita alteração posterior nos campos afetados.

Referências técnicas: [operações de pedidos Magento](https://github.com/magento/magento2/blob/2.4-develop/app/code/Magento/Sales/etc/webapi.xml), [API WooCommerce](https://woocommerce.github.io/woocommerce-rest-api-docs/).

## Validação final

- PHPUnit 10.5.63 / PHP 8.4.25: **71 testes, 192 asserções, todos aprovados**, com SQLite em memória e APIs simuladas.
- Vite: build de produção aprovado. Aviso de tamanho do bundle compartilhado AG Grid (aproximadamente 1,1 MB antes de gzip).
- Blade: views compiladas com sucesso.
- Pint: arquivos PHP alterados formatados.
- Revisão visual da tela de login em Chrome headless. Não houve teste visual completo de todos os fluxos autenticados; navegação/tema possuem implementação e testes de renderização, mas a validação interativa em lojas reais permanece para homologação.

## Incidente local e correção

A primeira execução dos testes de banco utilizou indevidamente o MySQL local: variáveis exportadas pelo Docker prevaleceram sobre as entradas env do PHPUnit e RefreshDatabase recriou as tabelas. O incidente foi informado ao usuário. Binlogs foram preservados e reconstruídos em banco separado até antes da primeira remoção. Existia um usuário e nenhuma loja; o usuário foi restaurado com senha e perfil originais. Cópias locais de recuperação permanecem fora do Git e do contexto de build.

Correção: PHPUnit força também variáveis server; `tests/CreatesApplication.php` fixa a configuração SQLite `:memory:` e verifica o driver/banco antes de RefreshDatabase. Um teste específico cobre o isolamento. A execução final acima ocorreu após essa correção.

## Limitações e configuração externa

- Agente deve ser implantado e configurado no servidor Magento; SMTP/destinatários precisam ser definidos para alertas externos. Nenhum servidor de loja foi alterado.
- WooCommerce: prefixo de SKU refina a página corrente; contagem permanece remota. E-mail usa busca da API e número de pedido usa ID interno.
- Campos não retornados pela plataforma ficam explicitamente indisponíveis; estoque Magento é somente leitura. Invoice/shipment/refund não são simulados.
- O agente fornece conectividade, disco, manutenção, logs e cron. APIs de provedores, Redis, inspeção de processos/serviços, indexadores, canais Slack/Teams e agrupamento são extensões futuras.
- URLs privadas não são aceitas pelo transporte público atual. Alertas sem destinatário ficam registrados como erro de configuração.
- Não foram executadas escritas em lojas reais. Homologar com uma loja de teste e credenciais de escopo mínimo antes do uso operacional.

Orientações de instalação e operação estão no [README](../README.md) e em [agent-setup.md](agent-setup.md).
