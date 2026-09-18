<section class="store-dashboard" aria-labelledby="store-dashboard-title">
    <div class="store-dashboard__heading">
        <div>
            <h3 id="store-dashboard-title">Visão geral</h3>
            <p>Suas lojas, em números.</p>
        </div>
        <span class="store-dashboard__note">Todas as lojas cadastradas</span>
    </div>
    <div class="store-dashboard__grid">
        <article class="store-metric store-metric--total">
            <div class="store-metric__top">
                <span>Total de lojas</span>
                <span class="store-metric__icon"><x-icon name="store"/></span>
            </div>
            <strong class="store-metric__value">{{ number_format($storeCount, 0, ',', '.') }}</strong>
            <p>Uma visão completa da sua operação</p>
            <button type="button" wire:click="$set('platform', 'all')" class="store-metric__action">Todas as plataformas <span aria-hidden="true">→</span></button>
        </article>
        @foreach([
            'woocommerce' => ['label' => 'WooCommerce', 'count' => $wooCommerceCount, 'mark' => 'W'],
            'magento2' => ['label' => 'Magento 2', 'count' => $magento2Count, 'mark' => 'M'],
        ] as $key => $metric)
            @php($percentage = $storeCount > 0 ? round($metric['count'] / $storeCount * 100, 1) : 0)
            <article class="store-metric store-metric--{{ $key }}">
                <div class="store-metric__top">
                    <span>{{ $metric['label'] }}</span>
                    <span class="store-metric__icon" aria-hidden="true">{{ $metric['mark'] }}</span>
                </div>
                <strong class="store-metric__value">{{ number_format($metric['count'], 0, ',', '.') }}</strong>
                <p>{{ number_format($percentage, 1, ',', '.') }}% do total de lojas</p>
                <div class="store-metric__track" aria-hidden="true"><span style="width: {{ $percentage }}%"></span></div>
                <button type="button" wire:click="$set('platform', '{{ $key }}')" class="store-metric__action">Filtrar {{ $metric['label'] }} <span aria-hidden="true">→</span></button>
            </article>
        @endforeach
    </div>
</section>
