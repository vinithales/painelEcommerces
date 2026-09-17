<?php
namespace App\Livewire\Stores;
use Livewire\Attributes\Title;
#[Title('Pedidos')]
class StoreOrders extends ResourceTable {protected function resource(): string {return 'order';}}