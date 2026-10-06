<div class="stack-xl product-create">
    <div class="page-header">
        <div>
            <h2>Novo produto</h2>
            <p>{{ $store->name }} · {{ $store->isMagento2() ? 'Magento 2' : 'WooCommerce' }}</p>
        </div>
        <a wire:navigate class="btn btn-secondary" href="{{ route('stores.products', $store) }}">Voltar aos produtos</a>
    </div>

    @if ($createdProduct)
        <div class="flash" role="status">
            Produto <strong>{{ $createdProduct['name'] }}</strong> (SKU {{ $createdProduct['sku'] }}) criado na loja.
        </div>
        @if ($imageErrors)
            <div class="flash flash--warning" role="alert">
                O produto foi criado, mas algumas imagens não foram adicionadas:
                <ul>@foreach ($imageErrors as $imageError)<li>{{ $imageError }}</li>@endforeach</ul>
            </div>
        @endif
        <div class="toolbar">
            <a wire:navigate class="btn btn-primary" href="{{ route('stores.products', $store) }}">Ver produtos</a>
            <button type="button" class="btn btn-secondary" wire:click="newProduct">Criar outro produto</button>
        </div>
    @else
        @if ($errors->has('api'))
            <div class="flash flash--error" role="alert">{{ $errors->first('api') }}</div>
        @endif

        <form wire:submit="save" class="product-create__form">
            <section class="panel stack-lg" aria-labelledby="product-create-basics">
                <div class="panel__header">
                    <h2 id="product-create-basics">Dados do produto</h2>
                    <p>Cadastre um produto simples. Ele começa sem publicação ativa.</p>
                </div>
                <div class="product-create__grid">
                    <label>Nome *<input type="text" wire:model="form.name" maxlength="255" required>@error('form.name')<span class="field-error">{{ $message }}</span>@enderror</label>
                    <label>SKU *<input type="text" wire:model="form.sku" maxlength="255" required>@error('form.sku')<span class="field-error">{{ $message }}</span>@enderror</label>
                    <label>Preço *<input type="number" wire:model="form.price" min="0" step="0.01" required>@error('form.price')<span class="field-error">{{ $message }}</span>@enderror</label>
                    <label>Status
                        <select wire:model="form.status">
                            @if ($store->isMagento2())
                                <option value="disabled">Desabilitado</option><option value="enabled">Habilitado</option>
                            @else
                                <option value="draft">Rascunho</option><option value="pending">Pendente</option><option value="private">Privado</option><option value="publish">Publicado</option>
                            @endif
                        </select>
                        @error('form.status')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                    <label>Peso<input type="number" wire:model="form.weight" min="0" step="0.001" placeholder="Opcional">@error('form.weight')<span class="field-error">{{ $message }}</span>@enderror</label>
                    @if ($store->isMagento2())
                        <label>Conjunto de atributos *
                            <select wire:model="form.attribute_set_id" required>
                                <option value="">Selecione um conjunto de produto</option>
                                @foreach ($attributeSets as $set)
                                    <option value="{{ $set['attribute_set_id'] }}">{{ $set['attribute_set_name'] ?? 'Conjunto' }} (ID {{ $set['attribute_set_id'] }})</option>
                                @endforeach
                            </select>
                            @error('form.attribute_set_id')<span class="field-error">{{ $message }}</span>@enderror
                            @if ($attributeSetsError)<span class="field-error">Não foi possível carregar os conjuntos: {{ $attributeSetsError }}</span>@endif
                        </label>
                        <label>Visibilidade
                            <select wire:model="form.visibility"><option value="4">Catálogo e busca</option><option value="2">Catálogo</option><option value="3">Busca</option><option value="1">Oculto</option></select>
                        </label>
                        <label>Quantidade em estoque<input type="number" wire:model="form.stock_quantity" min="0" step="1" placeholder="Opcional">@error('form.stock_quantity')<span class="field-error">{{ $message }}</span>@enderror</label>
                    @else
                        <label class="product-create__checkbox"><input type="checkbox" wire:model.live="form.manage_stock">Gerenciar estoque</label>
                        @if ($form['manage_stock'])
                            <label>Quantidade em estoque<input type="number" wire:model="form.stock_quantity" min="0" step="1">@error('form.stock_quantity')<span class="field-error">{{ $message }}</span>@enderror</label>
                        @endif
                    @endif
                </div>
                <label>Descrição<textarea wire:model="form.description" rows="5"></textarea>@error('form.description')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label>Descrição curta<textarea wire:model="form.short_description" rows="3"></textarea>@error('form.short_description')<span class="field-error">{{ $message }}</span>@enderror</label>
            </section>

            <section class="panel stack-lg" aria-labelledby="product-create-attributes">
                <div class="product-create__section-heading">
                    <div class="panel__header"><h2 id="product-create-attributes">Atributos</h2><p>{{ $store->isMagento2() ? 'Informe o código do atributo e o valor aceito pelo Magento.' : 'Informe o nome e as opções separadas por vírgula.' }}</p></div>
                    <button type="button" class="btn btn-secondary" wire:click="addAttribute" @disabled(count($productAttributes) >= 20)>Adicionar atributo</button>
                </div>
                @forelse ($productAttributes as $index => $attribute)
                    <div class="product-create__row" wire:key="product-attribute-{{ $index }}">
                        <label>{{ $store->isMagento2() ? 'Código' : 'Nome' }}<input type="text" wire:model="productAttributes.{{ $index }}.code" placeholder="{{ $store->isMagento2() ? 'ex.: color' : 'ex.: Cor' }}">@error('productAttributes.'.$index.'.code')<span class="field-error">{{ $message }}</span>@enderror</label>
                        <label>{{ $store->isMagento2() ? 'Valor / ID da opção' : 'Opções' }}<input type="text" wire:model="productAttributes.{{ $index }}.value" placeholder="{{ $store->isMagento2() ? 'ex.: 52' : 'ex.: Azul, Verde' }}">@error('productAttributes.'.$index.'.value')<span class="field-error">{{ $message }}</span>@enderror</label>
                        <button type="button" class="btn btn-secondary" wire:click="removeAttribute({{ $index }})" aria-label="Remover atributo {{ $index }}">Remover</button>
                    </div>
                @empty
                    <p class="muted">Nenhum atributo adicional.</p>
                @endforelse
                @error('productAttributes')<span class="field-error">{{ $message }}</span>@enderror
            </section>

            <section class="panel stack-lg" aria-labelledby="product-create-images">
                <div class="panel__header">
                    <h2 id="product-create-images">Imagens</h2>
                    <p>Adicione URLs públicas ou arquivos JPG, PNG e WebP de até 5 MB. Limite: {{ $store->isMagento2() ? 5 : 10 }} imagens. {{ $store->isMagento2() ? 'Escolha uma imagem principal para a loja.' : 'A primeira será a principal.' }}</p>
                </div>
                @if ($store->isWooCommerce())
                    <p class="muted">Para enviar arquivos, configure usuário e senha de aplicativo WordPress nas configurações da loja. URLs públicas não precisam dessa configuração.</p>
                @endif
                <div><button type="button" class="btn btn-secondary" wire:click="addImageUrl" @disabled(count($imageUrls) >= ($store->isMagento2() ? 5 : 10))>Adicionar URL de imagem</button></div>
                @foreach ($imageUrls as $index => $image)
                    <div class="product-create__image-row" wire:key="product-image-{{ $index }}">
                        <label>URL da imagem *<input type="url" wire:model="imageUrls.{{ $index }}.src" placeholder="https://..." required>@error('imageUrls.'.$index.'.src')<span class="field-error">{{ $message }}</span>@enderror</label>
                        <label>Nome<input type="text" wire:model="imageUrls.{{ $index }}.name" maxlength="255"></label>
                        <label>Texto alternativo<input type="text" wire:model="imageUrls.{{ $index }}.alt" maxlength="2000"></label>
                        @if ($store->isMagento2())
                            <div class="product-create__image-options">
                                <label>Rótulo no Magento<input type="text" wire:model="imageUrls.{{ $index }}.label" maxlength="255" placeholder="Opcional"></label>
                                <label>Posição<input type="number" wire:model="imageUrls.{{ $index }}.position" min="1" max="1000" placeholder="Automática"></label>
                                <label>Função<select wire:model="imageUrls.{{ $index }}.role"><option value="galeria">Galeria</option><option value="principal">Principal, pequena e miniatura</option></select></label>
                                <label class="product-create__checkbox"><input type="checkbox" wire:model="imageUrls.{{ $index }}.disabled">Desabilitar imagem</label>
                            </div>
                        @endif
                        <button type="button" class="btn btn-secondary" wire:click="removeImageUrl({{ $index }})" aria-label="Remover imagem {{ $index }}">Remover</button>
                    </div>
                @endforeach
                @error('imageUrls')<span class="field-error">{{ $message }}</span>@enderror
                <label>Selecionar arquivos<input type="file" wire:model="uploads" accept="image/jpeg,image/png,image/webp" multiple></label>
                <div wire:loading wire:target="uploads" role="status" class="muted">Enviando imagens...</div>
                @error('uploads')<span class="field-error">{{ $message }}</span>@enderror
                @error('uploads.*')<span class="field-error">{{ $message }}</span>@enderror
                @error('images')<span class="field-error">{{ $message }}</span>@enderror
                @foreach ($uploads as $index => $file)
                    <div class="product-create__row" wire:key="product-upload-{{ $index }}">
                        <strong>{{ $file->getClientOriginalName() }}</strong>
                        <label>Nome da imagem<input type="text" wire:model="imageMeta.{{ $index }}.name" maxlength="255" placeholder="{{ $file->getClientOriginalName() }}"></label>
                        <label>Texto alternativo<input type="text" wire:model="imageMeta.{{ $index }}.alt" maxlength="2000" placeholder="Descreva a imagem"></label>
                        @if ($store->isMagento2())
                            <div class="product-create__image-options">
                                <label>Rótulo no Magento<input type="text" wire:model="imageMeta.{{ $index }}.label" maxlength="255" placeholder="Opcional"></label>
                                <label>Posição<input type="number" wire:model="imageMeta.{{ $index }}.position" min="1" max="1000" placeholder="Automática"></label>
                                <label>Função<select wire:model="imageMeta.{{ $index }}.role">
                                    @if ($index === 0 && $imageUrls === [])
                                        <option value="principal">Principal, pequena e miniatura</option><option value="galeria">Galeria</option>
                                    @else
                                        <option value="galeria">Galeria</option><option value="principal">Principal, pequena e miniatura</option>
                                    @endif
                                </select></label>
                                <label class="product-create__checkbox"><input type="checkbox" wire:model="imageMeta.{{ $index }}.disabled">Desabilitar imagem</label>
                            </div>
                        @endif
                    </div>
                @endforeach
            </section>

            <div class="product-create__footer">
                <a wire:navigate class="btn btn-secondary" href="{{ route('stores.products', $store) }}">Cancelar</a>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save,uploads">
                    <span wire:loading.remove wire:target="save">Criar produto</span>
                    <span wire:loading wire:target="save">Criando produto...</span>
                </button>
            </div>
        </form>
    @endif
</div>
