<div class="stack-xl">
    <section class="hero-grid">
        <article class="hero-card hero-card--wide">
            <span class="eyebrow">SEO monitor</span>
            <h1>Store indexability &amp; robots checks.</h1>
            <p>Run checks manually or wait for the daily 12:00 run. Stores with issues receive an e-mail alert automatically.</p>

            <div class="hero-card__actions">
                @if (!empty($selected))
                    <button
                        type="button"
                        class="button"
                        wire:click="runSelectedChecks"
                        wire:loading.attr="disabled"
                        wire:target="runSelectedChecks"
                    >
                        <span wire:loading.remove wire:target="runSelectedChecks">Run checks for {{ count($selected) }} selected</span>
                        <span wire:loading wire:target="runSelectedChecks">Running…</span>
                    </button>
                @endif
                <span class="hint">Checks: robots.txt availability and meta-robots / X-Robots-Tag directives.</span>
            </div>
        </article>
    </section>

    <section class="panel stack-lg">
        <div class="toolbar">
            <label class="field field--search">
                <span>Search</span>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or URL" />
            </label>

            <label class="field field--filter">
                <span>Platform</span>
                <select wire:model.live="platform">
                    <option value="all">All platforms</option>
                    <option value="woocommerce">WooCommerce</option>
                    <option value="magento2">Magento 2</option>
                </select>
            </label>

            <label class="field field--filter">
                <span>Status</span>
                <select wire:model.live="statusFilter">
                    <option value="all">All stores</option>
                    <option value="issues">With issues</option>
                    <option value="healthy">Healthy only</option>
                </select>
            </label>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 36px">
                            <input type="checkbox" wire:model.live="selectAll" />
                        </th>
                        <th>Store</th>
                        <th>Platform</th>
                        <th>robots.txt</th>
                        <th>Indexability</th>
                        <th class="table-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stores as $store)
                        @php
                            $statuses = $this->getStoreStatuses($store->id);
                            $errors   = $this->getStoreErrors($store->id);

                            $robotsStatus = $statuses['robots_txt']   ?? null;
                            $indexStatus  = $statuses['indexability']  ?? null;
                            $robotsError  = $errors['robots_txt']      ?? null;
                            $indexError   = $errors['indexability']    ?? null;
                        @endphp
                        <tr wire:key="check-{{ $store->id }}">
                            <td>
                                <input
                                    type="checkbox"
                                    value="{{ $store->id }}"
                                    wire:model.live="selected"
                                />
                            </td>
                            <td>
                                <div class="table-title">{{ $store->name }}</div>
                                <div class="table-subtitle">{{ $store->store_url }}</div>
                            </td>
                            <td>
                                <span class="platform-pill platform-pill--{{ $store->platform }}">
                                    {{ $store->platform === 'woocommerce' ? 'WooCommerce' : 'Magento 2' }}
                                </span>
                            </td>
                            <td>
                                @if ($robotsStatus === null)
                                    <span class="check-badge check-badge--pending">Not checked</span>
                                @elseif ($robotsStatus === 'ok')
                                    <span class="check-badge check-badge--ok">OK</span>
                                @elseif ($robotsStatus === 'missing')
                                    <span class="check-badge check-badge--warn" title="{{ $robotsError }}">Missing</span>
                                @else
                                    <span class="check-badge check-badge--fail" title="{{ $robotsError }}">{{ $robotsStatus }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($indexStatus === null)
                                    <span class="check-badge check-badge--pending">Not checked</span>
                                @elseif (in_array($indexStatus, ['indexable', 'index']))
                                    <span class="check-badge check-badge--ok">Indexable</span>
                                @elseif ($indexStatus === 'noindex')
                                    <span class="check-badge check-badge--fail" title="{{ $indexError }}">noindex</span>
                                @elseif ($indexStatus === 'noindex_nofollow')
                                    <span class="check-badge check-badge--fail" title="{{ $indexError }}">noindex + nofollow</span>
                                @elseif ($indexStatus === 'nofollow')
                                    <span class="check-badge check-badge--warn" title="{{ $indexError }}">nofollow</span>
                                @elseif ($indexStatus === 'index_nofollow')
                                    <span class="check-badge check-badge--warn" title="{{ $indexError }}">index + nofollow</span>
                                @else
                                    <span class="check-badge check-badge--fail" title="{{ $indexError }}">{{ $indexStatus }}</span>
                                @endif

                                @if ($indexError)
                                    <div class="table-subtitle check-detail">{{ $indexError }}</div>
                                @endif
                            </td>
                            <td class="table-actions">
                                <button
                                    type="button"
                                    class="button button--small button--ghost"
                                    wire:click="runCheck({{ $store->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="runCheck({{ $store->id }})"
                                >
                                    <span wire:loading.remove wire:target="runCheck({{ $store->id }})">Run check</span>
                                    <span wire:loading wire:target="runCheck({{ $store->id }})">Running…</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <strong>No stores found.</strong>
                                    <p>Adjust the filters or add a store first.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $stores->links() }}
        </div>
    </section>
</div>
