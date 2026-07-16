@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-2 rounded-md font-medium transition focus:outline-none focus-visible:ring-2 disabled:pointer-events-none disabled:opacity-60';

    $sizeClasses = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-sm',
    ][$size] ?? 'px-4 py-2 text-sm';

    $variantClasses = [
        'primary' => 'bg-brand-700 text-white hover:bg-brand-800 focus-visible:ring-brand-500/40',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus-visible:ring-brand-500/30',
        'ghost' => 'text-slate-700 hover:bg-slate-100 focus-visible:ring-brand-500/30',
        'danger' => 'bg-danger-700 text-white hover:brightness-95 focus-visible:ring-danger-700/40',
    ][$variant] ?? 'bg-brand-700 text-white hover:bg-brand-800 focus-visible:ring-brand-500/40';
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $baseClasses.' '.$sizeClasses.' '.$variantClasses]) }}>
    {{ $slot }}
</button>
