@php
    $coloresTipo = [];
    $coloresTextoTipo = [];

    foreach (\Src\Shared\Tipos\TipoPokemon::cases() as $tipo) {
        $coloresTipo[$tipo->slug()] = $tipo->color();
        $coloresTextoTipo[$tipo->slug()] = $tipo->textoSobreColor();
    }
@endphp

<style>
    :root {
        @foreach ($coloresTipo as $slug => $color)
            --type-{{ $slug }}: {{ $color }};
            --type-{{ $slug }}-on: {{ $coloresTextoTipo[$slug] }};
        @endforeach
    }
</style>

<script>
    window.TIPOS_COLOR = @json($coloresTipo);
</script>
