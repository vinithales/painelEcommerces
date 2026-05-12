<div class="stack-xl">
    <section class="hero-card">
        <div>
            <span class="eyebrow">Edit store</span>
            <h1>Adjust store metadata and credentials safely.</h1>
            <p>The update flow replaces the platform integration atomically so switching between WooCommerce and Magento 2 does not leave stale records behind.</p>
        </div>
        <div class="hero-card__meta">
            <span class="metric-chip">Transactional update</span>
            <span class="metric-chip">Single source of truth</span>
            <span class="metric-chip">Encrypted-ready design</span>
        </div>
    </section>

    <form wire:submit="save" class="stack-lg">
        @include('livewire.stores.partials.form-fields', [
            'heading' => 'Store identity',
            'description' => 'Keep the shared profile aligned with the correct integration model.',
            'submitLabel' => 'Save changes',
        ])
    </form>
</div>