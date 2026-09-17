<div class="stack-xl">
    <section class="hero-grid">
        <article class="hero-card hero-card--wide">
            <span class="eyebrow">{{ $store->name }}</span>
            <h1>Pedidos</h1>
            <p>
                <span class="platform-pill platform-pill--{{ $store->platform }}">
                    {{ $store->platform === 'woocommerce' ? 'WooCommerce' : 'Magento 2' }}
                </span>
                &mdash; {{ $store->store_url }}
            </p>
        </article>
    </section>

    @if ($errors->has('api'))
        <div class="flash flash--error">Erro de API: {{ $errors->first('api') }}</div>
    @endif

    <section class="panel stack-lg">
        <div class="toolbar">
            <label class="field field--filter">
                <span>Status</span>
                <select wire:model="filters.status">
                    <option value="">Todos</option>
                    <option value="pending">Pendente</option>
                    <option value="processing">Processando</option>
                    <option value="completed">Concluído</option>
                    <option value="cancelled">Cancelado</option>
                    <option value="refunded">Reembolsado</option>
                    <option value="on-hold">Em espera</option>
                </select>
            </label>
            <label class="field field--search">
                <span>E-mail do cliente</span>
                <input type="text" wire:model="filters.customer_email" placeholder="customer@example.com" />
            </label>
            <label class="field">
                <span>De</span>
                <input type="date" wire:model="filters.date_from" />
            </label>
            <label class="field">
                <span>Até</span>
                <input type="date" wire:model="filters.date_to" />
            </label>
            <label class="field">
                <span>Por página</span>
                <select wire:model="perPage">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>
            <button type="button" class="button" wire:click="loadOrders">
                <span wire:loading.remove wire:target="loadOrders">Carregar pedidos</span>
                <span wire:loading wire:target="loadOrders">Carregando...</span>
            </button>
        </div>

        <div class="toolbar">
            <button type="button" id="btn-bulk-order-apply" class="button button--ghost">Aplicar aos selecionados</button>
            <button type="button" id="btn-save-order-changes" class="button">
                Salvar alterações
                @if (count($pendingChanges) > 0)
                    <span class="badge">{{ count($pendingChanges) }}</span>
                @endif
            </button>
        </div>

        <div
            id="orders-grid"
            class="ag-theme-alpine"
            style="height: 480px; width: 100%;"
            wire:ignore
        ></div>

        <div class="table-subtitle">
            Total: {{ $total }} pedidos &mdash; página {{ $page }}
        </div>

        <div class="toolbar">
            @if ($page > 1)
                <button type="button" class="button button--ghost" wire:click="$set('page', {{ $page - 1 }})">
                    &laquo; Anterior
                </button>
            @endif
            @if ($total > $page * $perPage)
                <button type="button" class="button button--ghost" wire:click="$set('page', {{ $page + 1 }})">
                    Próxima &raquo;
                </button>
            @endif
        </div>
    </section>

    @if (!empty($executionFeedback))
        <section class="panel stack-lg">
            <h2 class="section-title">Resultados da execução</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Status</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($executionFeedback as $fb)
                            <tr wire:key="fb-{{ $fb['id'] }}">
                                <td>{{ $fb['id'] }}</td>
                                <td>
                                    <span class="check-badge check-badge--{{ $fb['status'] === 'ok' ? 'ok' : 'error' }}">
                                        {{ $fb['status'] }}
                                    </span>
                                </td>
                                <td class="table-subtitle">{{ $fb['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($showBulkModal)
        <div class="modal-overlay">
            <div class="modal">
                <h2>Aplicar em lote a {{ count($bulkSelectedIds) }} pedido(s)</h2>

                <label class="field">
                    <span>Novo status</span>
                    <select wire:model="bulkAttributes.status">
                        <option value="">— Selecione —</option>
                        <option value="processing">Processando</option>
                        <option value="completed">Concluído</option>
                        <option value="cancelled">Cancelado</option>
                        <option value="on-hold">Em espera</option>
                    </select>
                </label>

                <div class="toolbar" style="margin-top: 1rem;">
                    <button type="button" class="button" wire:click="applyBulk">Aplicar em lote</button>
                    <button type="button" class="button button--ghost" wire:click="$set('showBulkModal', false)">Cancelar</button>
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
    @vite('resources/js/ag-grid-orders.js')
    <script>
        document.addEventListener('livewire:initialized', () => {
            const wireId = document.querySelector('[wire\\:id]')?.getAttribute('wire:id');
            if (wireId) {
                window.initOrdersGrid(wireId);
            }
        });
    </script>
@endpush
