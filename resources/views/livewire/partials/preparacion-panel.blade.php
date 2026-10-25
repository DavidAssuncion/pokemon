@if($preparacionPendiente)
    <div class="card mb-3 border border-blue-200 dark:border-blue-900/60">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <div class="fw-semibold text-gray-900 dark:text-gray-100">Preparación de la ronda {{ $round }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Ajusta las posturas y, si quieres, adelanta un movimiento con prioridad.
                        Si juegas directamente, la preparación se omite.
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="saltarPreparacion" {{ ($processing || $animAttackerId !== '') ? 'disabled' : '' }}>
                        Saltar
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" wire:click="confirmarPreparacion" {{ ($processing || $animAttackerId !== '') ? 'disabled' : '' }}>
                        Listo
                    </button>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                <span class="text-[10px] uppercase fw-bold text-gray-500 dark:text-gray-400">Posturas de la ronda</span>
                @foreach($team1Vista as $pokemon)
                    @if($pokemon['alive'])
                        <div class="d-inline-flex align-items-center gap-1 bg-light border rounded px-1 py-0.5" title="Postura de {{ $pokemon['nombre'] }}">
                            <img src="{{ $pokemon['icon'] }}" alt="" width="18" height="18" style="object-fit:contain">
                            <select class="form-select form-select-sm border-0 bg-transparent text-[10px] p-0" style="width: 5.4rem;" wire:change="cambiarPostura('{{ $pokemon['refId'] }}', $event.target.value)" aria-label="Postura de {{ $pokemon['nombre'] }}" {{ ($processing || $animAttackerId !== '') ? 'disabled' : '' }}>
                                <option value="atacar" @selected(($posturas[$pokemon['refId']] ?? 'atacar') === 'atacar')>Atacar</option>
                                <option value="guardia" @selected(($posturas[$pokemon['refId']] ?? 'atacar') === 'guardia')>Guardia</option>
                                <option value="cubrir" @selected(($posturas[$pokemon['refId']] ?? 'atacar') === 'cubrir')>Cubrir</option>
                            </select>
                        </div>
                    @endif
                @endforeach
            </div>
            <div class="text-[10px] text-gray-500 dark:text-gray-400 mb-3">
                Atacar: acción normal · Guardia: −50 % de daño recibido · Cubrir: redirige el 40 % del daño de un aliado.
                Si eliges un movimiento de prioridad, la postura vuelve a Atacar.
            </div>

            <div class="row g-2">
                @foreach($team1Vista as $pokemon)
                    @if($pokemon['alive'])
                        @php($prioridadesPokemon = $prioridadesDisponibles[$pokemon['refId']] ?? [])
                        @if($prioridadesPokemon !== [])
                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="border rounded p-2 h-100">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <img src="{{ $pokemon['icon'] }}" alt="{{ $pokemon['nombre'] }}" width="32" height="32" loading="lazy">
                                        <span class="fw-semibold text-sm text-gray-900 dark:text-gray-100">{{ $pokemon['nombre'] }}</span>
                                    </div>

                                    <label class="form-label text-xs mb-1">Movimiento de prioridad</label>
                                    <select class="form-select form-select-sm" wire:model="prioridades.{{ $pokemon['refId'] }}">
                                        <option value="">Sin prioridad</option>
                                        @foreach($prioridadesPokemon as $prioridad)
                                            <option value="{{ $prioridad['index'] }}">
                                                {{ $prioridad['nombre'] }} (+{{ $prioridad['prioridad'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-1">
                                        Consume la acción y se resuelve antes de los ataques normales. Fuerza postura Atacar.
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif
                @endforeach
            </div>

            @if(empty($prioridadesDisponibles))
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Ningún Pokémon tiene movimientos de prioridad disponibles. Ajusta las posturas o juega directamente.
                </div>
            @endif
        </div>
    </div>
@endif
