@php
    // Construir mapa nombre → icon para detectar qué pokémon se mencionan en el log
    $iconsPorNombre = [];
    foreach (array_merge($team1 ?? [], $team2 ?? []) as $p) {
        $iconsPorNombre[$p['nombre']] = $p['icon'];
    }
@endphp

<div class="battle-log mt-3">
    <div class="card">
        <div class="card-header bg-body-tertiary fw-semibold">Registro de batalla</div>
        <ul class="list-group list-group-flush">
            @forelse(array_slice($log, -10) as $entry)
                <li class="list-group-item py-1 small log-entry">
                    @foreach($iconsPorNombre as $nombre => $icon)
                        @if(str_contains($entry, $nombre))
                            <img src="{{ $icon }}" alt="{{ $nombre }}" style="width:20px;height:20px;object-fit:contain" class="me-1" loading="lazy">
                        @endif
                    @endforeach
                    {{ $entry }}
                </li>
            @empty
                <li class="list-group-item py-1 small text-muted">Sin eventos en el registro</li>
            @endforelse
        </ul>
    </div>
</div>
