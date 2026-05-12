<div class="stack-xl">
    <section class="hero-grid">
        <article class="hero-card hero-card--wide">
            <span class="eyebrow">{{ $store->name }}</span>
            <h1>Bulk Edit Templates</h1>
            <p>Define reusable sets of attribute changes to apply to multiple products or orders at once.</p>

            <div class="hero-card__actions">
                <button type="button" class="button" wire:click="openCreate">New template</button>
            </div>
        </article>
    </section>

    @if ($showForm)
        <section class="panel stack-lg">
            <h2 class="section-title">{{ $editingId ? 'Edit template' : 'New template' }}</h2>

            @if ($errors->any())
                <div class="flash flash--error">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <label class="field">
                <span>Name</span>
                <input type="text" wire:model="name" placeholder="e.g. Set all to draft" />
            </label>

            <label class="field field--filter">
                <span>Resource type</span>
                <select wire:model="resourceType">
                    <option value="product">Product</option>
                    <option value="order">Order</option>
                </select>
            </label>

            <label class="field--inline">
                <input type="checkbox" wire:model="isGlobal" id="is-global" />
                <span>Global (available to all stores)</span>
            </label>

            <label class="field">
                <span>Attribute changes (JSON)</span>
                <textarea
                    wire:model="rawAttributes"
                    rows="8"
                    placeholder='{"status": "draft", "price": "29.90"}'
                    style="font-family: monospace; font-size: 0.85rem;"
                ></textarea>
                <small class="hint">JSON object where keys are API field names and values are the new values to apply.</small>
            </label>

            <div class="toolbar">
                <button type="button" class="button" wire:click="save">
                    <span wire:loading.remove wire:target="save">Save template</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
                <button type="button" class="button button--ghost" wire:click="$set('showForm', false)">Cancel</button>
            </div>
        </section>
    @endif

    <section class="panel stack-lg">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Resource</th>
                        <th>Scope</th>
                        <th>Attributes</th>
                        <th class="table-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr wire:key="tpl-{{ $template->id }}">
                            <td>
                                <div class="table-title">{{ $template->name }}</div>
                            </td>
                            <td>{{ ucfirst($template->resource_type) }}</td>
                            <td>
                                @if ($template->isGlobal())
                                    <span class="inline-tag">Global</span>
                                @else
                                    <span class="inline-tag">This store</span>
                                @endif
                            </td>
                            <td>
                                <code style="font-size: 0.8rem;">{{ Str::limit(json_encode($template->attribute_changes), 60) }}</code>
                            </td>
                            <td class="table-actions">
                                <button
                                    type="button"
                                    class="button button--small button--ghost"
                                    wire:click="openEdit({{ $template->id }})"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    class="button button--small button--danger"
                                    wire:click="delete({{ $template->id }})"
                                    wire:confirm="Delete template '{{ $template->name }}'?"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="table-subtitle" style="text-align: center;">No templates yet. Create one to speed up bulk edits.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
