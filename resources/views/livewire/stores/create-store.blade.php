<div class="stack-xl">
    <section class="hero-card">
        <div>
            <span class="eyebrow">Create store</span>
            <h1>Register a new store connection.</h1>
            <p>Use a base store profile plus a platform-specific integration record so WooCommerce and Magento 2 can evolve independently.</p>
        </div>
        <div class="hero-card__meta">
            <span class="metric-chip">DDD actions</span>
            <span class="metric-chip">Livewire form object</span>
            <span class="metric-chip">Platform-aware credentials</span>
        </div>
    </section>

    <form wire:submit="save" class="stack-lg">
        @include('livewire.stores.partials.form-fields', [
            'heading' => 'Store identity',
            'description' => 'Capture the shared data once and delegate integration differences to dedicated tables.',
            'submitLabel' => 'Create store',
        ])
    </form>
</div>