# BringPainel — Documento de Orientações para Codex (SDD)

## 1. Objetivo deste documento

Este documento define as orientações funcionais, arquiteturais, visuais e de qualidade para a evolução do **BringPainel** usando uma abordagem de **Specification-Driven Development (SDD)**.

O Codex deve utilizar este arquivo como fonte principal de requisitos durante a refatoração e expansão do sistema.

A implementação deve partir do código existente, preservando recursos que já funcionam, reduzindo regressões e evitando reescritas desnecessárias.

O objetivo é transformar o BringPainel em uma ferramenta interna de operação, suporte e diagnóstico para lojas **Magento 2** e **WooCommerce**, incluindo recursos de gerenciamento de produtos e pedidos, diagnóstico de aplicação, monitoramento de servidor, verificações periódicas e alertas operacionais.

---

# 2. Contexto atual do sistema

O BringPainel é um backoffice em Laravel 10 usado para gerenciar lojas conectadas por WooCommerce e Magento 2.

Stack atual:

- PHP 8.1+
- Laravel 10
- Livewire 3
- AG Grid Community
- Vite 4
- PHPUnit 10

Principais recursos já existentes:

- cadastro e edição de lojas;
- credenciais específicas para WooCommerce e Magento 2;
- listagem e atualização de produtos;
- listagem e atualização de pedidos;
- logs de operações de API;
- snapshots e rollback de produtos;
- templates de edição em massa;
- health checks de integração;
- jobs em fila;
- adapters separados por plataforma.

Arquitetura conceitual atual:

```text
Livewire screen
  └─ Form object
       └─ Action
            └─ Service / Adapter
                 └─ Model / Integration relation
```

Adapters existentes:

```text
App\Services\PlatformApi\Adapters\WooCommerceApiAdapter
App\Services\PlatformApi\Adapters\Magento2ApiAdapter
App\Services\PlatformApi\PlatformApiAdapterFactory
```

Serviços existentes:

```text
App\Services\PlatformApi\ProductsApiService
App\Services\PlatformApi\OrdersApiService
```

Health checks existentes:

```text
App\Services\IntegrationCheckers\RunStoreChecks
App\Jobs\CheckStoreIntegrationJob
```

O Codex deve primeiro inspecionar o repositório real antes de alterar qualquer parte do projeto.

---

# 3. Princípios de implementação

## 3.1 Não reescrever sem necessidade

Não recriar o sistema do zero.

Não trocar Laravel, Livewire, AG Grid ou a arquitetura atual apenas por preferência tecnológica.

Antes de criar novas classes, verificar se já existe abstração semelhante.

## 3.2 Preservar funcionalidades existentes

Não remover ou simplificar recursos funcionais sem justificativa técnica.

Preservar especialmente:

- logs;
- filas;
- snapshots;
- rollback;
- edição em massa;
- templates;
- health checks;
- adapters por plataforma.

## 3.3 Separação entre domínio e plataforma

A interface não deve conhecer detalhes de endpoints HTTP de Magento ou WooCommerce.

Diferenças entre plataformas devem permanecer em adapters, serviços especializados, capabilities ou estratégias equivalentes.

## 3.4 Segurança operacional

O sistema será utilizado em lojas reais.

Nenhuma ação potencialmente destrutiva deve ser criada como atalho inseguro.

Operações de leitura devem ter prioridade.

Operações corretivas devem exigir permissões, confirmação e auditoria.

---

# 4. Direção visual e UX

## 4.1 Referência visual

A nova interface deve se inspirar na estrutura da interface do **Cloudways**, conforme as imagens fornecidas como referência.

A inspiração deve considerar:

- navegação lateral compacta;
- barra superior discreta;
- hierarquia visual clara;
- grande área de conteúdo;
- cards leves;
- menus contextuais;
- foco em operações;
- interface limpa para uso diário;
- baixa densidade de elementos decorativos.

Não copiar a identidade visual do Cloudways literalmente.

Não usar azul como cor primária nem secundária.

## 4.2 Paleta

A aplicação deve possuir identidade própria.

