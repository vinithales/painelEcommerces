# BeaconHub

Backoffice interno para opera??o de lojas WooCommerce e Magento 2. Mant?m Laravel 10, Livewire 3, AG Grid Community e Vite 4, com autentica??o, produtos/pedidos, ferramentas de suporte, diagn?stico Magento e verifica??es peri?dicas.

## Instala??o com Docker

```bash
docker compose up --build -d
docker compose exec app php artisan operator:create
```

Aplica??o: http://localhost:8000. Mailpit: http://localhost:8025. O comando solicita nome, e-mail, perfil e senha (m?nimo 12 caracteres); escolha admin para o primeiro administrador. N?o existe senha padr?o da aplica??o.

O Compose inicia MySQL, aplica??o, Vite, worker, scheduler e Mailpit. Migrations s?o aplicadas na inicializa??o da aplica??o. C?digo ? montado localmente; depend?ncias, storage e banco ficam em volumes. O Compose ? destinado a desenvolvimento.

Sem Docker: instale PHP 8.1+ com cURL, DOM/XML, PDO MySQL/SQLite e depend?ncias Laravel, execute composer install, npm ci, copie .env.example para .env e configure o banco:

```bash
php artisan key:generate
php artisan migrate
php artisan operator:create
npm run build
php artisan serve
php artisan queue:work --timeout=120
php artisan schedule:work
```

Em produ??o, use processos supervisionados para workers e cron executando php artisan schedule:run a cada minuto. Configure HTTPS, APP_DEBUG=false, SMTP e preserve APP_KEY para descriptografar credenciais existentes. Os processos devem compartilhar cache para os locks. N?o execute migrations de reset/fresh no banco de opera??o.

## Acesso e navega??o

| Perfil | Permiss?es |
|---|---|
| N1 | Consultar lojas, produtos, pedidos, logs e executar diagn?sticos |
| N2 | Permiss?es N1, editar produtos/pedidos, templates e checks |
| Admin | Permiss?es N2, cadastrar/configurar/excluir lojas e conex?es |

Permiss?es s?o verificadas no backend e nos jobs. Sidebar contextual, navega??o SPA, tema claro/escuro persistido e identidade esmeralda/grafite comp?em a interface. Sem prefer?ncia salva, o tema segue o sistema.

O hub /stores mostra sa?de, alertas, gateways e acesso aos m?dulos por loja. O dashboard agrega checks por n?vel, alertas e a??es recentes. Configura??es preservam segredos quando os campos ficam vazios.

## Produtos e pedidos

Listas usam pagina??o remota de 25/50/100 registros e filtros preservados na URL. Drawers apresentam detalhes, payload redigido e hist?rico. Produtos incluem imagens, pre?os, estoque, atributos, categorias e varia??es quando informados pela plataforma.

Edi??es enviam somente campos permitidos e alterados. Produtos possuem preview sem grava??o, snapshot anterior, releitura ap?s atualiza??o e rollback com confirma??o e prote??o contra altera??es posteriores. No WooCommerce, o pre?o edit?vel representa regular_price; o pre?o atual ? exibido separadamente. Magento identifica produtos por SKU e mant?m estoque somente leitura nesta vers?o.

Pedidos Magento oferecem cancelamento, hold e unhold pelos endpoints pr?prios; state/status n?o s?o sobrescritos diretamente. WooCommerce valida os status aceitos e bloqueia reembolso por simples troca de status. Faturamento, envio e reembolso Magento requerem fluxos pr?prios e n?o s?o simulados.

Edi??o em massa aceita at? 100 itens, templates e jobs com autoriza??o do operador revalidada. Atualiza??es de pedido n?o repetem automaticamente uma escrita de resultado incerto. Logs de API e auditoria registram resultados com reda??o de segredos.

Limites de APIs: filtro de prefixo WooCommerce refina a p?gina remota atual e mant?m a contagem remota; e-mail usa a busca do WooCommerce. N?mero de pedido WooCommerce usa ID interno. Moeda, tracking, dimens?es ou outros campos ausentes s?o apresentados como n?o informados.

## Verifica??es e alertas

Checks s?o classificados como SERVER, APP, API ou SITE. Tipos: HTTP_STATUS, CONTENT, META_ROBOTS, ROBOTS_TXT, SITEMAP_XML, MAINTENANCE, MAGENTO_CRON, API_HEALTH e RESPONSE_TIME.

O wizard oferece condi??es, intervalos de 5 minutos a 24 horas, timeout, severidade, falhas consecutivas e recupera??o. Teste do wizard e execu??o manual s?o ass?ncronos. Hist?rico registra in?cio/fim, dura??o, estado, detalhes e m?tricas. O scheduler central seleciona vencidos com claim at?mico, token de execu??o e lock por check.

Configure no .env:

```dotenv
QUEUE_CONNECTION=database
OPERATIONS_QUEUE_CONNECTION=database
CHECK_ALERT_RECIPIENTS=operacao@example.com
```

Configure tamb?m MAIL_*. No Docker local, e-mails chegam ao Mailpit. Destinat?rios vazios geram registro expl?cito de falha de configura??o; n?o h? destinat?rio impl?cito. Alertas suportam threshold, cooldown, aumento de severidade e recupera??o. Execu??es antigas s?o removidas ap?s 90 dias; alertas e auditoria permanecem armazenados.

```bash
php artisan checks:import-legacy
php artisan checks:dispatch
```

A importa??o de checks legados ? idempotente. O runner mant?m o resultado legado compat?vel. O cliente de checks restringe destinos a HTTP/S p?blicos, valida DNS/redirects, verifica TLS e limita tamanho e dura??o. UNKNOWN distingue falha de execu??o de uma condi??o detectada no alvo.

## Suporte e infraestrutura

Ferramentas oferecem teste de autentica??o, produto por ID/SKU, pedido por ID e informa??es da loja, com dura??o, HTTP status e resposta redigida. Diagn?stico consulta status do agente, cron e logs permitidos, com busca, n?vel, cursor, c?pia e download de trecho.

Consulte [implanta??o do agente](docs/agent-setup.md). Infraestrutura usa contrato pr?prio e transporte HTTPS autenticado, independente da API comercial. O agente inclui apenas leituras predefinidas; n?o oferece terminal SSH, caminhos arbitr?rios ou a??es corretivas remotas. Integra??es com provedores, Redis, servi?os/processos, indexadores e outros canais de alerta s?o extens?es futuras.

## Arquitetura e migrations

Fluxo: componente Livewire ? Action ? Service ? adapter por plataforma. Infraestrutura implementa ServerConnectionInterface; checks usam CheckStrategy, registry, dispatcher e runner. Suporte/checks executam em fila; edi??o individual usa Actions e lotes usam jobs.

Migrations adicionadas:

- 2026_09_17_000001_create_operations_tables: perfil em usu?rios, conex?es, auditoria, checks, execu??es e alertas.
- 2026_09_17_000002_add_check_claim_token: token para rejeitar jobs com claim vencido.

## Valida??o

```bash
docker compose exec app php vendor/bin/phpunit
docker compose exec vite npm run build
docker compose exec app php artisan view:cache
```

A su?te for?a SQLite :memory: antes de qualquer RefreshDatabase, mesmo quando Docker exporta vari?veis MySQL. APIs s?o simuladas nos testes, sem altera??es em lojas reais. N?o remova a prote??o de tests/CreatesApplication.php.

Especifica??o: [SDD](BeaconHub_Codex_SDD.md), [discovery e design](docs/SDD-implementation.md), [relat?rio de entrega](docs/SDD-delivery.md).
