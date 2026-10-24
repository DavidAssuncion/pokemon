<div class="moves-panel">
    <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
        <h3 class="h5 mb-0">Ataques</h3>
        <div class="d-flex align-items-center gap-2">
            @php
                $primerMovimientoVista = $currentMoves !== [] ? $currentMoves[array_key_first($currentMoves)] : [];
                $comboActualVista = (int) ($primerMovimientoVista['comboActual'] ?? 0);
                $comboMultiVista = (float) ($primerMovimientoVista['comboMultiplicador'] ?? 1);
            @endphp
            @if($comboActualVista > 0)
                <span class="badge bg-info text-dark" title="Racha de combo">
                    COMBO {{ $comboActualVista }} · ×{{ number_format($comboMultiVista, 2) }}
                </span>
            @endif
            <span class="badge bg-primary round-info">Ronda {{ $round }}</span>
        </div>
    </div>

    @php
        $statLabels = ['attack' => 'Ataque', 'defense' => 'Defensa', 'spAtk' => 'At.Esp', 'spDef' => 'Def.Esp', 'speed' => 'Vel', 'accuracy' => 'Precisión', 'evasion' => 'Evasión'];
        // Calcular máximo daño entre todos los movimientos
        $maxDmg = 0;
        foreach ($currentMoves as $m) {
            $dmg = (float) ($m['daño'] ?? 0);
            if ($dmg > $maxDmg) {
                $maxDmg = $dmg;
            }
        }
    @endphp

    @php
    // Ordenar movimientos: por daño descendente, empate → alfabético
    // ATENCIÓN: uasort (NO usort) para preservar claves originales que coinciden
    // con la posición en moves() — selectMove usa ese índice.
    uasort($currentMoves, function ($a, $b) {
        $dañoA = (float) ($a['daño'] ?? 0);
        $dañoB = (float) ($b['daño'] ?? 0);
        if ($dañoA !== $dañoB) {
            return $dañoA < $dañoB ? 1 : -1; // descendente
        }
        return strcmp(
            mb_strtolower($a['nombre'] ?? ''),
            mb_strtolower($b['nombre'] ?? '')
        ); // ascendente alfabético
    });
    @endphp

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
                @php
                    $tipoValue = (int) $move['tipo'];
                    $tipo = \Src\Shared\Tipos\TipoPokemon::tryFrom($tipoValue);
                    $tipoLabel = $tipo?->label() ?? $move['tipo'];
                    $tipoSlug = $tipo?->slug() ?? 'normal';
                    $tipoColor = $tipo?->color() ?? '#fafafa';
                    $eff = (float) $move['efectividad'];
                    $effText = $eff == (int) $eff ? (string) (int) $eff : number_format($eff, 1);
                    $effColor = $eff <= 0 ? 'dark' : ($eff < 1 ? 'warning' : ($eff <= 1 ? 'light border' : 'success'));
                    $esEstado = $move['categoria'] === 'estado' || (int) ($move['potencia'] ?? 0) <= 0;
                    $catLabel = $esEstado ? 'Estado' : match ($move['categoria']) { 'fisico' => 'Físico', 'especial' => 'Especial', default => 'Estado' };
                    $catColor = $esEstado ? 'secondary' : match ($move['categoria']) { 'fisico' => 'danger', 'especial' => 'primary', default => 'secondary' };
                    $isBest = $move['potencia'] > 0 && $maxDmg > 0 && (float) $move['daño'] >= $maxDmg;
                    $disponible = (bool) ($move['disponible'] ?? true);
                    $cargaMax = (int) ($move['cargaMaxima'] ?? 0);
                    $cargaActual = (int) ($move['cargaActual'] ?? 0);
                    $usosMax = (int) ($move['usosMaximos'] ?? 0);
                    $usosRestantes = (int) ($move['usosRestantes'] ?? -1);
                    $motivo = match ($move['motivoNoDisponible'] ?? null) {
                        'cargando' => 'Cargando '.$cargaActual.'/'.$cargaMax,
                        'sin_usos' => 'Sin usos restantes',
                        'silencio' => 'Silenciado: no puede usar estado',
                        'objeto_eleccion' => 'Objeto de elección: bloqueado a otro movimiento',
                        default => '',
                    };
                @endphp
                <div class="col-6 col-md-4 col-xl-2">
                    <button type="button"
                            class="card move-card h-100 w-100 text-start position-relative {{ $isBest ? 'move-best' : '' }} {{ $disponible ? '' : 'opacity-50' }}"
                            style="background-color: {{ $tipoColor }}; color: #fff"
                            @disabled(! $disponible)
                            title="{{ $disponible ? '' : $motivo }}"
                            wire:click="selectMove({{ $idx }})">
                        <div class="card-body p-2 d-flex flex-column gap-1">
                            {{-- Icono de tipo + nombre del ataque --}}
                            <div class="d-flex align-items-center gap-1">
                                <img src="/images/type/{{ $tipoSlug }}.webp" alt="{{ $tipoLabel }}" style="width:40px;height:40px" class="flex-shrink-0">
                                <span class="fw-bold small lh-sm move-name">{{ $move['nombre'] }}</span>
                            </div>

                            {{-- Daño destacado --}}
                            @if($move['potencia'] > 0)
                                <div class="lh-1">
                                    <span class="fs-5 fw-bold move-dmg">{{ ceil($move['daño']) }}</span>
                                    <span class="small text-white-50">daño</span>
                                </div>

                                @php
                                    $danoHp = (float) ($move['danoHp'] ?? 0);
                                    $danoBarrera = (float) ($move['danoBarrera'] ?? 0);
                                    $barreraLabel = $move['barreraLabel'] ?? 'DEF';
                                @endphp
                                @if($danoHp > 0 || $danoBarrera > 0)
                                    <div class="small text-white-50 lh-sm move-breakdown">
                                        @if($danoBarrera > 0)
                                            <span>{{ ceil($danoBarrera) }} {{ $barreraLabel }}</span>
                                        @endif
                                        @if($danoHp > 0)
                                            <span>{{ ceil($danoHp) }} PS</span>
                                        @endif
                                    </div>
                                @endif
                                @if($move['letal'] ?? false)
                                    <span class="badge text-bg-danger move-lethal">LETAL</span>
                                @endif
                            @endif

                            @if($cargaMax > 0 || $usosMax > 0 || ! $disponible)
                                <div class="d-flex flex-wrap gap-1">
                                    @if($cargaMax > 0)
                                        <span class="badge {{ $cargaActual >= $cargaMax ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            Carga {{ $cargaActual }}/{{ $cargaMax }}
                                        </span>
                                    @endif
                                    @if($usosMax > 0)
                                        <span class="badge {{ $usosRestantes > 0 ? 'text-bg-info' : 'text-bg-dark' }}">
                                            Usos {{ max(0, $usosRestantes) }}/{{ $usosMax }}
                                        </span>
                                    @endif
                                    @if(! $disponible && $motivo !== '')
                                        <span class="badge text-bg-dark">{{ $motivo }}</span>
                                    @endif
                                </div>
                            @endif

                            {{-- Efectividad y etiquetas del ataque. Los movimientos de estado
                                 no tienen efectividad de tipo porque no infligen daño. --}}
                            @php
                                $tieneBadgeEstado = !empty($move['statusEffect']) && $move['statusEffect'] !== 'none';
                            @endphp
                            @if(! $esEstado || $tieneBadgeEstado)
                                <div class="d-flex flex-wrap gap-1 align-items-center">
                                    @if(! $esEstado)
                                        <span class="badge text-bg-{{ $effColor }} efectividad">×{{ $effText }}</span>
                                        @if($move['directo'] ?? false)
                                            <span class="badge text-bg-danger directo-badge">DIRECTO</span>
                                        @endif
                                    @endif
                                    @if($tieneBadgeEstado)
                                        <span class="badge text-bg-warning status-badge">
                                            @switch($move['statusEffect'])
                                                @case('burn') 🔥 QUEMAR @break
                                                @case('poison') ☠ ENVENENAR @break
                                                @case('bad_poison') ☠ TOXICO @break
                                                @case('paralysis') ⚡ PARALIZAR @break
                                                @case('sleep') 💤 DORMIR @break
                                                @case('freeze') ❄ CONGELAR @break
                                                @case('confusion') 😵 CONFUNDIR @break
                                            @endswitch
                                        </span>
                                    @endif
                                </div>
                            @endif

                            {{-- Cambios de estadísticas propios y del objetivo --}}
                            @if(!empty($move['selfStatChanges']) || !empty($move['targetStatChanges']))
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach(($move['selfStatChanges'] ?? []) as $sc)
                                        @php
                                            $pct = (int) round((($sc['factor'] ?? 1.0) - 1) * 100);
                                        @endphp
                                        <span class="badge stage-up stat-self">
                                            {{ $pct > 0 ? '+' : '' }}{{ $pct }}% {{ $statLabels[$sc['stat']] ?? $sc['stat'] }}
                                        </span>
                                    @endforeach
                                    @foreach(($move['targetStatChanges'] ?? []) as $tc)
                                        @php
                                            $pct = (int) round((($tc['factor'] ?? 1.0) - 1) * 100);
                                        @endphp
                                        <span class="badge stage-down stat-target">
                                            {{ $pct > 0 ? '+' : '' }}{{ $pct }}% {{ $statLabels[$tc['stat']] ?? $tc['stat'] }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Categoría al pie de la card --}}
                            <div class="mt-auto">
                                <span class="badge text-bg-{{ $catColor }} move-cat">{{ $catLabel }}</span>
                            </div>
                        </div>
                    </button>
                </div>
            @endforeach
        </div>
    @elseif($phase === 'player_target')
        <div class="alert alert-info target-hint" role="status">
            <strong>Selecciona un objetivo</strong>
            <p class="penalty-note mb-0 small">Retaguardia: -50% daño si hay vanguardia viva</p>
        </div>
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