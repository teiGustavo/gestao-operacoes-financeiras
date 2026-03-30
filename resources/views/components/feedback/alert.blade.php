@props([
    'message' => null,
    'variant' => 'success',
    'class' => '',
])

@php
    $variants = [
        'success' => 'bg-emerald-50 text-emerald-800',
        'error' => 'bg-red-50 text-red-700',
        'info' => 'border border-blue-200 bg-blue-50 text-blue-900',
    ];

    $classes = $variants[$variant] ?? $variants['info'];
    $hasSlotContent = trim((string) $slot) !== '';
@endphp

@if (filled($message) || $hasSlotContent)
    <div {{ $attributes->merge(['class' => trim('mt-4 rounded-md px-3 py-2 text-sm '.$classes.' '.$class)]) }}>
        @if (filled($message))
            {{ $message }}
        @else
            {{ $slot }}
        @endif
    </div>
@endif

