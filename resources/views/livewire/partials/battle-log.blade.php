@php
    // Construir mapa nombre → icon para integrar imágenes en el texto del log.
    // El nombre visible se sustituye por el icono: la imagen identifica al pokémon.
    $iconsPorNombre = [];
    foreach (array_merge($team1 ?? [], $team2 ?? []) as $p) {
        $iconsPorNombre[$p['nombre']] = $p['icon'];
    }
    // Ordenar por longitud descendente para evitar que un nombre subcadena
    // (ej. "Gengar" dentro de "Mega Gengar") se reemplace antes.
    uksort($iconsPorNombre, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));
@endphp

<div class="battle-log">
    <ul class="list-group list-group-flush">
        @forelse(array_slice($log, -50) as $entry)
            @php
                // Se inserta solo el icono en lugar del nombre del pokémon; el alt
                // conserva el nombre para lectores de pantalla. Los nombres se cambian
                // primero por marcadores y los iconos se inyectan al final para no
                // reemplazar nombres dentro del HTML ya insertado.
                $entryConImagenes = e($entry);
                $iconosInsertados = [];
                foreach ($iconsPorNombre as $nombre => $icon) {
                    $nombreEscapado = e($nombre);
                    $marcador = '%%pokemon_' . count($iconosInsertados) . '%%';
                    if (str_contains($entryConImagenes, $nombreEscapado)) {
                        $entryConImagenes = str_replace($nombreEscapado, $marcador, $entryConImagenes);
                        $iconosInsertados[$marcador] = '<img src="' . e($icon) . '" alt="' . $nombreEscapado . '" style="width:40px;height:40px;object-fit:contain;vertical-align:middle" class="me-1" loading="lazy">';
                    }
                }
                $entryConImagenes = str_replace(array_keys($iconosInsertados), array_values($iconosInsertados), $entryConImagenes);
            @endphp
            <li class="list-group-item py-1 small log-entry d-flex align-items-center gap-1 flex-wrap">
                {!! $entryConImagenes !!}
            </li>
        @empty
            <li class="list-group-item py-1 small text-muted d-flex align-items-center gap-1 flex-wrap">Sin eventos en el registro</li>
        @endforelse
    </ul>
</div>