Usar uma paleta neutra com uma cor de destaque que não seja azul.

Exemplos aceitáveis:

- verde escuro;
- esmeralda;
- âmbar;
- violeta;
- grafite com destaque quente.

Não utilizar azul como cor principal, secundária ou destaque recorrente.

Azul pode aparecer apenas quando semanticamente necessário e sem se tornar parte da identidade dominante.

## 4.3 Layout geral

Estrutura recomendada:

```text
┌──────────────────────────────────────────────────────────────┐
│ Topbar                                                       │
├───────────────┬──────────────────────────────────────────────┤
│ Sidebar       │ Conteúdo principal                           │
│               │                                              │
│ Lojas         │ Breadcrumb / título                          │
│ Produtos      │                                              │
│ Pedidos       │ Cards / tabelas / painéis                    │
│ Checks        │                                              │
│ Logs          │                                              │
│ Ferramentas   │                                              │
│ Configuração  │                                              │
└───────────────┴──────────────────────────────────────────────┘
```

## 4.4 Sidebar

A sidebar deve ser simples, colapsável e conter apenas módulos principais.

Sugestão:

- Visão geral
- Lojas
- Produtos
- Pedidos
- Verificações
- Logs
- Ferramentas
- Configurações

Dentro de uma loja específica, exibir navegação contextual da loja.

## 4.5 Topbar

A topbar pode conter:

- breadcrumb;
- seletor da loja ativa quando aplicável;
- busca global opcional;
- botão de tema;
- usuário atual;
- indicadores discretos de alertas.

## 4.6 Dark mode e light mode

Implementar:

- modo claro;
- modo escuro;
- botão de alternância;
- persistência da preferência;
- uso da preferência do sistema operacional quando ainda não houver escolha;
- suporte consistente no AG Grid, formulários, cards, modais e drawers.

## 4.7 Ícones

Não usar emojis.

Utilizar uma biblioteca de ícones SVG consistente, preferencialmente Lucide ou equivalente.

Não misturar diferentes bibliotecas sem necessidade.

## 4.8 SPA

Manter e melhorar o comportamento SPA utilizando Livewire 3.

Garantir:

- navegação sem reload completo;
- funcionamento de voltar/avançar do navegador;
- estados de loading;
- filtros preservados em URL quando útil;
- AG Grid corretamente desmontado e reinstanciado;
- ausência de listeners duplicados.

---

# 5. Gestão de lojas

A listagem de lojas deve se tornar o hub principal de entrada.

Cada loja deve exibir de forma resumida:

- nome;
- plataforma;
- domínio;
- status da API;
- status do servidor, quando configurado;
- quantidade de alertas ativos;
- último check;
- gateways principais.

A tela não deve ser visualmente carregada.

Ações rápidas por loja:

- Produtos
- Pedidos
- Verificações
- Logs
- Diagnóstico
- Configurações

---

# 6. Produtos

## 6.1 Listagem

Refatorar `/stores/{store}/products`.

Campos obrigatórios:

- imagem;
- nome;
- SKU;
- URL;
- preço;
- peso.

Requisitos:

- busca por nome;
- busca por SKU;
- filtro por SKU exato;
- filtro por prefixo;
- filtro por status;
- filtro por período;
- paginação;
- page size configurável;
- seleção múltipla;
- ordenação quando suportada;
- loading states;
- empty states;
- tratamento de erro.

Sempre que possível, usar paginação e filtros no endpoint remoto.

Não carregar catálogos completos no frontend.

## 6.2 Drawer de produto

Ao clicar em uma linha, abrir um drawer/painel lateral sem abandonar a listagem.

O estado atual da listagem deve ser mantido.

Seções:

### Geral

- ID
- nome
- SKU
- tipo
- status
- visibilidade
- descrição
- descrição curta
- URL

### Preço

- preço regular
- preço promocional
- preço atual
- moeda

### Estoque

- quantidade
- status
- gerenciamento de estoque

### Físico

- peso
- dimensões

### Organização

- categorias
- atributos
- variações

### Mídia

- imagem principal
- galeria

### Técnico

- payload original
- última resposta
- histórico de atualizações

