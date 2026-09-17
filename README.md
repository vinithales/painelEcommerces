# BringPainel

BringPainel is a Laravel 10 backoffice for managing e-commerce stores connected through WooCommerce and Magento 2. It centralizes store records, platform credentials, integration health checks, and a full API management module for listing, editing, and monitoring products and orders across platforms.

## Stack

- PHP 8.1+ / Laravel 10
- Livewire 3 (full-page components, SPA navigation)
- AG Grid Community (inline spreadsheet editing)
- Vite 4 + laravel-vite-plugin
- PHPUnit 10

## Domain Model

### Store (`stores`)

The central entity. Each record represents one connected store.

| Field | Description |
|---|---|
| `name` | Display name |
| `platform` | `woocommerce` or `magento2` |
| `store_url` | Base URL of the connected store |
| `panel_prefix` | Internal hub identifier |
| `payment_gateway` | Payment provider |
| `shipping_gateway` | Shipping provider |

### Platform credentials

WooCommerce credentials (`woocommerce_integrations`): `consumer_key`, `consumer_secret`.

Magento 2 credentials (`magento2_integrations`): `consumer_key`, `consumer_secret`, `access_token`, `access_token_secret`.

### Supporting tables

| Table | Purpose |
|---|---|
| `api_operation_logs` | Full audit log of every list/update API call |
| `product_snapshots` | Before/after snapshots enabling rollback |
| `bulk_edit_templates` | Saved sets of attribute changes for bulk edits |
| `store_integration_status` | Results of the automated integration health checks |

## Pages

### `/stores` — Store Hub

Lists all registered stores with platform badge, gateway summary, and credential type. Provides search by name/URL/prefix and filter by platform. Each row links directly to Products, Orders, Edit, and Delete actions.

**Component:** `App\Livewire\Stores\IndexStores`

---

### `/stores/create` and `/stores/{store}/edit` — Store CRUD

Form for creating and editing a store. Shared fields (name, URL, gateways) are always shown. Platform-specific credential fields appear conditionally based on the selected platform. Magento 2 shows four credential fields; WooCommerce shows two.

**Component:** `App\Livewire\Stores\CreateStore` / `EditStore`  
**Form object:** `App\Livewire\Forms\StoreForm`  
**Actions:** `App\Actions\Stores\CreateStoreAction`, `UpdateStoreAction`, `DeleteStoreAction`

---

### `/stores/checks` — SEO / Integration Checks

Runs a suite of integration health checks against all stores and displays the results per check key. Failed checks trigger alert emails via Mailtrap.

**Component:** `App\Livewire\Stores\StoreChecks`  
**Orchestrator:** `App\Services\IntegrationCheckers\RunStoreChecks`  
**Job:** `App\Jobs\CheckStoreIntegrationJob`

---

### `/stores/{store}/products` — Product Management

Full product management screen for a single store. Pulls products from the platform API and renders them in an AG Grid table with inline editing.

**Features:**
- Filter by SKU (exact), SKU prefix, status, and date range
- Pagination with configurable page size (25 / 50 / 100)
- Inline cell editing via AG Grid — changes are staged locally before saving
- **Preview mode (dry-run):** shows a diff table of what would change without calling the API
- **Save changes:** applies all staged edits by dispatching `UpdateProductJob` per product (queue-backed, 3 retries)
- **Bulk apply:** select rows in the grid and apply a fixed set of attributes to all selected products at once; supports templates
- **Rollback:** every update creates a `ProductSnapshot`; the last 20 non-reverted snapshots are listed with a one-click rollback button that reapplies `data_before`
- Execution feedback table showing per-product status after saves

**Component:** `App\Livewire\Stores\StoreProducts`  
**Service:** `App\Services\PlatformApi\ProductsApiService`  
**Actions:** `App\Actions\Products\UpdateProductAction`, `BulkUpdateProductsAction`, `RollbackProductAction`  
**Job:** `App\Jobs\UpdateProductJob`

---

### `/stores/{store}/orders` — Order Management

Order management screen equivalent to Products, without dry-run or rollback (orders are not snapshotted).

**Features:**
- Filter by status (pending, processing, completed, cancelled, refunded, on-hold), customer email, and date range
- Pagination with configurable page size
- Inline status editing via AG Grid
- **Save changes:** dispatches `UpdateOrderJob` per order
- **Bulk apply:** apply a status change to all selected orders
- Execution feedback table

