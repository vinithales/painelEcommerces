<?php

namespace App\Services\PlatformApi;

use App\Models\Store;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductFields
{
    public static function values(Store $store, array $data): array
    {
        if ($store->isWooCommerce() && isset($data['regular_price'])) {
            $data['price'] = $data['regular_price'];
        }

        return $data;
    }

    public static function rules(Store $store): array
    {
        $common = ['name' => 'string|min:1|max:255', 'price' => 'numeric|min:0', 'weight' => 'nullable|numeric|min:0', 'description' => 'nullable|string|max:100000', 'short_description' => 'nullable|string|max:20000'];

        return $common + ($store->isMagento2() ? ['status' => 'in:enabled,disabled'] : ['sku' => 'string|max:255', 'status' => 'in:publish,draft,pending,private', 'sale_price' => 'nullable|numeric|min:0', 'stock_quantity' => 'nullable|numeric', 'manage_stock' => 'boolean', 'stock_status' => 'in:instock,outofstock,onbackorder']);
    }

    public static function validate(Store $store, array $changes): array
    {
        if (! $changes) {
            throw ValidationException::withMessages(['changes' => 'Nenhuma alteração informada.']);
        }
        $rules = self::rules($store);
        if (array_diff(array_keys($changes), array_keys($rules))) {
            throw ValidationException::withMessages(['changes' => 'Campo não suportado para edição nesta plataforma.']);
        }

        return Validator::make($changes, $rules)->validate();
    }

    public static function payload(string $platform, array $changes): array
    {
        if ($platform === 'woocommerce') {
            if (array_key_exists('price', $changes)) {
                $changes['regular_price'] = (string) $changes['price'];
                unset($changes['price']);
            }
            foreach (['regular_price', 'sale_price', 'weight'] as $field) {
                if (array_key_exists($field, $changes)) {
                    $changes[$field] = (string) ($changes[$field] ?? '');
                }
            }

            return $changes;
        }
        if (isset($changes['status'])) {
            $changes['status'] = $changes['status'] === 'enabled' ? 1 : 2;
        }
        foreach (['description', 'short_description'] as $field) {
            if (array_key_exists($field, $changes)) {
                $changes['custom_attributes'][] = ['attribute_code' => $field, 'value' => $changes[$field]];
                unset($changes[$field]);
            }
        }

        return $changes;
    }
}
