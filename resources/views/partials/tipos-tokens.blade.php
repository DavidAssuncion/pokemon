@php
    $coloresTipo = [];

    foreach (\Src\Shared\Tipos\TipoPokemon::cases() as $tipo) {
        $coloresTipo[$tipo->slug()] = $tipo->color();
    }
@endphp

<style>
    :root {
        @foreach ($coloresTipo as $slug => $color)
            --type-{{ $slug }}: {{ $color }};
        @endforeach
    }
</style>

<script>
    window.TIPOS_COLOR = @json($coloresTipo);
</script>
