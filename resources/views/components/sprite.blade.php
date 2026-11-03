{{--
    Sprite único de Pokémon.

    Props:
    - id: especie; construye /images/iconos_webp/{id}{suffix}.webp
    - src: URL explícita; tiene prioridad sobre id
    - name: texto alternativo
    - shiny: usa /images/iconos_webp/shiny/{id}{suffix}_s.webp
    - suffix: sufijo de forma regional añadido al id
    - size: 128 | 96 | 64 | inline | combat
    - loading: eager | lazy
    - priority: añade fetchpriority="high"
    - decorative: alt="" + aria-hidden="true"
    - wrapperClass: clases extra para el contenedor

    Los atributos no declarados se aplican al <img> (x-bind:src, x-bind:alt,
    x-on:error, class, etc.). El fallback estático es shiny → normal → 0.webp.
--}}
@props([
    'id' => null,
    'src' => null,
    'name' => '',
    'shiny' => false,
    'suffix' => '',
    'size' => '96',
    'loading' => 'lazy',
    'priority' => false,
    'decorative' => false,
    'wrapperClass' => '',
])

@php
    $allowedSizes = ['128', '96', '64', 'inline', 'combat'];
    $spriteSize = in_array((string) $size, $allowedSizes, true) ? (string) $size : '96';
    $loadingMode = in_array($loading, ['eager', 'lazy'], true) ? $loading : 'lazy';

    $assetId = $id !== null && $id !== '' ? (string) $id . (string) $suffix : null;
    $normalSrc = $assetId !== null ? '/images/iconos_webp/' . $assetId . '.webp' : null;
    $shinySrc = $assetId !== null ? '/images/iconos_webp/shiny/' . $assetId . '_s.webp' : null;

    $resolvedSrc = $src;
    if ($resolvedSrc === null && $assetId !== null) {
        $resolvedSrc = $shiny ? $shinySrc : $normalSrc;
    }

    $alt = $decorative ? '' : (string) $name;
    $wrapperClasses = trim('sprite sprite--' . $spriteSize . ' ' . $wrapperClass);

    $hasAlpineError = $attributes->has('x-on:error');
    $onError = '';

    if (! $hasAlpineError) {
        $zeroFallback = \Illuminate\Support\Js::from('/images/iconos_webp/0.webp')->toHtml();

        if ($shiny && $normalSrc !== null) {
            $normalFallback = \Illuminate\Support\Js::from($normalSrc)->toHtml();
            $onError = 'this.onerror=function(){this.onerror=null;this.src=' . $zeroFallback . '};this.src=' . $normalFallback . ';';
        } else {
            $onError = 'this.onerror=null;this.src=' . $zeroFallback . ';';
        }
    }

    $imgAttributes = $attributes
        ->except(['src', 'alt', 'width', 'height', 'loading', 'decoding', 'fetchpriority', 'onerror', 'aria-hidden'])
        ->class(['sprite__img']);
@endphp

<span class="{{ $wrapperClasses }}">
    <img
        @if($resolvedSrc !== null) src="{{ $resolvedSrc }}" @endif
        alt="{{ $alt }}"
        width="128"
        height="128"
        loading="{{ $loadingMode }}"
        decoding="async"
        @if($priority) fetchpriority="high" @endif
        @if($decorative) aria-hidden="true" @endif
        @if($onError !== '') onerror="{{ $onError }}" @endif
        {{ $imgAttributes }}
    >
</span>
