{{--
    Botón de acción reutilizable.

    Props:
    - variant: primary | secondary | ghost | danger | success
    - size: sm | md | lg
    - type: atributo type del <button> (por defecto "button"; nunca submit implícito)
    - href: si se indica y el botón no está deshabilitado, se renderiza un <a>
    - disabled: deshabilita la acción y evita la navegación
    - loading: muestra spinner, añade aria-busy y bloquea la interacción
    - icon: glifo decorativo (solo icono ⇒ el consumidor debe pasar aria-label)

    No emite clases Bootstrap ni valores hexadecimales.
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'disabled' => false,
    'loading' => false,
    'icon' => null,
])

@php
    $variants = [
        'primary' => 'bg-primary text-on-primary hover:bg-primary-hover',
        'secondary' => 'bg-surface text-ink border border-line hover:bg-surface-sunken',
        'ghost' => 'bg-transparent text-ink hover:bg-surface-sunken',
        'danger' => 'bg-danger text-on-primary hover:bg-danger/90',
        'success' => 'bg-success text-on-primary hover:bg-success/90',
    ];
    $sizes = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-6 py-3 text-base',
    ];
    $variantClasses = $variants[$variant] ?? $variants['primary'];
    $sizeClasses = $sizes[$size] ?? $sizes['md'];
    $isLink = $href !== null && $href !== '' && ! $disabled;
    $isDisabled = $disabled || $loading;
    $classes = [
        'inline-flex items-center justify-center gap-2 rounded-control font-medium transition-colors focus-ring',
        $variantClasses,
        $sizeClasses,
        $isDisabled ? 'opacity-60 cursor-not-allowed' : '',
        $isLink && $isDisabled ? 'pointer-events-none' : '',
    ];
@endphp

@if($isLink)
    <a
        href="{{ $href }}"
        {{ $attributes->class($classes) }}
        @if($isDisabled) aria-disabled="true" tabindex="-1" @endif
        @if($loading) aria-busy="true" @endif
    >
        @if($loading)
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
            </svg>
        @elseif($icon !== null && $icon !== '')
            <span class="inline-flex shrink-0" aria-hidden="true">{{ $icon }}</span>
        @endif
        {{ $slot }}
    </a>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->class($classes) }}
        @if($isDisabled) disabled aria-disabled="true" @endif
        @if($loading) aria-busy="true" @endif
    >
        @if($loading)
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
            </svg>
        @elseif($icon !== null && $icon !== '')
            <span class="inline-flex shrink-0" aria-hidden="true">{{ $icon }}</span>
        @endif
        {{ $slot }}
    </button>
@endif
