{{--
    Superficie de tarjeta.

    Props:
    - title: título opcional; si existe se asocia con aria-labelledby
    - subtitle: subtítulo opcional bajo el título
    - padding: none | sm | md (padding del contenido)
    - as: etiqueta semántica del contenedor (div | section | article | aside | form | nav)

    Slots:
    - default: contenido
    - footer: pie opcional
--}}
@props([
    'title' => null,
    'subtitle' => null,
    'padding' => 'md',
    'as' => 'div',
])

@php
    $tag = in_array($as, ['div', 'section', 'article', 'aside', 'form', 'nav'], true) ? $as : 'div';
    $padding = in_array($padding, ['none', 'sm', 'md'], true) ? $padding : 'md';

    $bodyPadding = ['none' => '', 'sm' => 'p-4', 'md' => 'p-6'][$padding];
    $sectionPadding = ['none' => 'px-6 py-4', 'sm' => 'px-4 py-3', 'md' => 'px-6 py-4'][$padding];

    $hasTitle = $title !== null && $title !== '';
    $headingId = null;

    if ($hasTitle) {
        $cardId = $attributes->get('id');
        $headingId = $cardId !== null && $cardId !== ''
            ? $cardId . '-title'
            : 'card-title-' . \Illuminate\Support\Str::slug((string) $title);
    }

    $rootAttributes = $attributes->class(['card-surface overflow-hidden']);

    if ($headingId !== null) {
        $rootAttributes = $rootAttributes->merge(['aria-labelledby' => $headingId]);
    }
@endphp

<{{ $tag }} {{ $rootAttributes }}>
    @if($hasTitle)
        <header class="{{ $sectionPadding }} border-b border-line">
            <h2 id="{{ $headingId }}" class="text-lg font-semibold text-ink">{{ $title }}</h2>
            @if($subtitle !== null && $subtitle !== '')
                <p class="mt-0.5 text-sm text-ink-muted">{{ $subtitle }}</p>
            @endif
        </header>
    @endif

    <div class="{{ $bodyPadding }}">
        {{ $slot }}
    </div>

    @if(isset($footer))
        <footer class="{{ $sectionPadding }} border-t border-line">
            {{ $footer }}
        </footer>
    @endif
</{{ $tag }}>
