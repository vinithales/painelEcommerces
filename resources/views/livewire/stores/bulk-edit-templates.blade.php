<div class="stack-xl">
    <section class="hero-grid">
        <article class="hero-card hero-card--wide">
            <span class="eyebrow">{{ $store->name }}</span>
            <h1>Modelos de edição em lote</h1>
            <p>Defina conjuntos reutilizáveis de alterações para aplicar a vários produtos ou pedidos de uma vez.</p>

            <div class="hero-card__actions">
                <button type="button" class="button" wire:click="openCreate">Novo modelo</button>
            </div>
        </article>
    </section>

    @if ($showForm)
        <section class="panel stack-lg">
            <h2 class="section-title">{{ $editingId ? 'Editar modelo' : 'Novo modelo' }}</h2>

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
                <span>Nome</span>
                <input type="text" wire:model="name" placeholder="ex.: colocar todos como rascunho" />
            </label>

            <label class="field field--filter">
                <span>Tipo de recurso</span>
                <select wire:model="resourceType">
                    <option value="product">Product</option>
                    <option value="order">Order</option>
                </select>
            </label>

            <label class="field--inline">
                <input type="checkbox" wire:model="isGlobal" id="is-global" />
                <span>Global (disponível para todas as lojas)</span>
            </label>

            <label class="field">
                <span>Alterações de atributos (JSON)</span>
                <textarea
                    wire:model="rawAttributes"
                    rows="8"
                    placeholder='{"status": "draft", "price": "29.90"}'
                    style="font-family: monospace; font-size: 0.85rem;"
                ></textarea>
                <small class="hint">Objeto JSON em que as chaves são campos da API e os valores são os novos valores a aplicar.</small>
            </label>

            <div class="toolbar">
                <button type="button" class="button" wire:click="save">
                    <span wire:loading.remove wire:target="save">Salvar modelo</span>
                    <span wire:loading wire:target="save">Salvando...</span>
                </button>
                <button type="button" class="button button--ghost" wire:click="$set('showForm', false)">Cancelar</button>
            </div>
        </section>
    @endif

    <section class="panel stack-lg">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Recurso</th>
                        <th>Escopo</th>
                        <th>Atributos</th>
                        <th class="table-actions">Ações</th>
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
                                    <span class="inline-tag">Esta loja</span>
                                @endif
                            </td>
                            <td>
                                <code style="font-size: 0.8rem;">{{ substr(json_encode($template->attribute_changes), 0, 60) }}</code>
                            </td>
                            <td class="table-actions">
                                <button
                                    type="button"
                                    class="button button--small button--ghost"
                                    wire:click="openEdit({{ $template->id }})"
                                >
                                    Editar
                                </button>
                                <button
                                    type="button"
                                    class="button button--small button--danger"
                                    wire:click="delete({{ $template->id }})"
                                    wire:confirm="Excluir o modelo '{{ $template->name }}'?"
                                >
                                    Excluir
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="table-subtitle" style="text-align: center;">Nenhum modelo encontrado. Crie um para agilizar as edições em lote.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
