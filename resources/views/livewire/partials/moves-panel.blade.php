<div class="moves-panel">
    <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
        <h3 class="h5 mb-0">Ataques</h3>
        <div class="d-flex align-items-center gap-2">
            @if($movesResumen['comboActual'] > 0)
                <span class="badge bg-info-soft text-info-ink" title="Racha de combo">
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
            <span>{{ $animAttackerNombre }} usa {{ $animMoveNombre !== '' ? $animMoveNombre : 'un ataque' }}...</span>
        </div>
    @elseif($phase === 'player_move')
        {{-- Ataques en cards: 2 por fila en móvil, 3 en tablet y 1 en el panel lateral de escritorio. --}}
        <div class="row g-2">
            @foreach($currentMoves as $idx => $move)
                <div class="col-6 col-md-4 col-lg-12">
                    <button type="button"
                            class="card move-card h-100 w-100 text-start position-relative {{ $move['esMejor'] ? 'move-best' : '' }} {{ $move['disponible'] ? '' : 'opacity-50' }}"
                            style="background-color: var(--type-{{ $move['tipoSlug'] }}); color: var(--color-on-primary)"
                            @disabled(! $move['disponible'])
                            title="{{ $move['disponible'] ? '' : $move['motivoTexto'] }}"
                            wire:click="selectMove({{ $idx }})">
                        <div class="card-body p-2 d-flex flex-column gap-1">
                            {{-- Fila 1: icono + nombre | categoría + precisión a la derecha --}}
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-1 min-w-0">
                                    <img src="/images/type/{{ $move['tipoSlug'] }}.webp" alt="{{ $move['tipoLabel'] }}" style="width:40px;height:40px" class="flex-shrink-0">
                                    <span class="fw-bold small lh-sm move-name text-truncate min-w-0" title="{{ $move['nombre'] }}">{{ $move['nombre'] }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                    <span class="badge text-bg-{{ $move['categoriaColor'] }} move-cat">{{ $move['categoriaLabel'] }}</span>
                                    <span class="badge bg-surface-sunken border text-ink-muted move-accuracy" title="Precisión del ataque">
                                        Prec. {{ $move['precisionTexto'] }}
                                    </span>
                                </div>
                            </div>

                            {{-- Fila 2: daño + desglose | efectividad + directo a la misma altura --}}
                            @if($move['potencia'] > 0)
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <div class="d-flex align-items-baseline gap-1 lh-1">
                                        <span class="fs-5 fw-bold move-dmg">{{ ceil($move['daño']) }}</span>
                                        <span class="small text-on-primary/70">daño</span>
                                    </div>
                                    @if(! $move['esEstado'])
                                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                            <span class="badge text-bg-{{ $move['efectividadColor'] }} efectividad">×{{ $move['efectividadTexto'] }}</span>
                                            @if($move['directo'])
                                                <span class="badge text-bg-danger directo-badge">DIRECTO</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                @if($move['letal'])
                                    <span class="badge bg-danger-soft text-danger-ink move-lethal">LETAL</span>
                                @endif
                            @elseif(! $move['esEstado'])
                                <div class="d-flex align-items-center gap-1">
                                    <span class="badge text-bg-{{ $move['efectividadColor'] }} efectividad">×{{ $move['efectividadTexto'] }}</span>
                                    @if($move['directo'])
                                        <span class="badge text-bg-danger directo-badge">DIRECTO</span>
                                    @endif
                                </div>
                            @endif

                            @if($move['statusLabel'] !== '')
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge bg-warning-soft text-warning-ink status-badge">
                                        {{ $move['statusLabel'] }}
                                    </span>
                                </div>
                            @endif

                            @if($move['cargaMaxima'] > 0 || $move['usosMaximos'] > 0 || ! $move['disponible'])
                                <div class="d-flex flex-wrap gap-1">
                                    @if($move['cargaMaxima'] > 0)
                                        <span class="badge {{ $move['cargaActual'] >= $move['cargaMaxima'] ? 'bg-success-soft text-success-ink' : 'bg-surface-sunken text-ink-muted' }}">
                                            Carga {{ $move['cargaActual'] }}/{{ $move['cargaMaxima'] }}
                                        </span>
                                    @endif
                                    @if($move['usosMaximos'] > 0)
                                        <span class="badge {{ $move['usosRestantes'] > 0 ? 'bg-info-soft text-info-ink' : 'bg-ink text-ink-inverse' }}">
                                            Usos {{ max(0, $move['usosRestantes']) }}/{{ $move['usosMaximos'] }}
                                        </span>
                                    @endif
                                    @if(! $move['disponible'] && $move['motivoTexto'] !== '')
                                        <span class="badge bg-surface-sunken text-ink-muted border border-line">{{ $move['motivoTexto'] }}</span>
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
            <span>Ejecutando turno...</span>
        </div>
    @else
        <div class="waiting d-flex align-items-center gap-2 text-muted">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span>Ejecutando turno...</span>
        </div>
    @endif
</div>
