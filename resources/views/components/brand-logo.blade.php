@props(['symbol' => false])
<span {{ $attributes->class(['brand-logo', 'brand-logo--symbol' => $symbol]) }} role="img" aria-label="BeaconHub">
    <img class="brand-logo__light" src="{{ asset('images/brand/beaconhub'.($symbol ? '-symbol' : '').'-light.png') }}" alt="" aria-hidden="true" decoding="async">
    <img class="brand-logo__dark" src="{{ asset('images/brand/beaconhub'.($symbol ? '-symbol' : '').'-dark.png') }}" alt="" aria-hidden="true" decoding="async">
</span>
