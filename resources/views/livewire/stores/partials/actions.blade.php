<details class="store-actions" name="store-actions">
    <summary class="store-actions__toggle"><x-icon name="menu"/><span>Ações da loja</span><x-icon name="chevron-down" class="store-actions__chevron"/></summary>
    <div class="store-actions__panel">
        <p class="store-actions__heading">Gerenciar loja</p>
        <div class="store-actions__grid">
            @foreach(['overview'=>'Visão geral','products'=>'Produtos','orders'=>'Pedidos','api-logs'=>'Logs de API','diagnostics'=>'Diagnóstico','tools'=>'Ferramentas'] as $route=>$label)
                <a wire:navigate class="store-actions__item" href="{{ route('stores.'.$route,$store) }}"><x-icon :name="$route"/><span>{{ $label }}</span></a>
            @endforeach
            <a wire:navigate class="store-actions__item" href="{{ route('stores.checks',['storeId'=>$store->id]) }}"><x-icon name="check"/><span>Verificação</span></a>
            @can('configure')<a wire:navigate class="store-actions__item" href="{{ route('stores.edit',$store) }}"><x-icon name="settings"/><span>Configurações</span></a>@endcan
        </div>
        @can('configure')
            <div class="store-actions__footer"><button type="button" class="store-actions__item store-actions__item--danger" wire:click="delete({{ $store->id }})" wire:confirm="Excluir a loja e seu histórico local?"><x-icon name="trash"/><span>Excluir loja</span></button></div>
        @endcan
    </div>
</details>
