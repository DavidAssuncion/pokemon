<div class="card mb-3 turn-bar">
    <div class="card-body py-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="text-uppercase small fw-bold text-muted me-1">Próximos turnos</span>
            @forelse($turnBar['items'] as $item)
                <span class="badge rounded-pill {{ $item['badgeClass'] }} d-inline-flex align-items-center gap-1 {{ $item['activeClass'] }}" title="{{ $item['nombre'] }}">
                    <img src="{{ $item['icon'] }}" alt="{{ $item['nombre'] }}" style="width:20px; height:20px; object-fit:contain">
                    {{ $item['nombre'] }}
                    <small class="opacity-75">{{ $item['velocidad'] }}</small>
                </span>
            @empty
                <span class="text-muted small">Esperando...</span>
            @endforelse

            <div class="ms-auto d-flex align-items-center gap-2">
                @if($turnBar['mostrarEsperar'])
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            wire:click="pasarTurno"
                            wire:loading.attr="disabled"
                            title="Consume tu acción sin atacar">
                        ⏭ Esperar
                    </button>
                @endif
                @if($turnBar['puedeRetrasar'])
                    <button type="button"
                            class="btn btn-sm btn-outline-warning"
                            wire:click="retrasarTurno"
                            wire:loading.attr="disabled"
                            title="Actúa al final de la ronda; no consume tu acción">
                        ⏳ Retrasar turno
                    </button>
                @endif
                @if($turnBar['puedeCambiarTurno'])
                    <button type="button"
                            class="btn btn-sm btn-outline-primary turn-swap"
                            wire:click="swapTurno"
                            wire:loading.attr="disabled"
                            title="Adelanta el turno de {{ $turnBar['siguienteTurno']['nombre'] }}; el pokémon actual actuará después">
                        ⇄ Atacar con {{ $turnBar['siguienteTurno']['nombre'] }}
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
