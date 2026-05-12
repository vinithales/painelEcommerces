<?php

namespace App\Livewire\Stores;

use App\Actions\Stores\CreateStoreAction;
use App\Livewire\Forms\StoreForm;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Create store')]
class CreateStore extends Component
{
    public StoreForm $form;

    public function save(CreateStoreAction $action)
    {
        $this->form->validate();

        $action->execute($this->form->storeData(), $this->form->integrationData());

        session()->flash('status', 'Store created successfully.');

        return $this->redirectRoute('stores.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.stores.create-store');
    }
}