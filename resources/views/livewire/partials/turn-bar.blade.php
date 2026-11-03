<div class="card mb-3 turn-bar">
    <div class="card-body py-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="text-uppercase small fw-bold text-muted me-1">Próximos turnos</span>
            @forelse($turnBar['items'] as $item)
                <span class="badge {{ $item['badgeClass'] }} d-inline-flex align-items-center gap-1 {{ $item['activeClass'] }}" title="{{ $item['nombre'] }}">
                    <x-sprite size="48" :src="$item['icon']" :name="$item['nombre']" wrapper-class="shrink-0" />
                </span>
            @empty
                <span class="text-muted small">Esperando...</span>
            @endforelse

            <div class="ms-auto d-flex align-items-center gap-2">
                @if($battleField['clima'] !== null)
                    <div class="alert {{ $battleField['clima']['variante'] }} weather-{{ $battleField['clima']['tipo'] }} py-1 px-2 mb-0 d-inline-flex align-items-center gap-2"
                         role="status"
                         title="Clima actual: {{ $battleField['clima']['label'] }}">
                        <span class="weather-label fw-semibold">{{ $battleField['clima']['label'] }}</span>
                        @if($battleField['clima']['mostrarBonusRoca'])
                            <span class="weather-power small" title="+500 potencia a movimientos Roca">(+500 Roca)</span>
                        @endif
                    </div>
                @endif
                @if($preparacionPendiente)
                    <button type="button"
                            class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#preparacion-modal"
                            title="Ajusta posturas y movimientos de prioridad al inicio de la ronda">
                        Preparación
                    </button>
                @endif
                @if($turnBar['mostrarEsperar'])
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            wire:click="pasarTurno"
                            wire:loading.attr="disabled"
                            title="Consume tu acción sin atacar">
                        Esperar
                    </button>
                @endif
                @if($turnBar['puedeRetrasar'])
                    <button type="button"
                            class="btn btn-sm btn-outline-warning"
                            wire:click="retrasarTurno"
                            wire:loading.attr="disabled"
                            title="Actúa al final de la ronda; no consume tu acción">
                        Retrasar turno
                    </button>
                @endif
                @if($turnBar['puedeCambiarTurno'])
                    <button type="button"
                            class="btn btn-sm btn-outline-primary turn-swap"
                            wire:click="swapTurno"
                            wire:loading.attr="disabled"
                            title="Adelanta el turno de {{ $turnBar['siguienteTurno']['nombre'] }}; el pokémon actual actuará después">
                        Atacar con {{ $turnBar['siguienteTurno']['nombre'] }}
                    </button>
                @endif
                <button type="button"
                        class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal"
                        data-bs-target="#battle-log-modal"
                        title="Consultar el registro de batalla">
                    Registro
                </button>
            </div>
        </div>
    </div>
</div>
