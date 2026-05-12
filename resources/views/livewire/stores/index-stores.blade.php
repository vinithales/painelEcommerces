<div class="stack-xl">

    {{-- Page header --}}
    <div class="page-header">
        <div>
            <h2>Store Hub</h2>
            <p>Manage and monitor your connected e-commerce platforms.</p>
        </div>
        <a href="{{ route('stores.create') }}" wire:navigate class="button">
            <span class="material-symbols-outlined" style="font-size:18px">add</span>
            New Store
        </a>
    </div>

    {{-- Stat cards --}}
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card__icon">
                <span class="material-symbols-outlined">store</span>
            </div>
            <div class="stat-card__label">Total Stores</div>
            <div class="stat-card__value">{{ $storeCount }}</div>
            <div class="stat-card__desc">active integrations</div>
        </div>
        <div class="stat-card">
            <div class="stat-card__icon">
                <span class="material-symbols-outlined">shopping_cart</span>
            </div>
            <div class="stat-card__label">WooCommerce</div>
            <div class="stat-card__value">{{ $wooCommerceCount }}</div>
            <div class="stat-card__desc">stores with consumer keys</div>
        </div>
        <div class="stat-card">
            <div class="stat-card__icon">
                <span class="material-symbols-outlined">inventory_2</span>
            </div>
            <div class="stat-card__label">Magento 2</div>
            <div class="stat-card__value">{{ $magento2Count }}</div>
            <div class="stat-card__desc">stores with token quartets</div>
        </div>
    </div>

    {{-- Search & filter bar --}}
    <div class="filter-bar">
        <div class="filter-bar__input-wrap">
            <span class="material-symbols-outlined filter-bar__icon">search</span>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search by name, URL or panel prefix…" />
        </div>
        <div class="filter-bar__divider"></div>
        <div style="display:flex;align-items:center;gap:6px;padding:0 8px;">
            <span class="material-symbols-outlined" style="font-size:20px;color:var(--text-muted)">filter_list</span>
            <select wire:model.live="platform">
                <option value="all">All Platforms</option>
                <option value="woocommerce">WooCommerce</option>
                <option value="magento2">Magento 2</option>
            </select>
        </div>
    </div>

    {{-- Data table --}}
    <div class="card" style="overflow:hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Store</th>
                        <th>Platform</th>
                        <th>Gateways</th>
                        <th>Credentials</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stores as $store)
                        <tr wire:key="store-{{ $store->id }}">
                            <td>
                                <div class="store-info-cell">
                                    <div class="store-avatar">
                                        <span class="material-symbols-outlined">
                                            {{ $store->isWooCommerce() ? 'shopping_bag' : 'store' }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="table-cell-name">{{ $store->name }}</div>
                                        <div class="table-cell-sub">{{ $store->store_url }}</div>
                                        @if ($store->panel_prefix)
                                            <span class="gateway-chip" style="margin-top:4px">
                                                <span class="material-symbols-outlined" style="font-size:12px">code</span>
                                                {{ $store->panel_prefix }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="platform-pill platform-pill--{{ $store->platform }}">
                                    {{ $store->platform === 'woocommerce' ? 'WooCommerce' : 'Magento 2' }}
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                    @if ($store->payment_gateway)
                                        <span class="gateway-chip">{{ $store->payment_gateway }}</span>
                                    @endif
                                    @if ($store->shipping_gateway)
                                        <span class="gateway-chip">{{ $store->shipping_gateway }}</span>
                                    @endif
                                    @if (!$store->payment_gateway && !$store->shipping_gateway)
                                        <span class="table-cell-sub">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="table-cell-sub">
                                {{ $store->isWooCommerce() ? 'Consumer key + secret' : 'Consumer pair + access token pair' }}
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('stores.products', $store) }}" wire:navigate class="icon-btn icon-btn--primary" title="Products">
                                        <span class="material-symbols-outlined">inventory_2</span>
                                    </a>
                                    <a href="{{ route('stores.orders', $store) }}" wire:navigate class="icon-btn icon-btn--primary" title="Orders">
                                        <span class="material-symbols-outlined">shopping_cart</span>
                                    </a>
                                    <a href="{{ route('stores.api-logs', $store) }}" wire:navigate class="icon-btn icon-btn--primary" title="API Logs">
                                        <span class="material-symbols-outlined">receipt_long</span>
                                    </a>
                                    <div style="width:1px;height:20px;background:var(--surface-border);margin:0 2px"></div>
                                    <a href="{{ route('stores.edit', $store) }}" wire:navigate class="icon-btn" title="Edit">
                                        <span class="material-symbols-outlined">edit</span>
                                    </a>
                                    <button
                                        type="button"
                                        class="icon-btn icon-btn--danger"
                                        wire:click="delete({{ $store->id }})"
                                        wire:confirm="Delete {{ $store->name }}?"
                                        title="Delete"
                                    >
                                        <span class="material-symbols-outlined">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center;padding:48px 20px;">
                                <span class="material-symbols-outlined" style="font-size:40px;color:var(--text-muted);display:block;margin-bottom:8px">storefront</span>
                                <strong style="display:block;margin-bottom:4px">No stores found</strong>
                                <p class="table-cell-sub" style="margin:0 0 16px">Adjust the filters or create your first store integration.</p>
                                <a href="{{ route('stores.create') }}" wire:navigate class="button">
                                    <span class="material-symbols-outlined" style="font-size:18px">add</span>
                                    Add Store
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($stores->hasPages())
            <div style="padding:12px 20px;border-top:1px solid var(--surface-border)">
                {{ $stores->links() }}
            </div>
        @endif
    </div>

</div>