## 6.3 Edição

Permitir editar apenas campos suportados pela plataforma.

Enviar apenas os campos alterados.

Não enviar o produto completo sem necessidade.

Implementar:

- dirty state;
- validação;
- salvar;
- descartar;
- confirmação ao perder alterações;
- feedback de sucesso/erro;
- refresh do produto após atualização.

## 6.4 Dry-run

Preservar e melhorar preview:

| Campo | Valor atual | Novo valor |
|---|---|---|

O preview nunca pode chamar endpoint de escrita.

## 6.5 Ações do produto

Criar menu `Ações` com operações reais, como:

- atualizar dados via API;
- abrir produto na loja;
- copiar SKU;
- copiar ID;
- visualizar raw payload;
- visualizar última atualização;
- consultar histórico;
- rollback quando existir snapshot válido.

Não criar ações visuais sem implementação real.

---

# 7. Pedidos

## 7.1 Listagem

Refatorar `/stores/{store}/orders`.

Campos principais:

- número do pedido;
- data;
- cliente;
- e-mail;
- status;
- total;
- método de pagamento.

Filtros:

- número/ID;
- status;
- e-mail;
- período;
- paginação.

## 7.2 Drawer de pedido

Ao clicar, abrir painel lateral.

Seções:

### Resumo

- número
- ID
- data
- status
- moeda
- total

### Cliente

- nome
- e-mail
- telefone

### Itens

- produto
- SKU
- quantidade
- preço
- subtotal

### Valores

- subtotal
- desconto
- frete
- impostos
- total

### Pagamento

- método
- status da transação
- IDs relacionados

### Entrega

- método
- endereço
- tracking quando disponível

### Técnico

- payload
- resposta
- histórico

## 7.3 Alteração de status

A alteração deve respeitar a API real da plataforma.

WooCommerce e Magento 2 não devem compartilhar uma implementação simplista de `status`.

Para Magento 2:

- diferenciar `state` e `status`;
- respeitar operações específicas;
- não simular faturamento, envio, cancelamento ou reembolso via troca direta de status.

Após alteração:

1. executar operação correta;
2. buscar novamente o pedido;
3. validar estado final;
4. registrar log.

---

# 8. Ferramentas de suporte via API

Criar uma área de ferramentas por loja.

Objetivo: permitir testes e diagnósticos que normalmente exigiriam conhecimento técnico adicional.

Operações iniciais:

- testar autenticação da API;
- consultar produto por ID;
- consultar produto por SKU;
- consultar pedido por ID;
- consultar informações da loja;
- medir tempo de resposta;
- visualizar status HTTP;
- visualizar body da resposta;
- visualizar erro da plataforma.

Não criar um cliente HTTP genérico irrestrito para N1.

Operações devem ser previamente definidas e autorizadas.

---

# 9. Diagnóstico em nível de servidor

## 9.1 Objetivo

Permitir que suporte N1 consulte informações técnicas atualmente acessíveis apenas por SSH.

Exemplos:

- cron Magento;
- logs do `var/log`;
- estado básico de serviços;
- conectividade;
- fila;
- indexadores futuramente.

## 9.2 Abstração de acesso

Criar uma camada específica para acesso de infraestrutura.

Não acoplar o sistema diretamente a um único provedor.

Possíveis métodos:

- API do provedor;
- agente interno;
- SSH restrito no backend;
- acesso controlado ao banco;
- integração futura com Cloudways ou outros provedores.

Criar algo conceitualmente semelhante a:

```text
ServerConnectionInterface
ServerConnectionFactory
SshServerConnection
ProviderApiServerConnection
AgentServerConnection
```

Os nomes finais devem seguir a arquitetura existente.

## 9.3 Segurança

Nunca expor:

- senha SSH;
- chave privada;
- token do provedor;
- credenciais de banco;
- secrets.

Não fornecer terminal web genérico.

Somente comandos ou operações predefinidas devem ser executáveis.

---

# 10. Verificação do cron Magento 2

Criar uma verificação específica para cron.

Fontes possíveis:

