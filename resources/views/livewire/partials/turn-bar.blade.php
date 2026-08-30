<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="text-uppercase small fw-bold text-muted me-1">Próximos turnos</span>
            @forelse($turnQueue as $idx => $turn)
                @php $p = $turn['team'] === 0 ? ($team1[$turn['index']] ?? null) : ($team2[$turn['index']] ?? null); @endphp
                @if($p && $p['alive'])
                    <span class="badge rounded-pill {{ $idx === 0 ? 'bg-primary' : 'bg-secondary' }} d-inline-flex align-items-center gap-1 {{ $p['refId'] === $actingRefId ? 'acting' : '' }}" title="{{ $p['nombre'] }}">
                        <img src="{{ $p['icon'] }}" alt="{{ $p['nombre'] }}" style="width:20px; height:20px; object-fit:contain">
                        {{ $p['nombre'] }}
                        <small class="opacity-75">{{ round($p['accumulatedSpeed']) }}</small>
                    </span>
                @endif
            @empty
                <span class="text-muted small">Esperando...</span>
            @endforelse
        </div>
    </div>
</div>
