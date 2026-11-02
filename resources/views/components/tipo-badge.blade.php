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
        'md' => 'w-[30px] h-[30px]',
    ];
    $tamanoIcono = $tamanosIcono[$size] ?? $tamanosIcono['md'];
@endphp

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center gap-1 rounded-full py-0.5 pl-0 pr-2 text-[11px] font-semibold',
    $badge['clases'],
]) }}>
    @if($badge['icono'] !== '')
        <img src="{{ $badge['icono'] }}" alt="" loading="lazy" decoding="async" class="{{ $tamanoIcono }} object-contain">
    @endif
    <span>{{ $label ?? $badge['label'] }}</span>
</span>