- `cron_schedule`;
- processos do servidor;
- logs;
- comandos autorizados.

Detectar:

- última execução;
- jobs pendentes;
- jobs `missed`;
- jobs `error`;
- jobs presos em `running`;
- backlog anormal;
- cron aparentemente parado.

Nunca considerar cron saudável apenas porque existe um processo PHP.

Os thresholds devem ser configuráveis.

Exemplo de resultado:

```text
Cron Magento
Estado: Atenção
Última execução: 18 minutos atrás
Pendentes: 31
Atrasados: 12
Erro: 2
Possivelmente travados: 1
```

Inicialmente, criar apenas leitura e diagnóstico.

Não adicionar botão de restart de cron nesta etapa.

---

# 11. Logs Magento

Permitir consultar arquivos autorizados, especialmente:

- `var/log/system.log`
- `var/log/exception.log`
- `var/log/debug.log`
- `var/log/cron.log`, quando existir

Recursos:

- selecionar log;
- carregar linhas recentes;
- refresh;
- busca textual;
- filtro por nível;
- visualização de stack traces;
- copiar erro;
- carregar mais;
- download de trecho permitido.

Requisitos técnicos:

- leitura incremental;
- nunca carregar arquivo inteiro;
- limite configurável;
- proteção contra path traversal;
- whitelist de arquivos;
- redaction de secrets;
- auditoria de acesso.

Nunca permitir leitura livre de caminhos informados pelo usuário.

---

# 12. Sistema de verificações periódicas

## 12.1 Objetivo

Adicionar um módulo onde usuários autorizados possam criar verificações que serão executadas periodicamente pelo BringPainel.

Essas verificações devem validar condições operacionais e gerar alertas quando algo sair do estado esperado.

O sistema deve permitir verificações em diferentes níveis.

## 12.2 Níveis de verificação

Toda verificação deve possuir uma categoria/nível:

### SERVER

Relacionado à infraestrutura.

Exemplos:

- cron parou;
- disco acima do limite;
- Redis indisponível;
- MySQL inacessível;
- serviço essencial indisponível;
- fila parada;
- processo travado.

### APP

Relacionado à aplicação Magento/WordPress/WooCommerce.

Exemplos:

- Magento retornando erro;
- maintenance mode habilitado;
- indexadores atrasados;
- tabela cron com backlog;
- exceções recorrentes;
- integração específica inativa.

### API

Relacionado a APIs e integrações.

Exemplos:

- endpoint retorna 401;
- endpoint retorna 500;
- latência acima do limite;
- autenticação expirou;
- payload não possui campo obrigatório;
- API de pagamento indisponível.

### SITE

Relacionado ao site público.

Exemplos:

- homepage retornando 404;
- site retornando 500;
- maintenance page detectada;
- página sem `index,follow` quando deveria possuir;
- página com `noindex` inesperado;
- robots.txt inexistente;
- robots.txt inválido;
- sitemap.xml indisponível;
- sitemap retornando erro;
- domínio indisponível;
- redirect inesperado;
- SSL inválido ou expirando futuramente.

---

# 13. Tipos básicos de checks

Implementar inicialmente verificações simples e altamente reutilizáveis.

## 13.1 HTTP status check

Configuração:

- URL;
- método GET/HEAD;
- status esperado;
- timeout;
- redirects permitidos ou não.

Exemplos:

```text
Esperado: 200
Erro se: 404, 500, timeout
```

## 13.2 Content check

Verificar presença ou ausência de texto/regex.

Exemplos:

- detectar página de manutenção;
- detectar mensagem de erro;
- verificar existência de conteúdo esperado.

## 13.3 Meta robots check

Validar:

- `index`;
- `noindex`;
- `follow`;
- `nofollow`.

Permitir definir condição esperada.

Exemplo:

```text
URL: /
Esperado: index,follow
Erro se: noindex ou ausência inesperada
```

## 13.4 robots.txt check

Verificar:

- existência;
- status HTTP;
- conteúdo mínimo válido;
- bloqueio geral inesperado;
- `Disallow: /` quando não esperado;
- presença de sitemap, se configurado como obrigatório.

