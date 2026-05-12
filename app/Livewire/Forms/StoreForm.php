<?php

namespace App\Livewire\Forms;

use App\Models\Store;
use Illuminate\Validation\Rule;
use Livewire\Form;

class StoreForm extends Form
{
    public ?Store $store = null;

    public string $name = '';

    public string $platform = Store::PLATFORM_WOOCOMMERCE;

    public string $store_url = '';

    public string $panel_prefix = '';

    public string $payment_gateway = '';

    public string $shipping_gateway = '';

    public string $consumer_key = '';

    public string $consumer_secret = '';

    public string $access_token = '';

    public string $access_token_secret = '';

    public string $username = '';

    public string $password = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in([Store::PLATFORM_WOOCOMMERCE, Store::PLATFORM_MAGENTO2])],
            'store_url' => ['required', 'url', 'max:255', Rule::unique('stores', 'store_url')->ignore($this->store?->id)],
            'panel_prefix' => ['nullable', 'string', 'max:255'],
            'payment_gateway' => ['nullable', 'string', 'max:255'],
            'shipping_gateway' => ['nullable', 'string', 'max:255'],
            'consumer_key' => ['required', 'string', 'max:255'],
            'consumer_secret' => ['required', 'string', 'max:255'],
            'access_token' => ['nullable', 'required_if:platform,' . Store::PLATFORM_MAGENTO2, 'string', 'max:255'],
            'access_token_secret' => ['nullable', 'required_if:platform,' . Store::PLATFORM_MAGENTO2, 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'store_url' => 'store URL',
            'panel_prefix' => 'panel prefix',
            'payment_gateway' => 'payment gateway',
            'shipping_gateway' => 'shipping gateway',
            'consumer_key' => 'consumer key',
            'consumer_secret' => 'consumer secret',
            'access_token' => 'access token',
            'access_token_secret' => 'access token secret',
            'username' => 'username',
            'password' => 'password',
        ];
    }

    public function setStore(Store $store): void
    {
        $this->store = $store;
        $integration = $store->integration();

        $this->fill([
            'name' => $store->name,
            'platform' => $store->platform,
            'store_url' => $store->store_url,
            'panel_prefix' => $store->panel_prefix ?? '',
            'payment_gateway' => $store->payment_gateway ?? '',
            'shipping_gateway' => $store->shipping_gateway ?? '',
        ]);

        $this->consumer_key = $integration?->consumer_key ?? '';
        $this->consumer_secret = $integration?->consumer_secret ?? '';
        $this->access_token = $store->usesAccessToken() ? ($integration?->access_token ?? '') : '';
        $this->access_token_secret = $store->usesAccessToken() ? ($integration?->access_token_secret ?? '') : '';
        $this->username = $store->isMagento2() ? ($integration?->username ?? '') : '';
        $this->password = $store->isMagento2() ? ($integration?->password ?? '') : '';
    }

    public function storeData(): array
    {
        return [
            'name' => $this->name,
            'platform' => $this->platform,
            'store_url' => $this->store_url,
            'panel_prefix' => $this->nullIfBlank($this->panel_prefix),
            'payment_gateway' => $this->nullIfBlank($this->payment_gateway),
            'shipping_gateway' => $this->nullIfBlank($this->shipping_gateway),
        ];
    }

    public function integrationData(): array
    {
        if (! Store::platformUsesAccessToken($this->platform)) {
            return [
                'consumer_key' => $this->consumer_key,
                'consumer_secret' => $this->consumer_secret,
            ];
        }

        return [
            'consumer_key' => $this->consumer_key,
            'consumer_secret' => $this->consumer_secret,
            'access_token' => $this->access_token,
            'access_token_secret' => $this->access_token_secret,
            'username' => $this->nullIfBlank($this->username),
            'password' => $this->nullIfBlank($this->password),
        ];
    }

    public function resetState(): void
    {
        $this->reset();
        $this->platform = Store::PLATFORM_WOOCOMMERCE;
        $this->store = null;
    }

    private function nullIfBlank(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}