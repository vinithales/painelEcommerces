<?php

namespace App\Livewire\Stores;

use App\Models\Store;
use App\Services\PlatformApi\ProductsApiService;
use App\Support\Redactor;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateStoreProduct extends Component
{
    use WithFileUploads;

    public Store $store;

    public array $form = [];

    public array $productAttributes = [];

    public array $imageUrls = [];

    public array $uploads = [];

    public array $imageMeta = [];

    public int $nextRowId = 0;

    public ?array $createdProduct = null;

    public array $imageErrors = [];

    public array $attributeSets = [];

    public ?string $attributeSetsError = null;

    public function mount(Store $store): void
    {
        Gate::authorize('manage');
        $this->store = $store;
        $this->newProduct();
        if ($store->isMagento2()) {
            try {
                $this->attributeSets = app(ProductsApiService::class)->productAttributeSets($store);
                if (count($this->attributeSets) === 1) {
                    $this->form['attribute_set_id'] = (string) $this->attributeSets[0]['attribute_set_id'];
                }
            } catch (\Throwable $e) {
                $this->attributeSetsError = Redactor::clean($e->getMessage());
            }
        }
    }

    public function newProduct(): void
    {
        $this->form = [
            'name' => '',
            'sku' => '',
            'price' => '',
            'status' => $this->store->isMagento2() ? 'disabled' : 'draft',
            'description' => '',
            'short_description' => '',
            'weight' => '',
            'manage_stock' => false,
            'stock_quantity' => '',
            'attribute_set_id' => count($this->attributeSets) === 1 ? (string) $this->attributeSets[0]['attribute_set_id'] : '',
            'visibility' => 4,
        ];
        $this->productAttributes = [];
        $this->imageUrls = [];
        $this->uploads = [];
        $this->imageMeta = [];
        $this->nextRowId = 0;
        $this->createdProduct = null;
        $this->imageErrors = [];
        $this->resetErrorBag();
    }

    public function addAttribute(): void
    {
        if (count($this->productAttributes) < 20) {
            $this->productAttributes[++$this->nextRowId] = ['code' => '', 'value' => ''];
        }
    }

    public function removeAttribute(int $index): void
    {
        unset($this->productAttributes[$index]);
    }

    public function addImageUrl(): void
    {
        if (count($this->imageUrls) < ($this->store->isMagento2() ? 5 : 10)) {
            $role = $this->imageUrls === [] && $this->uploads === [] ? 'principal' : 'galeria';
            $this->imageUrls[++$this->nextRowId] = ['src' => '', 'name' => '', 'alt' => '', 'label' => '', 'position' => '', 'disabled' => false, 'role' => $role];
        }
    }

    public function removeImageUrl(int $index): void
    {
        unset($this->imageUrls[$index]);
    }

    public function save(ProductsApiService $products): void
    {
        Gate::authorize('manage');
        if ($this->createdProduct !== null) {
            return;
        }

        $rules = [
            'form.name' => 'required|string|min:1|max:255',
            'form.sku' => 'required|string|min:1|max:255',
            'form.price' => 'required|numeric|min:0',
            'form.description' => 'nullable|string|max:100000',
            'form.short_description' => 'nullable|string|max:20000',
            'form.weight' => 'nullable|numeric|min:0',
            'productAttributes' => 'array|max:20',
            'productAttributes.*.code' => $this->store->isMagento2()
                ? ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/']
                : 'required|string|max:255',
            'productAttributes.*.value' => 'required|string|max:2000',
            'imageUrls' => 'array|max:'.($this->store->isMagento2() ? 5 : 10),
            'imageUrls.*.src' => 'required|url:http,https|max:2048',
            'imageUrls.*.name' => 'nullable|string|max:255',
            'imageUrls.*.alt' => 'nullable|string|max:2000',
            'uploads' => 'array|max:'.($this->store->isMagento2() ? 5 : 10),
            'uploads.*' => 'image|mimes:jpeg,png,webp|max:5120',
            'imageMeta.*.alt' => 'nullable|string|max:2000',
            'imageMeta.*.name' => 'nullable|string|max:255',
        ];

        if ($this->store->isMagento2()) {
            $rules += [
                'form.status' => 'required|in:enabled,disabled',
                'form.attribute_set_id' => 'required|integer|min:1',
                'form.visibility' => 'required|integer|in:1,2,3,4',
                'form.stock_quantity' => 'nullable|integer|min:0',
                'imageUrls.*.label' => 'nullable|string|max:255',
                'imageUrls.*.position' => 'nullable|integer|min:1|max:1000',
                'imageUrls.*.disabled' => 'boolean',
                'imageUrls.*.role' => 'required|in:principal,galeria',
                'imageMeta.*.label' => 'nullable|string|max:255',
                'imageMeta.*.position' => 'nullable|integer|min:1|max:1000',
                'imageMeta.*.disabled' => 'boolean',
                'imageMeta.*.role' => 'nullable|in:principal,galeria',
            ];
        } else {
            $rules += [
                'form.status' => 'required|in:publish,draft,pending,private',
                'form.manage_stock' => 'boolean',
                'form.stock_quantity' => 'nullable|integer|min:0',
            ];
        }

        $this->validate($rules);

        if (count($this->imageUrls) + count($this->uploads) > ($this->store->isMagento2() ? 5 : 10)) {
            $this->addError('images', 'Limite de imagens excedido para esta plataforma.');

            return;
        }

        if ($this->store->isWooCommerce()) {
            foreach ($this->productAttributes as $index => $attribute) {
                if (trim($attribute['value'], " ,\t\n\r\0\x0B") === '') {
                    $this->addError("productAttributes.{$index}.value", 'Informe ao menos uma opção.');

                    return;
                }
            }
        }

        $attributes = array_filter([
            'name' => trim($this->form['name']),
            'sku' => trim($this->form['sku']),
            'price' => $this->form['price'],
            'status' => $this->form['status'],
            'description' => $this->form['description'],
            'short_description' => $this->form['short_description'],
            'weight' => $this->form['weight'],
        ], static fn ($value) => $value !== null && $value !== '');

        $images = array_values($this->imageUrls);
        foreach ($this->uploads as $index => $file) {
            $images[] = [
                'filename' => $file->getClientOriginalName(),
                'name' => ($this->imageMeta[$index]['name'] ?? '') ?: $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'contents' => file_get_contents($file->getRealPath()),
                'alt' => $this->imageMeta[$index]['alt'] ?? '',
                'label' => $this->imageMeta[$index]['label'] ?? '',
                'position' => $this->imageMeta[$index]['position'] ?? '',
                'disabled' => $this->imageMeta[$index]['disabled'] ?? false,
                'role' => $this->imageMeta[$index]['role'] ?? ($index === 0 && $this->imageUrls === [] ? 'principal' : 'galeria'),
            ];
        }

        if ($this->store->isMagento2() && $images !== []) {
            $mainImages = count(array_filter($images, static fn (array $image): bool => $image['role'] === 'principal'));
            if ($mainImages !== 1) {
                $this->addError('images', 'Escolha exatamente uma imagem principal.');

                return;
            }
        }

        if ($this->store->isMagento2()) {
            $attributes['attribute_set_id'] = (int) $this->form['attribute_set_id'];
            $attributes['visibility'] = (int) $this->form['visibility'];
            if ($this->form['stock_quantity'] !== '') {
                $quantity = (int) $this->form['stock_quantity'];
                $attributes['extension_attributes'] = ['stock_item' => ['qty' => $quantity, 'is_in_stock' => $quantity > 0]];
            }
            $attributes['custom_attributes'] = array_values(array_map(static fn ($row) => [
                'attribute_code' => trim($row['code']),
                'value' => trim($row['value']),
            ], $this->productAttributes));
        } else {
            $attributes['manage_stock'] = (bool) $this->form['manage_stock'];
            if ($attributes['manage_stock'] && $this->form['stock_quantity'] !== '') {
                $attributes['stock_quantity'] = (int) $this->form['stock_quantity'];
            }
            $attributes['attributes'] = array_values(array_map(static fn ($row) => [
                'name' => trim($row['code']),
                'visible' => true,
                'variation' => false,
                'options' => array_values(array_filter(array_map('trim', explode(',', $row['value'])), static fn ($value) => $value !== '')),
            ], $this->productAttributes));
        }

        try {
            $result = $products->create($this->store, $attributes, $images);
            $this->createdProduct = [
                'id' => $result['data']['id'] ?? null,
                'sku' => $result['data']['sku'] ?? $attributes['sku'],
                'name' => $result['data']['name'] ?? $attributes['name'],
            ];
            $this->imageErrors = $result['image_errors'] ?? [];
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->addError('api', Redactor::clean($e->getMessage()));
        }
    }

    public function render()
    {
        return view('livewire.stores.create-store-product')
            ->layout('layouts.app', ['store' => $this->store, 'title' => 'Novo produto']);
    }
}
