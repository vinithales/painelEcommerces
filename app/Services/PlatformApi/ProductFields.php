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
        if (array_key_exists('gallery', $data)) {
            $data['images'] = $data['gallery'];
        }

        return $data;
    }

    public static function rules(Store $store): array
    {
        $common = ['name' => 'string|min:1|max:255', 'price' => 'numeric|min:0', 'weight' => 'nullable|numeric|min:0', 'description' => 'nullable|string|max:100000', 'short_description' => 'nullable|string|max:20000', 'images' => 'array|min:1|max:100'];

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

        $validated = Validator::make($changes, $rules + self::imageRules($store))->validate();
        foreach ($validated['images'] ?? [] as $index => $image) {
            if ($store->isWooCommerce() && empty($image['id']) && empty($image['src'])) {
                throw ValidationException::withMessages(["images.{$index}.src" => 'A imagem precisa de um ID ou URL válida.']);
            }
        }

        return $validated;
    }

    public static function payload(string $platform, array $changes): array
    {
        if (isset($changes['images'])) {
            usort($changes['images'], fn (array $left, array $right) => ($left['position'] ?? 0) <=> ($right['position'] ?? 0));
            if ($platform === 'woocommerce') {
                $changes['images'] = array_map(function (array $image): array {
                    $payload = [
                        'name' => (string) ($image['name'] ?? ''),
                        'alt' => (string) ($image['alt'] ?? ''),
                    ];
                    if (! empty($image['id'])) {
                        $payload['id'] = (int) $image['id'];
                    } elseif (! empty($image['src'])) {
                        $payload['src'] = $image['src'];
                    }

                    return $payload;
                }, $changes['images']);
            } else {
                $changes['media_gallery_entries'] = array_map(fn (array $image): array => [
                    'id' => (int) $image['id'],
                    'media_type' => $image['media_type'],
                    'label' => (string) ($image['alt'] ?? ''),
                    'position' => (int) $image['position'],
                    'disabled' => (bool) $image['disabled'],
                    'types' => array_values($image['types'] ?? []),
                    'file' => $image['file'],
                ], $changes['images']);
                unset($changes['images']);
            }
        }

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

    private static function imageRules(Store $store): array
    {
        $common = [
            'images.*.id' => 'nullable|integer|min:1',
            'images.*.src' => 'nullable|url:http,https|max:2048',
            'images.*.alt' => 'nullable|string|max:2000',
            'images.*.name' => 'nullable|string|max:255',
            'images.*.position' => 'required|integer|min:0|max:999',
            'images.*.date_created' => 'nullable|string|max:40',
        ];

        if ($store->isWooCommerce()) {
            return $common;
        }

        $common['images.*.id'] = 'required|integer|min:1';

        return $common + [
            'images.*.disabled' => 'required|boolean',
            'images.*.types' => 'array|max:3',
            'images.*.types.*' => ['string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'images.*.media_type' => 'required|string|in:image',
            'images.*.file' => 'required|string|max:1024',
        ];
    }
}
