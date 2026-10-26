@props(['type' => 'info'])

@php
    $styles = [
        'success' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'error' => 'bg-red-50 text-red-800 border-red-200',
        'info' => 'bg-sky-50 text-sky-800 border-sky-200',
        'warning' => 'bg-amber-50 text-amber-800 border-amber-200',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border px-4 py-3 text-sm '.$styles[$type]]) }} role="alert">
    {{ $slot }}
</div>
