<div class="stack-xl">
    <section class="hero-card">
        <div>
            <span class="eyebrow">Editar loja</span>
            <h1>Ajuste os dados e as credenciais da loja.</h1>
            <p>A atualização substitui a integração da plataforma de forma atômica, sem deixar registros antigos ao alternar entre WooCommerce e Magento 2.</p>
        </div>
        <div class="hero-card__meta">
            <span class="metric-chip">Atualização transacional</span>
            <span class="metric-chip">Fonte única da verdade</span>
            <span class="metric-chip">Pronto para criptografia</span>
        </div>
    </section>

    <form wire:submit="save" class="stack-lg">
        @include('livewire.stores.partials.form-fields', [
            'heading' => 'Identificação da loja',
            'description' => 'Mantenha o perfil alinhado ao modelo correto de integração.',
            'submitLabel' => 'Salvar alterações',
        ])
    </form>
</div>