**Component:** `App\Livewire\Stores\StoreOrders`  
**Service:** `App\Services\PlatformApi\OrdersApiService`  
**Actions:** `App\Actions\Orders\UpdateOrderAction`, `BulkUpdateOrdersAction`  
**Job:** `App\Jobs\UpdateOrderJob`

---

### `/stores/{store}/api-logs` — API Operation Logs

Full audit trail of every API call made by the panel for a given store. All list and update operations are logged automatically regardless of success or failure.

**Features:**
- Filter by operation type (list / update), resource type (product / order), status (ok / failed), and date range
- Filters apply live without page reload
- Paginated table with timestamp, operation, resource, status badge, and truncated error message
- Expandable detail rows showing full raw JSON payload and API response

**Component:** `App\Livewire\Stores\StoreApiLogs`  
**Model:** `App\Models\ApiOperationLog`

---

### `/stores/{store}/bulk-templates` — Bulk Edit Templates

CRUD screen for reusable attribute change sets that can be applied via the Products or Orders bulk apply modal.

**Features:**
- Create, edit, and delete named templates
- Templates are scoped to a store or marked as global (available to all stores)
- Attribute changes are defined as a JSON object (`{"status": "draft", "price": "29.90"}`)
- Resource type (product / order) is defined per template

**Component:** `App\Livewire\Stores\BulkEditTemplates`  
**Model:** `App\Models\BulkEditTemplate`

---

## Architecture

```
Livewire screen
  └─ Form object (validation)
       └─ Action (transaction + persistence)
            └─ Service / Adapter (API call + log)
                 └─ Model / Integration relation
```

- `App\Services\PlatformApi\Adapters\WooCommerceApiAdapter` — REST API v3, Basic Auth
- `App\Services\PlatformApi\Adapters\Magento2ApiAdapter` — REST API V1, OAuth 1.0 (HMAC-SHA256, no external package)
- `App\Services\PlatformApi\PlatformApiAdapterFactory` — resolves the correct adapter from `Store->platform`
- Platform branching is centralized in `App\Models\Store` through `isWooCommerce()`, `isMagento2()`, `usesAccessToken()`, `integration()`, and `integrationRelation()`

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

## Docker

Com Docker Desktop em execucao, suba a aplicacao, o MySQL, o worker da fila e o Mailpit:

```bash
docker compose up --build -d
```

A aplicacao estara em <http://localhost:8000> e a interface do Mailpit em
<http://localhost:8025>. As migrations rodam automaticamente quando o container
da aplicacao inicia.

O Compose esta configurado para desenvolvimento com hot reload. Os arquivos do
projeto sao montados nos containers da aplicacao e da fila:

- alteracoes em PHP e Blade aparecem ao recarregar a pagina;
- alteracoes em CSS e JavaScript sao atualizadas pelo Vite automaticamente;
- o servidor Vite fica disponivel em <http://localhost:5173>;
- `vendor`, `node_modules`, `storage` e o cache do Laravel permanecem em volumes
  Docker e nao poluem o diretorio local.

Depois da primeira inicializacao, nao e necessario reconstruir a imagem para
alteracoes normais no codigo. Se `composer.json`, extensoes PHP ou o Dockerfile
mudarem, execute novamente `docker compose up --build -d`.

Comandos uteis:

```bash
docker compose logs -f app
docker compose exec app php artisan migrate:status
docker compose down
```

Os dados do MySQL e os arquivos de `storage` ficam em volumes Docker. Para
remove-los tambem, use `docker compose down -v` (isso apaga os dados locais).

As portas e senhas podem ser sobrescritas no `.env`, por exemplo com
`APP_PORT`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `DB_PORT_FORWARD` e
`MAILPIT_UI_PORT`. A porta do Vite pode ser alterada com `VITE_PORT`.

Run the queue worker when using product/order edit features:

```bash
php artisan queue:work
```

## Testing

```bash
vendor/bin/phpunit
```

Tests live in `tests/Feature/Checks/` for integration checks and `tests/Unit/PlatformApi/` for adapter unit tests (Guzzle MockHandler, no real HTTP calls).
