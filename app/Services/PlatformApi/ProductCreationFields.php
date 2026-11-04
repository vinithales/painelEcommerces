<?php

namespace App\Services\PlatformApi;

use App\Models\Store;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductCreationFields
{
    public static function validate(Store $store, array $attributes, array $images): array
    {
        $rules = [
            'name' => 'required|string|min:1|max:255',
            'sku' => 'required|string|min:1|max:255',
            'price' => 'required|numeric|min:0',
            'status' => 'required|'.($store->isMagento2() ? 'in:enabled,disabled' : 'in:publish,draft,pending,private'),
            'description' => 'nullable|string|max:100000',
            'short_description' => 'nullable|string|max:20000',
            'weight' => 'nullable|numeric|min:0',
        ];

        if ($store->isMagento2()) {
            $rules += [
                'attribute_set_id' => 'required|integer|min:1',
                'visibility' => 'required|integer|in:1,2,3,4',
                'extension_attributes.stock_item.qty' => 'nullable|integer|min:0',
                'extension_attributes.stock_item.is_in_stock' => 'boolean',
                'custom_attributes' => 'array|max:20',
                'custom_attributes.*.attribute_code' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
                'custom_attributes.*.value' => 'required|string|max:2000',
            ];
        } else {
            $rules += [
                'manage_stock' => 'boolean',
                'stock_quantity' => 'nullable|integer|min:0',
                'attributes' => 'array|max:20',
                'attributes.*.name' => 'required|string|max:255',
                'attributes.*.visible' => 'boolean',
                'attributes.*.variation' => 'boolean',
                'attributes.*.options' => 'required|array|min:1|max:30',
                'attributes.*.options.*' => 'required|string|max:255',
            ];
        }

        $validated = Validator::make($attributes, $rules)->validate();
        Validator::make(['images' => $images], ['images' => 'array|max:'.($store->isMagento2() ? 5 : 10)])->validate();
        $validatedImages = [];
        foreach ($images as $index => $image) {
            $rules = isset($image['src'])
                ? ['src' => 'required|url:http,https|max:2048', 'name' => 'nullable|string|max:255', 'alt' => 'nullable|string|max:2000']
                : ['filename' => 'required|string|max:255', 'name' => 'required|string|max:255', 'alt' => 'nullable|string|max:2000', 'mime' => 'required|in:image/jpeg,image/png,image/webp', 'contents' => 'required|string'];
            if ($store->isMagento2()) {
                $rules += [
                    'label' => 'nullable|string|max:255',
                    'position' => 'nullable|integer|min:1|max:1000',
                    'disabled' => 'boolean',
                    'role' => 'nullable|in:principal,galeria',
                ];
            }
            $validatedImages[] = Validator::make($image, $rules)->validate();
            if (isset($image['contents']) && strlen($image['contents']) > 5242880) {
                throw ValidationException::withMessages(["images.{$index}" => 'Cada imagem pode ter no máximo 5 MB.']);
            }
        }

        return [$validated, $validatedImages];
    }
}
