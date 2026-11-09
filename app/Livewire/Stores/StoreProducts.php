<?php

namespace App\Livewire\Stores;

use Livewire\Attributes\Title;

#[Title('Produtos')]
class StoreProducts extends ResourceTable
{
    public array $categories = [];

    public ?string $categoryError = null;

    public function loadCategories(): void
    {
        $this->categoryError = null;
        try {
            $this->categories = $this->service()->categories($this->store);
        } catch (\Throwable $e) {
            $this->categoryError = 'Não foi possível carregar as categorias. Tente novamente.';
        }
    }

    protected function resource(): string
    {
        return 'product';
    }
}
