<div class="d-flex align-items-start gap-2 flex-wrap mb-2">
    <span class="text-2xs uppercase fw-bold text-ink-muted">Posturas de la ronda</span>
    @foreach($team1Vista as $pokemon)
        @if($pokemon['alive'])
            @php
                $refId = $pokemon['refId'];
                $posturaActual = $posturas[$refId] ?? 'atacar';
                $prioridadActual = $prioridades[$refId] ?? '';
            @endphp
            <div class="d-inline-flex flex-column gap-1 bg-surface border rounded px-1 py-0.5" title="Postura de {{ $pokemon['nombre'] }}" wire:key="postura-{{ $refId }}">
                <div class="d-flex align-items-center gap-1">
                    <x-sprite size="inline" :src="$pokemon['icon']" :name="$pokemon['nombre']" wrapper-class="shrink-0" />
                    <select id="postura-{{ $refId }}" class="form-select form-select-sm border-0 bg-transparent text-2xs p-0" style="width: 5.4rem;" wire:change="cambiarPostura('{{ $refId }}', $event.target.value)" aria-label="Postura de {{ $pokemon['nombre'] }}" {{ ($processing || $animAttackerId !== '') ? 'disabled' : '' }}>
                        <option value="atacar" @selected($posturaActual === 'atacar')>Atacar</option>
                        <option value="guardia" @selected($posturaActual === 'guardia')>Guardia</option>
                        <option value="cubrir" @selected($posturaActual === 'cubrir')>Cubrir</option>
                    </select>
                </div>
                @if($posturaActual === 'cubrir')
                    @if($prioridadActual !== '')
                        <span class="text-2xs text-ink-muted">La prioridad fuerza Atacar y cancela Cubrir.</span>
                    @else
                        <label for="cobertura-{{ $refId }}" class="visually-hidden">Aliado al que cubre {{ $pokemon['nombre'] }}</label>
                        <select id="cobertura-{{ $refId }}" class="form-select form-select-sm text-2xs" wire:model="coberturas.{{ $refId }}" aria-label="Aliado al que cubre {{ $pokemon['nombre'] }}" {{ ($processing || $animAttackerId !== '') ? 'disabled' : '' }}>
                            <option value="">Automático</option>
                            @foreach($team1Vista as $aliado)
                                @if($aliado['alive'] && $aliado['refId'] !== $refId)
                                    <option value="{{ $aliado['refId'] }}" wire:key="cobertura-{{ $refId }}-{{ $aliado['refId'] }}" @selected(($coberturas[$refId] ?? '') === $aliado['refId'])>{{ $aliado['nombre'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    @endif
                @endif
            </div>
        @endif
    @endforeach
</div>
<div class="text-2xs text-ink-muted mb-3">
    Atacar: acción normal · Guardia: −50 % de daño recibido · Cubrir: redirige el 40 % del daño del aliado elegido.
    Si eliges un movimiento de prioridad, puedes fijar su objetivo; «Automático» deja que el combate elija un rival legal.
    La prioridad fuerza Atacar y cancela Cubrir; Cubrir sin prioridad permite elegir a quién protege.
</div>

<div class="row g-2">
    @foreach($team1Vista as $pokemon)
        @if($pokemon['alive'])
            @php
                $prioridadesPokemon = $prioridadesDisponibles[$pokemon['refId']] ?? [];
                $refId = $pokemon['refId'];
                $prioridadSeleccionada = (string) ($prioridades[$refId] ?? '');
                $prioridadElegida = null;
                foreach ($prioridadesPokemon as $prioridad) {
                    if ((string) $prioridad['index'] === $prioridadSeleccionada) {
                        $prioridadElegida = $prioridad;
                        break;
                    }
                }
            @endphp
            @if($prioridadesPokemon !== [])
                <div class="col-12 col-md-6 col-xl-4" wire:key="prioridad-{{ $refId }}">
                    <div class="border rounded p-2 h-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <x-sprite size="inline" :src="$pokemon['icon']" :name="$pokemon['nombre']" wrapper-class="shrink-0" />
                            <span class="fw-semibold text-sm text-ink">{{ $pokemon['nombre'] }}</span>
                        </div>

                        <label for="prioridad-movimiento-{{ $refId }}" class="form-label text-xs mb-1">Movimiento de prioridad</label>
                        <select id="prioridad-movimiento-{{ $refId }}" class="form-select form-select-sm" wire:model.live="prioridades.{{ $refId }}" aria-describedby="prioridad-movimiento-ayuda-{{ $refId }}" {{ ($processing || $animAttackerId !== '') ? 'disabled' : '' }}>
                            <option value="">Sin prioridad</option>
                            @foreach($prioridadesPokemon as $prioridad)
                                <option value="{{ $prioridad['index'] }}" wire:key="prioridad-opcion-{{ $refId }}-{{ $prioridad['index'] }}">
                                    {{ $prioridad['nombre'] }} (+{{ $prioridad['prioridad'] }})
                                </option>
                            @endforeach
                        </select>
                        <div id="prioridad-movimiento-ayuda-{{ $refId }}" class="text-2xs text-ink-muted mt-1">
                            Consume la acción y se resuelve antes de los ataques normales. Fuerza postura Atacar.
                        </div>

                        @if($prioridadElegida !== null)
                            @if($prioridadElegida['autodirigido'] ?? false)
                                <div class="text-2xs text-ink-muted mt-2" role="note">
                                    Movimiento autodirigido: no requiere objetivo.
                                </div>
                            @elseif(($prioridadElegida['objetivoForzadoRefId'] ?? '') !== '')
                                <div class="alert alert-warning py-1 px-2 text-2xs mt-2 mb-0" role="status">
                                    Desafío: objetivo fijado en {{ $prioridadElegida['objetivoForzadoNombre'] ?? '' }}.
                                </div>
                            @else
                                <label for="prioridad-objetivo-{{ $refId }}" class="form-label text-xs mt-2 mb-1">Objetivo del movimiento de prioridad</label>
                                <select id="prioridad-objetivo-{{ $refId }}" class="form-select form-select-sm" wire:model="prioridadesObjetivo.{{ $refId }}" aria-label="Objetivo de {{ $prioridadElegida['nombre'] }} de {{ $pokemon['nombre'] }}" aria-describedby="prioridad-objetivo-ayuda-{{ $refId }}" {{ ($processing || $animAttackerId !== '') ? 'disabled' : '' }}>
                                    <option value="">Automático</option>
                                    @foreach(($prioridadElegida['objetivos'] ?? []) as $objetivo)
                                        <option value="{{ $objetivo['refId'] }}" wire:key="prioridad-objetivo-opcion-{{ $refId }}-{{ $objetivo['refId'] }}" @selected(($prioridadesObjetivo[$refId] ?? '') === $objetivo['refId'])>
                                            {{ $objetivo['nombre'] }} ({{ $objetivo['posicion'] }})
                                        </option>
                                    @endforeach
                                </select>
                                <div id="prioridad-objetivo-ayuda-{{ $refId }}" class="text-2xs text-ink-muted mt-1">
                                    Automático: el combate elige un rival legal al resolver la prioridad.
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @endif
        @endif
    @endforeach
</div>

@if(empty($prioridadesDisponibles))
    <div class="text-xs text-ink-muted">
        Ningún Pokémon tiene movimientos de prioridad disponibles. Ajusta las posturas o juega directamente.
    </div>
@endif
