@php
    // Construir mapa nombre → icon para integrar imágenes en el texto del log.
    $iconsPorNombre = [];
    foreach (array_merge($team1 ?? [], $team2 ?? []) as $p) {
        $iconsPorNombre[$p['nombre']] = $p['icon'];
    }
    // Ordenar por longitud descendente para evitar que un nombre subcadena
    // (ej. "Gengar" dentro de "Mega Gengar") se reemplace antes.
    uksort($iconsPorNombre, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));
@endphp

<div class="battle-log">
    <div class="card">
        <div class="card-header bg-primary text-white fw-semibold">Registro de batalla</div>
        <ul class="list-group list-group-flush">
            @forelse(array_slice($log, -10) as $entry)
                @php
                    // Escapar el texto plano y luego insertar la imagen delante de cada
                    // pokémon mencionado (atacante y defensor quedan inline con el texto).
                    $entryConImagenes = e($entry);
                    foreach ($iconsPorNombre as $nombre => $icon) {
                        $nombreEscapado = e($nombre);
                        $img = '<img src="' . e($icon) . '" alt="' . $nombreEscapado . '" style="width:60px;height:60px;object-fit:contain;vertical-align:middle" class="mx-1" loading="lazy">';
                        $entryConImagenes = str_replace($nombreEscapado, $img . ' ' . $nombreEscapado, $entryConImagenes);
                    }
                @endphp
                <li class="list-group-item py-1 small log-entry d-flex align-items-center gap-1 flex-wrap">
                    {!! $entryConImagenes !!}
                </li>
            @empty
                <li class="list-group-item py-1 small text-muted d-flex align-items-center gap-1 flex-wrap">Sin eventos en el registro</li>
            @endforelse
        </ul>
    </div>
</div>