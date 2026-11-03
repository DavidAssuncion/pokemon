{{--
    Etiqueta de estado o tipo.

    Props:
    - variant: neutral | primary | success | warning | danger | info
    - size: sm | md | lg
    - type: si se indica, delega en <x-tipo-badge> con el tipo resuelto

    El texto es siempre visible: el color nunca es el único portador de información.
--}}
@props([
    'variant' => 'neutral',
    'size' => 'sm',
    'type' => null,
])

@php
    $variants = [
        'neutral' => 'bg-surface-sunken text-ink-muted border-line',
        'primary' => 'bg-primary-soft text-primary-ink border-primary/30',
        'success' => 'bg-success-soft text-success-ink border-success/30',
        'warning' => 'bg-warning-soft text-warning-ink border-warning/30',
        'danger' => 'bg-danger-soft text-danger-ink border-danger/30',
        'info' => 'bg-info-soft text-info-ink border-info/30',
    ];
    $sizes = [
        'sm' => 'px-2 py-0.5 text-xs',
        'md' => 'px-2.5 py-1 text-xs',
        'lg' => 'px-3 py-1 text-sm',
    ];
    $variantClasses = $variants[$variant] ?? $variants['neutral'];
    $sizeClasses = $sizes[$size] ?? $sizes['sm'];
    $label = trim((string) $slot);
    $hasType = $type !== null && $type !== '';
@endphp

@if($hasType)
    <x-tipo-badge
        :tipo="$type"
        :label="$label !== '' ? $label : null"
        :size="$size"
        {{ $attributes }}
    />
@else
    <span {{ $attributes->class(['inline-flex items-center rounded-full border font-medium', $variantClasses, $sizeClasses]) }}>
        {{ $slot }}
    </span>
@endif
