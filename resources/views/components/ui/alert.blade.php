{{--
    Aviso contextual.

    Props:
    - variant: info | success | warning | danger
    - title: título opcional
    - dismissible: muestra un botón de cierre (Alpine)

    danger/warning usan role="alert"; el resto role="status" + aria-live="polite".
--}}
@props([
    'variant' => 'info',
    'title' => null,
    'dismissible' => false,
])

@php
    $variants = [
        'info' => 'bg-info-soft text-info-ink border-info',
        'success' => 'bg-success-soft text-success-ink border-success',
        'warning' => 'bg-warning-soft text-warning-ink border-warning',
        'danger' => 'bg-danger-soft text-danger-ink border-danger',
    ];
    $variantClasses = $variants[$variant] ?? $variants['info'];
    $isUrgent = in_array($variant, ['danger', 'warning'], true);
    $hasTitle = $title !== null && $title !== '';
@endphp

<div
    @if($dismissible) x-data="{ show: true }" x-show="show" @endif
    role="{{ $isUrgent ? 'alert' : 'status' }}"
    @if(! $isUrgent) aria-live="polite" @endif
    {{ $attributes->class(['flex items-start gap-3 rounded-control border px-4 py-3 text-sm', $variantClasses]) }}
>
    <div class="min-w-0 flex-1">
        @if($hasTitle)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div class="{{ $hasTitle ? 'mt-1' : '' }}">{{ $slot }}</div>
    </div>

    @if($dismissible)
        <button
            type="button"
            @click="show = false"
            aria-label="Cerrar"
            class="shrink-0 rounded-control p-1 opacity-70 transition-opacity hover:opacity-100 focus-ring"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"></path>
            </svg>
        </button>
    @endif
</div>
