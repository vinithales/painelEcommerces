<div class="form-grid">
    <section class="panel panel--form">
        <div class="panel__header">
            <span class="eyebrow">Core profile</span>
            <h2>{{ $heading }}</h2>
            <p>{{ $description }}</p>
        </div>

        <div class="field-grid">
            <label class="field">
                <span>Name</span>
                <input type="text" wire:model.blur="form.name" placeholder="Carli Fitness" />
                @error('form.name') <small class="field__error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Platform</span>
                <select wire:model.live="form.platform">
                    <option value="woocommerce">WooCommerce</option>
                    <option value="magento2">Magento 2</option>
                </select>
                @error('form.platform') <small class="field__error">{{ $message }}</small> @enderror
            </label>

            <label class="field field--full">
                <span>Store URL</span>
                <input type="url" wire:model.blur="form.store_url" placeholder="https://store.example.com" />
                @error('form.store_url') <small class="field__error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Panel prefix</span>
                <input type="text" wire:model.blur="form.panel_prefix" placeholder="carlifitness" />
                @error('form.panel_prefix') <small class="field__error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Payment gateway</span>
                <input type="text" wire:model.blur="form.payment_gateway" placeholder="Pagar.me" />
                @error('form.payment_gateway') <small class="field__error">{{ $message }}</small> @enderror
            </label>

            <label class="field field--full">
                <span>Shipping gateway</span>
                <input type="text" wire:model.blur="form.shipping_gateway" placeholder="Correios, Melhor Envio, Frenet" />
                @error('form.shipping_gateway') <small class="field__error">{{ $message }}</small> @enderror
            </label>
        </div>
    </section>

    <section class="panel panel--form">
        <div class="panel__header">
            <span class="eyebrow">Integration credentials</span>
            <h2>{{ $form->platform === 'woocommerce' ? 'WooCommerce keys' : 'Magento 2 tokens' }}</h2>
            <p>
                @if ($form->platform === 'woocommerce')
                    Keep the consumer pair isolated for stores running WooCommerce.
                @else
                    Magento 2 requires the full token quartet for API operations.
                @endif
            </p>
        </div>

        <div class="credential-badge-row">
            <span class="credential-badge {{ $form->platform === 'woocommerce' ? 'is-active' : '' }}">WooCommerce</span>
            <span class="credential-badge {{ $form->platform === 'magento2' ? 'is-active' : '' }}">Magento 2</span>
        </div>

        <div class="field-grid">
            <label class="field">
                <span>Consumer key</span>
                <input type="text" wire:model.blur="form.consumer_key" autocomplete="off" />
                @error('form.consumer_key') <small class="field__error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Consumer secret</span>
                <input type="password" wire:model.blur="form.consumer_secret" autocomplete="new-password" />
                @error('form.consumer_secret') <small class="field__error">{{ $message }}</small> @enderror
            </label>

            @if ($form->platform === 'magento2')
                <label class="field">
                    <span>Access token</span>
                    <input type="text" wire:model.blur="form.access_token" autocomplete="off" />
                    @error('form.access_token') <small class="field__error">{{ $message }}</small> @enderror
                </label>

                <label class="field">
                    <span>Access token secret</span>
                    <input type="password" wire:model.blur="form.access_token_secret" autocomplete="new-password" />
                    @error('form.access_token_secret') <small class="field__error">{{ $message }}</small> @enderror
                </label>

                <label class="field">
                    <span>Username</span>
                    <input type="text" wire:model.blur="form.username" autocomplete="off" />
                    @error('form.username') <small class="field__error">{{ $message }}</small> @enderror
                </label>

                <label class="field">
                    <span>Password</span>
                    <input type="password" wire:model.blur="form.password" autocomplete="new-password" />
                    @error('form.password') <small class="field__error">{{ $message }}</small> @enderror
                </label>
            @endif
        </div>

        <div class="panel__footer">
            <a href="{{ route('stores.index') }}" wire:navigate class="button button--ghost">Cancel</a>
            <button type="submit" class="button">
                <span wire:loading.remove>{{ $submitLabel }}</span>
                <span wire:loading>Saving...</span>
            </button>
        </div>
    </section>
</div>