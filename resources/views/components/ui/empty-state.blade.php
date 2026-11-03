{{--
    Estado vacío reutilizable.

    Props:
    - title: mensaje principal (obligatorio)
    - description: texto de apoyo opcional
    - icon: glifo decorativo opcional (aria-hidden)

    Slot:
    - action: acción principal opcional
--}}
@props([
    'title',
    'description' => null,
    'icon' => null,
])

<div {{ $attributes->class(['flex flex-col items-center justify-center gap-3 px-6 py-12 text-center']) }}>
    @if($icon !== null && $icon !== '')
        <div class="text-3xl text-ink-subtle" aria-hidden="true">{{ $icon }}</div>
    @endif

    <h3 class="text-base font-semibold text-ink">{{ $title }}</h3>

    @if($description !== null && $description !== '')
        <p class="max-w-md text-sm text-ink-muted">{{ $description }}</p>
    @endif

    @if(isset($action))
        <div class="mt-2">{{ $action }}</div>
    @endif
</div>
