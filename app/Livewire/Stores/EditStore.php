<?php

namespace App\Livewire\Stores;

use App\Actions\Stores\UpdateStoreAction;
use App\Livewire\Forms\StoreForm;
use App\Models\Store;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit store')]
class EditStore extends Component
{
    public StoreForm $form;

    public Store $store;

    public function mount(Store $store): void
    {
        $this->store = $store->load(['wooCommerceIntegration', 'magento2Integration']);
        $this->form->setStore($this->store);
    }

    public function save(UpdateStoreAction $action)
    {
        $this->form->validate();

        $this->store = $action->execute(
            $this->store,
            $this->form->storeData(),
            $this->form->integrationData(),
        );

        $this->form->setStore($this->store);

        session()->flash('status', 'Store updated successfully.');

        return $this->redirectRoute('stores.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.stores.edit-store');
    }
}