{{--
    Badge de tipo Pokémon.

    Props:
    - tipo: id, slug o etiqueta del tipo
    - slug: alias de tipo
    - label: texto visible; si falta se usa la etiqueta resuelta
    - size: xs | sm | md (tamaño del icono 16/20/28 px)
--}}
@props([
    'tipo' => null,
    'slug' => null,
    'label' => null,
    'size' => 'md',
])

@php
    $badge = \Src\Shared\UI\TipoBadges::resolver($tipo ?? $slug ?? $label);
    $tamanosIcono = [
        'xs' => 'w-4 h-4',
        'sm' => 'w-5 h-5',
        'md' => 'w-7 h-7',
    ];
    $tamanoIcono = $tamanosIcono[$size] ?? $tamanosIcono['md'];
@endphp

<span
    {{ $attributes->class(['tipo-badge shrink-0 font-semibold']) }}
    @if($badge['slug'] !== '') style="--type-color: var(--type-{{ $badge['slug'] }})" @endif
>
    @if($badge['icono'] !== '')
        <img
            src="{{ $badge['icono'] }}"
            alt=""
            loading="lazy"
            decoding="async"
            class="{{ $tamanoIcono }} object-contain"
        >
    @endif
    <span>{{ $label ?? $badge['label'] }}</span>
</span>
