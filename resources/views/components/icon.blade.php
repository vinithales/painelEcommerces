@props(['name' => 'grid'])
<svg {{ $attributes->merge(['width'=>20,'height'=>20,'viewBox'=>'0 0 24 24','fill'=>'none','stroke'=>'currentColor','stroke-width'=>1.7,'stroke-linecap'=>'round','stroke-linejoin'=>'round','aria-hidden'=>'true']) }}>
@switch($name)
@case('menu')<path d="M4 6h16M4 12h16M4 18h16"/>@break
@case('sun')<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1 1m12 12 1 1M5 19l1-1M18 6l1-1"/>@break
@case('store')<path d="M3 10h18L19 3H5l-2 7Zm2 0v11h14V10M9 21v-7h6v7"/>@break
@case('check')<path d="m8 12 3 3 5-6"/><circle cx="12" cy="12" r="9"/>@break
@default<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
@endswitch</svg>