## 13.5 sitemap.xml check

Verificar:

- disponibilidade;
- HTTP 200;
- XML bem formado;
- tipo sitemap ou sitemap index;
- quantidade mínima de URLs opcional.

## 13.6 Maintenance check

Detectar manutenção por:

- status;
- título;
- conteúdo;
- headers;
- padrão conhecido da aplicação.

## 13.7 Cron health check

Executar estratégia Magento definida anteriormente.

## 13.8 API auth check

Testar autenticação sem executar escrita.

## 13.9 Response time check

Validar latência.

Exemplo:

```text
Warn: > 2s
Critical: > 5s
```

---

# 14. Modelo de configuração de um check

Cada check deve possuir algo equivalente a:

```text
name
store_id
level
check_type
enabled
interval
configuration
severity
notify_on_failure
notify_on_recovery
consecutive_failures_before_alert
created_by
last_run_at
next_run_at
```

`configuration` pode ser JSON tipado por check, mas deve ser validado no backend.

Exemplo conceitual:

```json
{
  "url": "https://example.com/robots.txt",
  "expected_status": 200,
  "timeout": 10,
  "must_contain": "Sitemap:",
  "must_not_contain": "Disallow: /"
}
```

Não permitir JSON arbitrário sem schema/validação.

---

# 15. Intervalos e agendamento

O usuário deve conseguir definir a frequência.

Exemplos de intervalos pré-definidos:

- 5 minutos;
- 10 minutos;
- 15 minutos;
- 30 minutos;
- 1 hora;
- 3 horas;
- 6 horas;
- 12 horas;
- 24 horas.

Opcionalmente permitir configuração avançada futura.

Não executar checks diretamente durante requests do frontend.

Utilizar scheduler + filas.

Estrutura recomendada:

```text
Scheduler
  └─ busca checks vencidos
       └─ DispatchCheckJob
            └─ CheckRunner
                 └─ Strategy específica
```

Evitar um cron job individual por check.

Criar um scheduler central que despache os checks necessários.

---

# 16. Estado e severidade

Resultados padronizados:

```text
OK
WARNING
CRITICAL
UNKNOWN
```

Cada execução deve registrar:

- início;
- fim;
- duração;
- status;
- resumo;
- detalhes técnicos;
- métricas relevantes;
- erro, se houver.

Separar falha da verificação de falha operacional.

Exemplo:

```text
UNKNOWN = o BringPainel não conseguiu executar o check
CRITICAL = o check foi executado e encontrou condição crítica
```

---

# 17. Alertas

## 17.1 Condições

O sistema deve conseguir alertar quando:

- um check muda de OK para falha;
- falha persiste por N execuções;
- severidade aumenta;
- serviço se recupera.

## 17.2 Anti-ruído

Implementar proteção contra alert storm.

Permitir:

- `consecutive_failures_before_alert`;
- cooldown;
- alertar apenas mudança de estado;
- notificação de recuperação;
- agrupamento futuro.

Exemplo:

```text
Check executa a cada 5 min
Alertar somente após 3 falhas consecutivas
```

## 17.3 Canais

Reutilizar mecanismo de e-mail existente inicialmente.

Preparar arquitetura para canais futuros:

- Slack;
- Teams;
- webhook;
- e-mail;
- notificações internas.

Não implementar todos agora se não forem necessários.

---

# 18. Interface do módulo de verificações

Criar uma área chamada **Verificações**.

## 18.1 Lista

Campos sugeridos:

| Campo | Descrição |
|---|---|
| Nome | nome amigável |
| Loja | loja relacionada |
| Nível | SERVER / APP / API / SITE |
| Tipo | HTTP, robots, sitemap etc. |
| Status | último estado |
| Intervalo | frequência |
| Última execução | timestamp |
| Próxima execução | timestamp |
| Alertas | ativo/inativo |

## 18.2 Criação

Criar wizard simples:

### Passo 1 — Escopo

- loja;
- nível;
- tipo de check.

### Passo 2 — Condições

Campos específicos do tipo selecionado.

### Passo 3 — Frequência

