{{--
    Sprite de entrenador (pixel art 80×80 del manifiesto).

    Props:
    - slug: slug del manifiesto (p. ej. `bugsy`); si no existe, fallback a trainer.webp
    - size: tamaño CSS en px (80 por defecto)
    - name: nombre visible de la clase o líder; se usa como `alt`
    - decorative: alt="" + aria-hidden="true" cuando el texto ya está al lado
    - loading: eager | lazy (lazy por defecto)

    Los atributos extra se aplican al <img>.
--}}
@props([
    'slug' => '',
    'size' => 80,
    'name' => '',
    'decorative' => false,
    'loading' => 'lazy',
])

@php
    $tamano = (int) $size;
    if ($tamano < 8 || $tamano > 160) {
        $tamano = 80;
    }

    $modoCarga = in_array($loading, ['eager', 'lazy'], true) ? $loading : 'lazy';
    $src = \App\Support\UI\SpritesEntrenador::url((string) $slug);
    $alt = $decorative ? '' : (string) $name;
    $fallbackJs = \Illuminate\Support\Js::from(\App\Support\UI\SpritesEntrenador::FALLBACK_URL)->toHtml();
    $onError = 'this.onerror=null;if(this.src.indexOf("/images/misc/trainer.webp")===-1){this.src=' . $fallbackJs . ';}';

    $imgAttributes = $attributes->except(['src', 'alt', 'width', 'height', 'loading', 'decoding', 'onerror', 'aria-hidden']);
@endphp

<img
    src="{{ $src }}"
    alt="{{ $alt }}"
    width="{{ $tamano }}"
    height="{{ $tamano }}"
    loading="{{ $modoCarga }}"
    decoding="async"
    style="image-rendering: pixelated"
    @if($decorative) aria-hidden="true" @endif
    onerror="{{ $onError }}"
    {{ $imgAttributes->class(['object-contain']) }}
>
