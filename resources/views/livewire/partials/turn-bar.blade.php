@php
    $puedeCambiarTurno = count($turnQueue) >= 2
        && ($turnQueue[0]['team'] ?? null) === 0
        && ($turnQueue[1]['team'] ?? null) === 0
        && in_array($phase, ['player_target', 'player_move'], true)
        && ($animAttackerId ?? '') === '';
    $siguienteTurno = $puedeCambiarTurno ? ($team1[$turnQueue[1]['index']] ?? null) : null;
@endphp
<div class="card mb-3 turn-bar">
    <div class="card-body py-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="text-uppercase small fw-bold text-muted me-1">Próximos turnos</span>
            @forelse($turnQueue as $idx => $turn)
                @php $p = $turn['team'] === 0 ? ($team1[$turn['index']] ?? null) : ($team2[$turn['index']] ?? null); @endphp
                @if($p && $p['alive'])
                    <span class="badge rounded-pill {{ $idx === 0 ? 'bg-primary' : 'bg-secondary' }} d-inline-flex align-items-center gap-1 {{ $idx === 0 || $p['refId'] === $actingRefId ? 'active-turn' : '' }}" title="{{ $p['nombre'] }}">
                        <img src="{{ $p['icon'] }}" alt="{{ $p['nombre'] }}" style="width:20px; height:20px; object-fit:contain">
                        {{ $p['nombre'] }}
                        <small class="opacity-75">{{ round($p['effectiveSpeed']) }}</small>
                    </span>
                @endif
            @empty
                <span class="text-muted small">Esperando...</span>
            @endforelse

            <div class="ms-auto d-flex align-items-center gap-2">
                @if($siguienteTurno && $siguienteTurno['alive'] && $siguienteTurno['refId'] !== $actingRefId)
                    <button type="button"
                            class="btn btn-sm btn-outline-primary turn-swap"
                            wire:click="swapTurno"
                            wire:loading.attr="disabled"
                            title="Adelanta el turno de {{ $siguienteTurno['nombre'] }}; el pokémon actual actuará después">
                        ⇄ Atacar con {{ $siguienteTurno['nombre'] }}
                    </button>
                @endif
                <button type="button"
                        class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal"
                        data-bs-target="#battle-log-modal"
                        title="Consultar el registro de batalla">
                    📜 Registro
                </button>
            </div>
        </div>
    </div>
</div>