- intervalo;
- timeout;
- quantidade de falhas antes do alerta.

### Passo 4 — Alertas

- enviar alerta de falha;
- enviar recuperação;
- severidade.

### Passo 5 — Testar

Adicionar botão `Testar verificação` antes de salvar.

Exibir o resultado sem criar histórico permanente, ou registrar como execução manual claramente identificada.

## 18.3 Detalhes

Ao abrir um check:

- configuração;
- último estado;
- histórico;
- duração média;
- últimas falhas;
- botão executar agora;
- habilitar/desabilitar;
- editar.

Não transformar isso em uma ferramenta de observabilidade excessivamente complexa.

---

# 19. Exemplos de verificações desejadas

## Loja saiu do índice

```text
Nível: SITE
Tipo: META_ROBOTS
URL: /
Esperado: index,follow
Intervalo: 30 min
Alerta após: 2 falhas
```

## Loja entrou em manutenção

```text
Nível: SITE
Tipo: MAINTENANCE
URL: /
Esperado: site normal
Intervalo: 5 min
```

## Homepage começou a retornar 404

```text
Nível: SITE
Tipo: HTTP_STATUS
URL: /
Esperado: 200
Intervalo: 5 min
```

## robots.txt desapareceu

```text
Nível: SITE
Tipo: ROBOTS_TXT
URL: /robots.txt
Esperado: HTTP 200 + sintaxe válida
Intervalo: 1h
```

## robots bloqueia tudo

```text
Nível: SITE
Tipo: ROBOTS_TXT
Condição proibida: User-agent: * + Disallow: /
```

## sitemap indisponível

```text
Nível: SITE
Tipo: SITEMAP_XML
URL: /sitemap.xml
Esperado: HTTP 200 + XML válido
Intervalo: 1h
```

## Cron Magento parado

```text
Nível: APP
Tipo: MAGENTO_CRON
Esperado: execução dentro do threshold
Intervalo: 10 min
```

## API Magento indisponível

```text
Nível: API
Tipo: API_HEALTH
Esperado: autenticação e resposta válida
Intervalo: 10 min
```

---

# 20. Arquitetura sugerida para checks

Criar contratos simples e extensíveis.

Exemplo conceitual:

```php
interface StoreCheckInterface
{
    public function run(StoreCheck $check): CheckResult;
}
```

Implementações possíveis:

```text
HttpStatusCheck
ContentCheck
MetaRobotsCheck
RobotsTxtCheck
SitemapCheck
MaintenanceCheck
MagentoCronCheck
ApiHealthCheck
ResponseTimeCheck
```

Resultado padronizado:

```text
CheckResult
- status
- summary
- details
- metrics
- started_at
- finished_at
```

Usar factory/registry para resolver o runner pelo `check_type`.

Evitar `switch` gigante dentro do componente Livewire.

---

# 21. Persistência recomendada

Avaliar migrations semelhantes a:

## store_checks

```text
id
store_id
name
level
check_type
enabled
interval_seconds
severity
configuration_json
notify_on_failure
notify_on_recovery
consecutive_failures_before_alert
failure_count
last_status
last_run_at
next_run_at
created_by
created_at
updated_at
```

## store_check_runs

```text
id
store_check_id
status
summary
details_json
metrics_json
started_at
finished_at
duration_ms
manual
error_message
created_at
```

## store_check_alerts

Opcional se o mecanismo existente de alertas não for suficiente.

Registrar:

- check;
- transição de status;
- envio;
- canal;
- data;
- erro.

Não armazenar grandes logs brutos sem necessidade.

---

# 22. Integração com health checks existentes

Não criar dois sistemas paralelos sem necessidade.

Revisar:

```text
App\Services\IntegrationCheckers\RunStoreChecks
App\Jobs\CheckStoreIntegrationJob
store_integration_status
```

Decidir se:

1. o mecanismo atual será evoluído para o novo sistema; ou
2. checks atuais serão adaptados para usar o novo runner.

Priorizar convergência de arquitetura.

Evitar duplicação entre `integration checks` e `store checks`.

---

# 23. Dashboard operacional da loja

