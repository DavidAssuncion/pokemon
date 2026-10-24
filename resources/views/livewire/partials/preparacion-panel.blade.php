@if($phase === 'preparacion')
    <div class="card mb-3 border border-blue-200 dark:border-blue-900/60">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <div class="fw-semibold text-gray-900 dark:text-gray-100">Prioridades de la ronda {{ $round }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Las posturas se configuran en Equipo y se mantienen automáticamente.
                        Aquí solo puedes adelantar un movimiento con prioridad.
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="saltarPreparacion">
                        Saltar
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" wire:click="confirmarPreparacion">
                        Listo
                    </button>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                <span class="text-[10px] uppercase fw-bold text-gray-500 dark:text-gray-400">Posturas actuales · solo lectura</span>
                @foreach($team1 as $pokemon)
                    @if($pokemon['alive'])
                        @php
                            $postura = \Src\Battle\Domain\Enums\PosturaCombate::tryFrom((string) ($pokemon['postura'] ?? ''))
                                ?? \Src\Battle\Domain\Enums\PosturaCombate::ATACAR;
                        @endphp
                        <span class="d-inline-flex align-items-center gap-1 bg-light border rounded px-1 py-0.5" title="Postura configurada en Equipo">
                            <img src="{{ $pokemon['icon'] }}" alt="" width="18" height="18" style="object-fit:contain">
                            <select class="form-select form-select-sm border-0 bg-transparent text-[10px] p-0" style="width: 4.8rem;" disabled aria-label="Postura de {{ $pokemon['nombre'] }}">
                                <option value="atacar" @selected($postura === \Src\Battle\Domain\Enums\PosturaCombate::ATACAR)>Atacar</option>
                                <option value="guardia" @selected($postura === \Src\Battle\Domain\Enums\PosturaCombate::GUARDIA)>Guardia</option>
                                <option value="cubrir" @selected($postura === \Src\Battle\Domain\Enums\PosturaCombate::CUBRIR)>Cubrir</option>
                            </select>
                        </span>
                    @endif
                @endforeach
            </div>

            <div class="row g-2">
                @foreach($team1 as $pokemon)
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
                    Ningún Pokémon tiene movimientos de prioridad disponibles. Puedes continuar con la ronda.
                </div>
            @endif
        </div>
    </div>
@endif
