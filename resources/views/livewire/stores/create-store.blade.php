<div class="stack-xl">
    <section class="hero-card">
        <div>
            <span class="eyebrow">Criar loja</span>
            <h1>Cadastre uma nova conexão de loja.</h1>
            <p>Use um perfil de loja e um registro de integração específico para manter WooCommerce e Magento 2 independentes.</p>
        </div>
        <div class="hero-card__meta">
            <span class="metric-chip">Ações DDD</span>
            <span class="metric-chip">Formulário Livewire</span>
            <span class="metric-chip">Credenciais por plataforma</span>
        </div>
    </section>

    <form wire:submit="save" class="stack-lg">
        @include('livewire.stores.partials.form-fields', [
            'heading' => 'Identificação da loja',
            'description' => 'Informe os dados comuns uma vez e mantenha as diferenças de integração nas tabelas específicas.',
            'submitLabel' => 'Criar loja',
        ])
    </form>
</div>