Cada loja deve possuir uma página de visão operacional.

Estrutura inspirada na organização do Cloudways, mas com identidade própria.

Seções:

### Status geral

- API
- site
- app
- servidor
- checks críticos

### Verificações recentes

Mostrar apenas itens relevantes.

### Alertas ativos

Problemas que ainda não se recuperaram.

### Ações rápidas

- Produtos
- Pedidos
- Logs
- Cron
- Verificações
- Teste de API

### Histórico recente

- últimas ações do suporte;
- atualizações executadas;
- falhas detectadas.

Não criar gráficos apenas para preencher espaço.

---

# 24. Níveis de acesso

Preparar controle de acesso.

Exemplo:

| Recurso | N1 | N2/Admin |
|---|---:|---:|
| Consultar API | Sim | Sim |
| Produtos | Sim | Sim |
| Pedidos | Sim | Sim |
| Alterar produto | conforme política | Sim |
| Alterar pedido | conforme política | Sim |
| Ver logs autorizados | Sim | Sim |
| Rodar checks | Sim | Sim |
| Criar checks | conforme política | Sim |
| Configurar servidor | Não | Sim |
| Credenciais | Não | Sim |
| Operações corretivas de infraestrutura | Não | Sim |

Não confiar apenas em ocultar botões.

Validar autorização no backend.

---

# 25. Auditoria

Registrar ações relevantes:

- usuário;
- loja;
- recurso;
- ação;
- resultado;
- data;
- parâmetros seguros;
- erro.

Nunca registrar secrets.

A execução manual de checks deve ser auditada.

A consulta de logs do servidor também deve ser auditada.

---

# 26. Desempenho

## Backend

Evitar:

- N+1;
- chamadas duplicadas;
- catálogos inteiros em memória;
- leitura completa de logs;
- checks síncronos durante request web.

Usar:

- filas;
- scheduler;
- timeout;
- retry controlado;
- backoff;
- paginação;
- cache quando seguro.

## Frontend

Evitar:

- reload completo;
- grids recriados sem necessidade;
- telas gigantes;
- dezenas de cards redundantes.

Usar drawers para detalhes e preservar contexto.

---

# 27. Tratamento de falhas

Falha em uma loja não deve comprometer outras.

Falha de integração de servidor não deve impedir uso da API.

Falha de um check não deve interromper o scheduler.

Todo job deve capturar exceções esperadas e registrar resultado apropriado.

Distinguir claramente:

- falha no alvo;
- falha na conexão;
- falha interna do BringPainel.

---

# 28. Testes obrigatórios

Criar ou atualizar testes para:

## Produtos

- normalização WooCommerce;
- normalização Magento;
- payload parcial;
- preview;
- rollback;
- edição em massa.

## Pedidos

- normalização;
- mudança de status;
- rejeição de transição inválida;
- comportamento específico Magento.

## Infraestrutura

- leitura autorizada de log;
- bloqueio de path traversal;
- redaction;
- check de cron.

## Checks

- scheduler seleciona apenas checks vencidos;
- runner correto por tipo;
- HTTP status;
- robots.txt;
- sitemap;
- meta robots;
- maintenance;
- API health;
- timeout;
- UNKNOWN em erro interno;
- contador de falhas consecutivas;
- alerta somente após threshold;
- recovery notification;
- checks desabilitados não executam.

Usar mocks e fixtures.

Não depender de lojas reais nos testes automatizados.

---

# 29. Processo SDD obrigatório

O Codex deve trabalhar em etapas formais.

## Fase 1 — Discovery

Antes de escrever código:

1. mapear estrutura atual;
2. identificar arquivos afetados;
3. identificar migrations existentes;
4. identificar componentes reutilizáveis;
5. identificar gaps entre README e implementação real.

Produzir um resumo técnico antes da implementação.

## Fase 2 — Specification

Para cada feature grande, escrever uma mini especificação contendo:

- objetivo;
- comportamento esperado;
- entradas;
- saídas;
- regras de negócio;
- erros;
- permissões;
- testes.

Não iniciar a implementação de uma feature grande sem essa definição.

