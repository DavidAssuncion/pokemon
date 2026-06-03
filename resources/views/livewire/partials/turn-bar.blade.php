<div class="turn-bar">
    <div class="turn-label">PRÓXIMOS TURNOS</div>
    <div class="turn-icons">
        @forelse($turnQueue as $idx => $turn)
            @php $p = $turn['team'] === 0 ? ($team1[$turn['index']] ?? null) : ($team2[$turn['index']] ?? null); @endphp
            @if($p && $p['alive'])
                <div class="turn-icon {{ $idx === 0 ? 'active' : '' }} {{ $p['refId'] === $actingRefId ? 'acting' : '' }}" title="{{ $p['nombre'] }}">
                    <img src="{{ $p['icon'] }}" alt="{{ $p['nombre'] }}" class="turn-icon-img">
                    <small>{{ $p['accumulatedSpeed'] }}</small>
                </div>
            @endif
        @empty
            <span class="text-muted">Esperando...</span>
        @endforelse
    </div>
</div>
