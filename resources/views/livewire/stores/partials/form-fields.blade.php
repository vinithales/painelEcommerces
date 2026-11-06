<div class="form-grid">
    <section class="panel panel--form">
        <div class="panel__header">
            <span class="eyebrow">Perfil principal</span>
            <h2>{{ $heading }}</h2>
            <p>{{ $description }}</p>
        </div>

        <div class="field-grid">
            <label class="field">
                <span>Nome</span>
                <input type="text" wire:model.blur="form.name" placeholder="Carli Fitness" />
                @error('form.name') <small class="field__error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Plataforma</span>
                <select wire:model.live="form.platform">
                    <option value="woocommerce">WooCommerce</option>
                    <option value="magento2">Magento 2</option>
                </select>
                @error('form.platform') <small class="field__error">{{ $message }}</small> @enderror
            </label>

            <label class="field field--full">
                <span>URL da loja</span>
                <input type="url" wire:model.blur="form.store_url" placeholder="https://store.example.com" />
                @error('form.store_url') <small class="field__error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Prefixo do painel</span>
                <input type="text" wire:model.blur="form.panel_prefix" placeholder="carlifitness" />
                @error('form.panel_prefix') <small class="field__error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Gateway de pagamento</span>
                <input type="text" wire:model.blur="form.payment_gateway" placeholder="Pagar.me" />
                @error('form.payment_gateway') <small class="field__error">{{ $message }}</small> @enderror
            </label>

            <label class="field field--full">
                <span>Gateway de envio</span>
                <input type="text" wire:model.blur="form.shipping_gateway" placeholder="Correios, Melhor Envio, Frenet" />
                @error('form.shipping_gateway') <small class="field__error">{{ $message }}</small> @enderror
            </label>
        </div>
    </section>

    <section class="panel panel--form">
        <div class="panel__header">
            <h2>Acesso SSH (opcional)</h2>
            <p>Com IP, usuário e senha, as verificações usam a porta 22 e memorizam a chave do servidor no primeiro acesso. Para definir outra porta ou uma chave de confiança, use Diagnóstico.</p>
        </div>
        <div class="field-grid">
            <label class="field"><span>IP do servidor</span>
                <input type="text" wire:model="form.ssh_ip" placeholder="IPv4 ou IPv6">
                @error('form.ssh_ip') <small class="field__error">{{ $message }}</small> @enderror
            </label>
            <label class="field"><span>Usuário SSH</span>
                <input type="text" wire:model="form.ssh_user" autocomplete="off">
                @error('form.ssh_user') <small class="field__error">{{ $message }}</small> @enderror
            </label>
            <label class="field"><span>Senha SSH</span>
                <input type="password" wire:model="form.ssh_password" autocomplete="new-password">
                @if($form->store)<small>Deixe em branco para manter a senha cadastrada.</small>@endif
                @error('form.ssh_password') <small class="field__error">{{ $message }}</small> @enderror
            </label>
        </div>
    </section>

    <section class="panel panel--form">
        <div class="panel__header">
            <span class="eyebrow">Credenciais de integração</span>
            <h2>{{ $form->platform === 'woocommerce' ? 'Chaves do WooCommerce' : 'Tokens do Magento 2' }}</h2>
            <p>
                @if ($form->platform === 'woocommerce')
                    Mantenha o par de consumidor isolado para lojas que usam WooCommerce.
                @else
                    O Magento 2 exige o conjunto completo de tokens para operações de API.
                @endif
            </p>
        </div>

        <div class="credential-badge-row">
            <span class="credential-badge {{ $form->platform === 'woocommerce' ? 'is-active' : '' }}">WooCommerce</span>
            <span class="credential-badge {{ $form->platform === 'magento2' ? 'is-active' : '' }}">Magento 2</span>
        </div>

        <div class="field-grid">
            <label class="field">
                <span>Chave do consumidor</span>
                <input type="text" wire:model.blur="form.consumer_key" autocomplete="off" />
                @error('form.consumer_key') <small class="field__error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Segredo do consumidor</span>
                <input type="password" wire:model.blur="form.consumer_secret" autocomplete="new-password" />
                @error('form.consumer_secret') <small class="field__error">{{ $message }}</small> @enderror
            </label>

            @if ($form->platform === 'woocommerce')
                <label class="field">
                    <span>Usuário WordPress para mídias</span>
                    <input type="text" wire:model.blur="form.wp_username" autocomplete="off" />
                    @error('form.wp_username') <small class="field__error">{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Senha de aplicativo WordPress</span>
                    <input type="password" wire:model.blur="form.wp_application_password" autocomplete="new-password" />
                    @error('form.wp_application_password') <small class="field__error">{{ $message }}</small> @enderror
                    <small>Necessários apenas para enviar arquivos de imagem. Deixe vazios para manter os valores existentes.</small>
                </label>
            @endif

            @if ($form->platform === 'magento2')
                <label class="field">
                    <span>Token de acesso</span>
                    <input type="text" wire:model.blur="form.access_token" autocomplete="off" />
                    @error('form.access_token') <small class="field__error">{{ $message }}</small> @enderror
                </label>

                <label class="field">
                    <span>Segredo do token de acesso</span>
                    <input type="password" wire:model.blur="form.access_token_secret" autocomplete="new-password" />
                    @error('form.access_token_secret') <small class="field__error">{{ $message }}</small> @enderror
                </label>

                <label class="field">
                    <span>Usuário</span>
                    <input type="text" wire:model.blur="form.username" autocomplete="off" />
                    @error('form.username') <small class="field__error">{{ $message }}</small> @enderror
                </label>

                <label class="field">
                    <span>Senha</span>
                    <input type="password" wire:model.blur="form.password" autocomplete="new-password" />
                    @error('form.password') <small class="field__error">{{ $message }}</small> @enderror
                </label>
            @endif
        </div>

        <div class="panel__footer">
            <a href="{{ route('stores.index') }}" wire:navigate class="button button--ghost">Cancelar</a>
            <button type="submit" class="button">
                <span wire:loading.remove>{{ $submitLabel }}</span>
                <span wire:loading>Salvando...</span>
            </button>
        </div>
    </section>
</div>