## Fase 3 — Design

Definir:

- classes novas;
- classes alteradas;
- contratos;
- migrations;
- componentes Livewire;
- jobs;
- eventos;
- fluxo de dados.

## Fase 4 — Implementação

Implementar incrementalmente.

Não fazer uma alteração massiva sem checkpoints.

## Fase 5 — Testes

Executar testes direcionados a cada etapa.

Depois executar suíte relevante.

## Fase 6 — Revisão

Verificar:

- regressões;
- duplicações;
- código morto;
- autorização;
- logs sensíveis;
- experiência SPA;
- dark mode;
- comportamento em erro.

---

# 30. Ordem recomendada de implementação

1. analisar projeto atual;
2. refatorar layout base;
3. criar design system mínimo;
4. light/dark mode;
5. refatorar listagem de lojas;
6. normalização de produtos;
7. nova listagem e drawer de produtos;
8. edição e preview;
9. pedidos e drawer;
10. status de pedidos;
11. abstração de infraestrutura;
12. logs Magento;
13. cron Magento;
14. modelo genérico de checks;
15. runners de SITE;
16. scheduler;
17. histórico;
18. alertas;
19. dashboard operacional;
20. testes e hardening.

---

# 31. Critérios de aceitação gerais

O trabalho será considerado concluído quando:

- a interface estiver consistente com a direção visual definida;
- não houver azul como cor primária ou secundária;
- light/dark mode funcionar;
- navegação SPA estiver consistente;
- produtos tiverem listagem e drawer completos;
- produtos puderem ser atualizados por API;
- preview e rollback funcionarem;
- pedidos tiverem listagem e detalhes;
- mudanças de status respeitarem cada plataforma;
- logs Magento puderem ser consultados com segurança;
- cron Magento puder ser diagnosticado;
- checks personalizados puderem ser criados;
- checks puderem ser classificados como SERVER, APP, API ou SITE;
- intervalo puder ser configurado;
- scheduler executar checks automaticamente;
- histórico de execuções estiver disponível;
- alertas forem enviados quando condições saírem do esperado;
- recuperação puder gerar notificação;
- robots.txt, sitemap.xml, meta robots, manutenção e HTTP status forem verificáveis;
- permissões forem validadas no backend;
- nenhuma credencial sensível for exposta;
- funcionalidades antigas relevantes continuarem funcionando;
- testes cobrirem fluxos críticos.

---

# 32. Restrições explícitas

Não fazer:

- terminal SSH web genérico;
- execução arbitrária de comandos por N1;
- leitura arbitrária de arquivos;
- exposição de credenciais;
- alteração direta de state/status Magento sem fluxo correto;
- checks síncronos durante requisição web;
- criação de gráficos decorativos sem utilidade;
- uso de emojis;
- azul como cor principal/secundária;
- introdução de React/Vue sem necessidade;
- reescrita completa do projeto;
- botões sem implementação real;
- endpoints fictícios;
- dados simulados em produção.

---

# 33. Resultado esperado

O BringPainel deve evoluir de um painel CRUD e de API para uma plataforma interna de suporte e operação.

O resultado final deve permitir que a equipe da Bring:

- gerencie lojas;
- consulte e edite produtos;
- consulte e gerencie pedidos;
- teste integrações;
- consulte logs;
- diagnostique cron;
- acompanhe saúde de aplicações;
- crie verificações periódicas;
- receba alertas quando condições saírem do esperado;
- diagnostique problemas sem depender de acesso SSH direto;
- utilize uma interface simples, rápida e adequada ao uso diário.

O sistema deve continuar simples apesar do aumento de funcionalidades.

A prioridade deve ser clareza operacional, segurança, extensibilidade e baixo atrito para o suporte.

---

# 34. Entrega final esperada do Codex

Ao finalizar cada macro etapa, apresentar:

- resumo do que foi implementado;
- arquivos alterados;
- migrations criadas;
- decisões arquiteturais;
- testes executados;
- resultado dos testes;
- limitações conhecidas;
- próximos passos recomendados.

Ao concluir o projeto, atualizar o README para refletir a implementação real.

