{{--
    Modal basado en <dialog> nativo + Alpine.

    Props:
    - name: identificador para los eventos window (open-{name} / close-{name})
    - title: título obligatorio; se asocia con aria-labelledby
    - size: sm | md | lg | xl | full
    - open: estado inicial

    Apertura/cierre externos:
    - window.dispatchEvent(new CustomEvent('open-{name}'))
    - window.dispatchEvent(new CustomEvent('close-{name}'))

    Slots:
    - default: contenido
    - footer: pie opcional con acciones
--}}
@props([
    'name',
    'title',
    'size' => 'md',
    'open' => false,
])

@php
    $sizes = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        'full' => 'max-w-full',
    ];
    $sizeClasses = $sizes[$size] ?? $sizes['md'];
    $widthClasses = $size === 'full' ? 'w-full' : 'w-11/12 ' . $sizeClasses;
    $titleId = 'modal-title-' . \Illuminate\Support\Str::slug($name);
@endphp

<dialog
    x-data="{ open: {{ $open ? 'true' : 'false' }} }"
    x-ref="dialog"
    x-effect="open
        ? ($refs.dialog.open || $refs.dialog.showModal())
        : ($refs.dialog.open && $refs.dialog.close())"
    @open-{{ $name }}.window="open = true"
    @close-{{ $name }}.window="open = false"
    @close="open = false"
    @click="if ($event.target === $refs.dialog) open = false"
    aria-modal="true"
    aria-labelledby="{{ $titleId }}"
    {{ $attributes->class(['ui-modal m-auto rounded-panel border border-line bg-surface-raised p-0 text-ink shadow-modal', $widthClasses]) }}
>
    <div class="flex flex-col">
        <header class="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
            <h2 id="{{ $titleId }}" class="text-lg font-semibold text-ink">{{ $title }}</h2>
            <button
                type="button"
                @click="open = false"
                aria-label="Cerrar"
                class="shrink-0 rounded-control p-1 text-ink-muted transition-colors hover:text-ink focus-ring"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"></path>
                </svg>
            </button>
        </header>

        <div class="px-6 py-4">
            {{ $slot }}
        </div>

        @if(isset($footer))
            <footer class="flex flex-wrap justify-end gap-2 border-t border-line px-6 py-4">
                {{ $footer }}
            </footer>
        @endif
    </div>
</dialog>
