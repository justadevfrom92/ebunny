{{-- A simple line icon for a product tile: @include('frontend.shop._icon', ['icon' => 'thermo']) --}}
@php
    $icons = [
        'thermo' => '<rect x="14" y="10" width="36" height="44" rx="10"/><circle cx="32" cy="32" r="9"/><path d="M32 23v-4"/>',
        'sensor' => '<rect x="20" y="16" width="24" height="32" rx="6"/><circle cx="32" cy="28" r="4"/><path d="M26 40h12"/>',
        'ev' => '<rect x="16" y="10" width="24" height="44" rx="4"/><path d="M28 20l-5 10h10l-5 10"/><path d="M40 26h6v16a4 4 0 0 1-8 0"/>',
        'cable' => '<path d="M14 40c10 0 10-16 20-16s10 16 20 16"/><circle cx="14" cy="40" r="4"/><circle cx="54" cy="40" r="4"/>',
        'tools' => '<path d="M20 44l16-16"/><path d="M36 28a8 8 0 1 0 8-12l-5 5-4-1-1-4 5-5"/><path d="M16 48l4-4"/>',
        'bulb' => '<path d="M24 38a12 12 0 1 1 16 0c-2 2-3 4-3 7H27c0-3-1-5-3-7z"/><path d="M27 50h10M29 55h6"/>',
        'sun' => '<circle cx="32" cy="32" r="9"/><path d="M32 12v6M32 46v6M12 32h6M46 32h6M18 18l4 4M42 42l4 4M18 46l4-4M42 22l4-4"/>',
        'plug' => '<path d="M24 12v10M40 12v10"/><rect x="18" y="22" width="28" height="14" rx="4"/><path d="M32 36v8a6 6 0 0 0 6 6h6"/>',
        'meter' => '<circle cx="32" cy="34" r="18"/><path d="M32 34l8-8"/><path d="M20 34h2M42 34h2M32 22v2"/>',
        'shield' => '<path d="M32 10l18 6v14c0 12-8 20-18 24-10-4-18-12-18-24V16z"/><path d="M24 32l6 6 10-12"/>',
        'battery' => '<rect x="12" y="20" width="36" height="24" rx="4"/><path d="M48 28h4v8h-4"/><path d="M30 24l-4 8h8l-4 8"/>',
        'house' => '<path d="M12 30l20-16 20 16"/><path d="M18 26v24h28V26"/><path d="M28 50V38h8v12"/>',
        'cap' => '<path d="M14 40c0-12 8-20 18-20s18 8 18 20z"/><path d="M14 40h40"/>',
        'cup' => '<path d="M22 16h20l-3 38H25z"/><path d="M20 16h24"/>',
        'bag' => '<rect x="14" y="24" width="36" height="28" rx="3"/><path d="M24 24v-4a8 8 0 0 1 16 0v4"/>',
    ];
@endphp
<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$icon] ?? $icons['plug'] !!}</svg>
