<div class="stack-xl" @if($cacheStatus === 'warming') wire:poll.2000ms="checkCacheProgress" @endif>
    <section class="hero-grid">
        <article class="hero-card hero-card--wide">
            <span class="eyebrow">{{ $store->name }}</span>
            <h1>Products</h1>
            <p>
                <span class="platform-pill platform-pill--{{ $store->platform }}">
                    {{ $store->platform === 'woocommerce' ? 'WooCommerce' : 'Magento 2' }}
                </span>
                &mdash; {{ $store->store_url }}
            </p>
        </article>
    </section>

    {{-- Errors --}}
    @if ($errors->has('api'))
        <div class="flash flash--error">API error: {{ $errors->first('api') }}</div>
    @endif
    @if ($errors->has('rollback'))
        <div class="flash flash--error">Rollback failed: {{ $errors->first('rollback') }}</div>
    @endif

    <section class="panel stack-lg">

        {{-- Cache progress bar --}}
        @if ($cacheStatus === 'warming')
            <div class="cache-progress">
                <div class="cache-progress__header">
                    <span class="cache-progress__label">
                        <span class="cache-progress__spinner"></span>
                        Loading products from APIâ€¦
                    </span>
                    <span class="cache-progress__count">
                        @if ($cacheProgressTotal > 0)
                            {{ number_format($cacheProgressFetched) }} / {{ number_format($cacheProgressTotal) }}
                        @else
                            Startingâ€¦
                        @endif
                    </span>
                </div>
                @if ($cacheProgressTotal > 0)
                    <div class="cache-progress__bar">
                        <div class="cache-progress__fill" style="width: {{ min(100, round($cacheProgressFetched / $cacheProgressTotal * 100)) }}%"></div>
                    </div>
                @endif
            </div>
        @elseif ($cacheStatus === 'failed')
            <div class="flash flash--error">Failed to load products from API. <button type="button" class="button button--small" wire:click="refreshCache">Try again</button></div>
        @endif

        {{-- Filters toolbar (all client-side) --}}
        <div class="products-toolbar" wire:ignore>
            <label class="field field--search">
                <span>SKU</span>
                <input type="text" id="filter-sku" placeholder="Exact SKU" />
            </label>
            <label class="field field--search">
                <span>SKU prefix</span>
                <input type="text" id="filter-sku-prefix" placeholder="e.g. SHIRT-" />
            </label>
            <label class="field field--filter">
                <span>Status</span>
                <select id="filter-status">
                    <option value="">All</option>
                    <option value="publish">Published</option>
                    <option value="draft">Draft</option>
                    <option value="enabled">Enabled (M2)</option>
                    <option value="disabled">Disabled (M2)</option>
                </select>
            </label>
            <label class="field">
                <span>From</span>
                <input type="date" id="filter-date-from" />
            </label>
            <label class="field">
                <span>To</span>
                <input type="date" id="filter-date-to" />
            </label>
            <button type="button" class="button button--ghost" id="btn-clear-filters">Clear filters</button>
            <button type="button" class="button button--ghost" wire:click="refreshCache" title="Discard cache and reload from API">
                <span wire:loading.remove wire:target="refreshCache">â†º Refresh</span>
                <span wire:loading wire:target="refreshCache">Refreshingâ€¦</span>
            </button>
        </div>

        {{-- Action bar --}}
        <div class="toolbar">
            <label class="field--inline">
                <input type="checkbox" wire:model.live="dryRunMode" id="dry-run-toggle" />
                <span>Preview mode (dry-run)</span>
            </label>
            @if ($dryRunMode)
                <span class="inline-tag">Changes will be previewed, not applied</span>
            @endif
            <button type="button" id="btn-preview" class="button button--ghost">Preview pending</button>
            <button type="button" id="btn-bulk-apply" class="button button--ghost">Bulk apply to selected</button>
            <button type="button" id="btn-save-changes" class="button">
                {{ $dryRunMode ? 'Preview all' : 'Save changes' }}
                @if (count($pendingChanges) > 0)
                    <span class="badge">{{ count($pendingChanges) }}</span>
                @endif
            </button>
        </div>

        {{-- AG Grid --}}
        <div
            id="products-grid"
            class="products-grid"
            wire:ignore
        ></div>

    </section>

    {{-- Dry-run previews --}}
    @if (!empty($dryRunPreviews))
        <section class="panel stack-lg">
            <h2 class="section-title">Dry-run preview</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Field</th>
                            <th>From</th>
                            <th>To</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dryRunPreviews as $preview)
                            @if (isset($preview['error']))
                                <tr>
                                    <td colspan="4" class="table-subtitle">
                                        {{ $preview['product_id'] ?? 'â€”' }}: {{ $preview['error'] }}
                                    </td>
                                </tr>
                            @else
                                @foreach ($preview['diff'] ?? [] as $field => $change)
                                    <tr>
                                        <td>{{ $preview['product_id'] }}</td>
                                        <td>{{ $field }}</td>
                                        <td class="table-subtitle">{{ $change['from'] ?? 'â€”' }}</td>
                                        <td><strong>{{ $change['to'] }}</strong></td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Execution feedback --}}
    @if (!empty($executionFeedback))
        <section class="panel stack-lg">
            <h2 class="section-title">Execution results</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product ID</th>
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

    {{-- Snapshots / Rollback --}}
    @if ($snapshots->isNotEmpty())
        <section class="panel stack-lg">
            <h2 class="section-title">Recent changes (rollback available)</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Changed at</th>
                            <th class="table-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($snapshots as $snapshot)
                            <tr wire:key="snap-{{ $snapshot->id }}">
                                <td>{{ $snapshot->resource_id }}</td>
                                <td class="table-subtitle">{{ $snapshot->created_at->diffForHumans() }}</td>
                                <td class="table-actions">
                                    <button
                                        type="button"
                                        class="button button--small button--ghost"
                                        wire:click="rollback({{ $snapshot->id }})"
                                        wire:confirm="Rollback product #{{ $snapshot->resource_id }} to its previous state?"
                                    >
                                        Rollback
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Bulk apply modal --}}
    @if ($showBulkModal)
        <div class="modal-overlay">
            <div class="modal">
                <h2>Bulk apply to {{ count($bulkSelectedIds) }} product(s)</h2>

                @if ($templates->isNotEmpty())
                    <label class="field">
                        <span>Load template</span>
                        <select wire:model.live="selectedTemplateId" wire:change="loadTemplate">
                            <option value="">â€” Select template â€”</option>
                            @foreach ($templates as $tpl)
                                <option value="{{ $tpl->id }}">
                                    {{ $tpl->name }}{{ $tpl->isGlobal() ? ' (global)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <div class="stack-lg">
                    @foreach ($bulkAttributes as $key => $value)
                        <label class="field">
                            <span>{{ $key }}</span>
                            <input type="text" wire:model="bulkAttributes.{{ $key }}" />
                        </label>
                    @endforeach
                </div>

                <div class="toolbar" style="margin-top: 1rem;">
                    <button type="button" class="button" wire:click="applyBulk">
                        {{ $dryRunMode ? 'Preview bulk' : 'Apply bulk' }}
                    </button>
                    <button type="button" class="button button--ghost" wire:click="$set('showBulkModal', false)">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
    @vite('resources/js/ag-grid-products.js')
    <script>
        document.addEventListener('livewire:initialized', () => {
            const wireId = document.querySelector('[wire\\:id]')?.getAttribute('wire:id');
            if (wireId) {
                window.initProductsGrid(wireId);
            }
        });
    </script>
@endpush
