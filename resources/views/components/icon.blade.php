@props(['name' => 'grid'])
<svg {{ $attributes->merge(['width'=>20,'height'=>20,'viewBox'=>'0 0 24 24','fill'=>'none','stroke'=>'currentColor','stroke-width'=>1.7,'stroke-linecap'=>'round','stroke-linejoin'=>'round','aria-hidden'=>'true']) }}>
@switch($name)
@case('chevron-down')<path d="m6 9 6 6 6-6"/>@break
@case('chevron-right')<path d="m9 6 6 6-6 6"/>@break
@case('trash')<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/>@break
@case('menu')<path d="M4 6h16M4 12h16M4 18h16"/>@break
@case('arrow-left')<path d="M19 12H5m7 7-7-7 7-7"/>@break
@case('sun')<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1 1m12 12 1 1M5 19l1-1M18 6l1-1"/>@break
@case('store')<path d="M3 10h18L19 3H5l-2 7Zm2 0v11h14V10M9 21v-7h6v7"/>@break
@case('check')<path d="m8 12 3 3 5-6"/><circle cx="12" cy="12" r="9"/>@break
@case('overview')<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>@break
@case('products')<path d="m12 3 9 5v8l-9 5-9-5V8l9-5Zm-9 5 9 5 9-5M12 13v8M7.5 5.5l9 5v4"/>@break
@case('orders')<path d="M8 5H5v16h14V5h-3"/><rect x="8" y="3" width="8" height="4" rx="1"/><path d="M9 12h6M9 16h6"/>@break
@case('api-logs')<path d="M14 3H5v18h14V8l-5-5Zm0 0v5h5M8 12h8M8 16h5"/>@break
@case('diagnostics')<path d="M3 12h4l3-8 4 16 3-8h4"/>@break
@case('tools')<path d="M14 6a5 5 0 0 0-6 6l-5 5a2.1 2.1 0 0 0 3 3l5-5a5 5 0 0 0 6-6l-3 3-3-3 3-3Z"/>@break
@case('settings')<path d="m9 3-.5 3-2 1-2.8-1-2 3.5L4 11v2l-2.3 1.5 2 3.5 2.8-1 2 1 .5 3h6l.5-3 2-1 2.8 1 2-3.5L20 13v-2l2.3-1.5-2-3.5-2.8 1-2-1-.5-3H9Z"/><circle cx="12" cy="12" r="3"/>@break
@default<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
@endswitch</svg>
