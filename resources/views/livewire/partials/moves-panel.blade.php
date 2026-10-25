<div class="moves-panel">
    <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
        <h3 class="h5 mb-0">Ataques</h3>
        <div class="d-flex align-items-center gap-2">
            @if($movesResumen['comboActual'] > 0)
                <span class="badge bg-info text-dark" title="Racha de combo">
                    COMBO {{ $movesResumen['comboActual'] }} · ×{{ number_format($movesResumen['comboMultiplicador'], 2) }}
                </span>
            @endif
            <span class="badge bg-primary round-info">Ronda {{ $round }}</span>
        </div>
    </div>

    @if($animAttackerId !== '')
        {{-- Ventana de animación en curso (acción pendiente de resolver): bloquea la
             interacción para que ningún clic cancele la animación ni pise la acción
             pendiente (ver ANALISIS_FRONTEND 2026-10-21). --}}
        <div class="waiting d-flex align-items-center gap-2 text-muted" role="status">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span>⚔ {{ $animAttackerNombre }} usa {{ $animMoveNombre !== '' ? $animMoveNombre : 'un ataque' }}...</span>
        </div>
    @elseif($phase === 'player_move')
        {{-- Ataques en una fila de cards: 1/6 del ancho en pantallas grandes --}}
        <div class="row g-2">
            @foreach($currentMoves as $idx => $move)
                <div class="col-6 col-md-4 col-xl-2">
                    <button type="button"
                            class="card move-card h-100 w-100 text-start position-relative {{ $move['esMejor'] ? 'move-best' : '' }} {{ $move['disponible'] ? '' : 'opacity-50' }}"
                            style="background-color: {{ $move['tipoColor'] }}; color: #fff"
                            @disabled(! $move['disponible'])
                            title="{{ $move['disponible'] ? '' : $move['motivoTexto'] }}"
                            wire:click="selectMove({{ $idx }})">
                        <div class="card-body p-2 d-flex flex-column gap-1">
                            {{-- Icono de tipo + nombre del ataque --}}
                            <div class="d-flex align-items-center gap-1">
                                <img src="/images/type/{{ $move['tipoSlug'] }}.webp" alt="{{ $move['tipoLabel'] }}" style="width:40px;height:40px" class="flex-shrink-0">
                                <span class="fw-bold small lh-sm move-name">{{ $move['nombre'] }}</span>
                            </div>

                            {{-- Daño destacado --}}
                            @if($move['potencia'] > 0)
                                <div class="lh-1">
                                    <span class="fs-5 fw-bold move-dmg">{{ ceil($move['daño']) }}</span>
                                    <span class="small text-white-50">daño</span>
                                </div>

                                @if($move['danoHp'] > 0 || $move['danoBarrera'] > 0)
                                    <div class="small text-white-50 lh-sm move-breakdown">
                                        @if($move['danoBarrera'] > 0)
                                            <span>{{ ceil($move['danoBarrera']) }} {{ $move['barreraLabel'] }}</span>
                                        @endif
                                        @if($move['danoHp'] > 0)
                                            <span>{{ ceil($move['danoHp']) }} PS</span>
                                        @endif
                                    </div>
                                @endif
                                @if($move['letal'])
                                    <span class="badge text-bg-danger move-lethal">LETAL</span>
                                @endif
                            @endif

                            @if($move['cargaMaxima'] > 0 || $move['usosMaximos'] > 0 || ! $move['disponible'])
                                <div class="d-flex flex-wrap gap-1">
                                    @if($move['cargaMaxima'] > 0)
                                        <span class="badge {{ $move['cargaActual'] >= $move['cargaMaxima'] ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            Carga {{ $move['cargaActual'] }}/{{ $move['cargaMaxima'] }}
                                        </span>
                                    @endif
                                    @if($move['usosMaximos'] > 0)
                                        <span class="badge {{ $move['usosRestantes'] > 0 ? 'text-bg-info' : 'text-bg-dark' }}">
                                            Usos {{ max(0, $move['usosRestantes']) }}/{{ $move['usosMaximos'] }}
                                        </span>
                                    @endif
                                    @if(! $move['disponible'] && $move['motivoTexto'] !== '')
                                        <span class="badge text-bg-dark">{{ $move['motivoTexto'] }}</span>
                                    @endif
                                </div>
                            @endif

                            {{-- Efectividad y etiquetas del ataque. Los movimientos de estado
                                 no tienen efectividad de tipo porque no infligen daño. --}}
                            @if(! $move['esEstado'] || $move['statusLabel'] !== '')
                                <div class="d-flex flex-wrap gap-1 align-items-center">
                                    @if(! $move['esEstado'])
                                        <span class="badge text-bg-{{ $move['efectividadColor'] }} efectividad">×{{ $move['efectividadTexto'] }}</span>
                                        @if($move['directo'])
                                            <span class="badge text-bg-danger directo-badge">DIRECTO</span>
                                        @endif
                                    @endif
                                    @if($move['statusLabel'] !== '')
                                        <span class="badge text-bg-warning status-badge">
                                            {{ $move['statusEmoji'] }} {{ $move['statusLabel'] }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            {{-- Cambios de estadísticas propios y del objetivo --}}
                            @if($move['selfStatChanges'] !== [] || $move['targetStatChanges'] !== [])
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($move['selfStatChanges'] as $sc)
                                        <span class="badge stage-up stat-self">
                                            {{ $sc['texto'] }}
                                        </span>
                                    @endforeach
                                    @foreach($move['targetStatChanges'] as $tc)
                                        <span class="badge stage-down stat-target">
                                            {{ $tc['texto'] }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Categoría al pie de la card --}}
                            <div class="mt-auto">
                                <span class="badge text-bg-{{ $move['categoriaColor'] }} move-cat">{{ $move['categoriaLabel'] }}</span>
                            </div>
                        </div>
                    </button>
                </div>
            @endforeach
        </div>
    @elseif($phase === 'player_target')
        {{-- La selección de objetivo se hace directamente en el campo. --}}
    @elseif($phase === 'battle_over')
        <div class="alert alert-success battle-over {{ !empty($eventos) ? 'd-none' : '' }}" role="status">
            ¡Batalla terminada!
        </div>
    @elseif($phase === 'animating')
        <div class="waiting d-flex align-items-center gap-2 text-muted">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span>⚔ Ejecutando turno...</span>
        </div>
    @else
        <div class="waiting d-flex align-items-center gap-2 text-muted">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span>Ejecutando turno...</span>
        </div>
    @endif
</div>
