<?php

namespace App\Livewire\Stores;

use Livewire\Attributes\Title;

#[Title('Produtos')]
class StoreProducts extends ResourceTable
{
    protected function resource(): string
    {
        return 'product';
    }
}
