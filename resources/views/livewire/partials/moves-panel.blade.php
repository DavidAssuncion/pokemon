<div class="moves-panel">
    <h3 class="h5 mb-3">Ataques</h3>

    @php
        $statLabels = ['attack' => 'Ataque', 'defense' => 'Defensa', 'spAtk' => 'At.Esp', 'spDef' => 'Def.Esp', 'speed' => 'Vel', 'accuracy' => 'Precisión', 'evasion' => 'Evasión'];
        $tipoColors = [
            1 => 'secondary', 2 => 'danger', 3 => 'info', 4 => 'success', 5 => 'warning',
            6 => 'secondary', 7 => 'success', 8 => 'dark', 9 => 'secondary', 10 => 'danger',
            11 => 'primary', 12 => 'success', 13 => 'warning', 14 => 'info', 15 => 'info',
            16 => 'primary', 17 => 'dark', 18 => 'danger',
        ];
        // Slugs de tipo para icono (value 1-18 → slug español minúscula)
        $tipoSlugs = [
            1 => 'normal', 2 => 'lucha', 3 => 'volador', 4 => 'veneno', 5 => 'tierra',
            6 => 'roca', 7 => 'bicho', 8 => 'fantasma', 9 => 'acero', 10 => 'fuego',
            11 => 'agua', 12 => 'planta', 13 => 'electrico', 14 => 'psiquico',
            15 => 'hielo', 16 => 'dragon', 17 => 'siniestro', 18 => 'hada',
        ];
        // Colores de fondo suaves por tipo
        $tipoBg = [
            1 => '#fafafa',   // Normal
            2 => '#fdecea',   // Lucha
            3 => '#e3f2fd',   // Volador
            4 => '#f3e5f5',   // Veneno
            5 => '#efebe9',   // Tierra
            6 => '#e0e0e0',   // Roca
            7 => '#e8f5e9',   // Bicho
            8 => '#ede7f6',   // Fantasma
            9 => '#eceff1',   // Acero
            10 => '#ffebee',  // Fuego
            11 => '#e3f2fd',  // Agua
            12 => '#e8f5e9',  // Planta
            13 => '#fff8e1',  // Eléctrico
            14 => '#fce4ec',  // Psíquico
            15 => '#e1f5fe',  // Hielo
            16 => '#ede7f6',  // Dragón
            17 => '#eceff1',  // Siniestro
            18 => '#fce4ec',  // Hada
        ];
        // Calcular máximo daño entre todos los movimientos
        $maxDmg = 0;
        foreach ($currentMoves as $m) {
            if ((float) $m['daño'] > $maxDmg) {
                $maxDmg = (float) $m['daño'];
            }
        }
    @endphp

    @if($phase === 'player_move')
        <div class="d-grid gap-2">
            @foreach($currentMoves as $idx => $move)
                @php
                    $tipoValue = (int) $move['tipo'];
                    $tipoLabel = \Src\Shared\Tipos\TipoPokemon::tryFrom($tipoValue)?->label() ?? $move['tipo'];
                    $tipoColor = $tipoColors[$tipoValue] ?? 'secondary';
                    $tipoSlug = $tipoSlugs[$tipoValue] ?? 'normal';
                    $tipoBgColor = $tipoBg[$tipoValue] ?? '#fafafa';
                    $eff = (float) $move['efectividad'];
                    $effText = $eff == (int) $eff ? (string) (int) $eff : number_format($eff, 1);
                    $effColor = $eff <= 0 ? 'dark' : ($eff < 1 ? 'warning' : ($eff <= 1 ? 'light border' : 'success'));
                    $catLabel = match ($move['categoria']) { 'fisico' => 'Físico', 'especial' => 'Especial', default => 'Estado' };
                    $catColor = match ($move['categoria']) { 'fisico' => 'danger', 'especial' => 'primary', default => 'secondary' };
                    $isBest = $move['potencia'] > 0 && $maxDmg > 0 && (float) $move['daño'] >= $maxDmg;
                @endphp
                <button class="btn btn-outline-primary w-100 text-start position-relative {{ $isBest ? 'border-primary border-2 shadow-sm' : '' }}"
                        style="background-color: {{ $tipoBgColor }}"
                        wire:click="selectMove({{ $idx }})">
                    {{-- Primera línea: icono tipo + nombre + (catálogo + potencia al final) --}}
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <span class="fw-bold move-name d-flex align-items-center gap-1">
                            <img src="/images/type/{{ $tipoSlug }}.webp" alt="{{ $tipoLabel }}" style="width:24px;height:24px" class="me-1">
                            {{ $move['nombre'] }}
                        </span>
                        <span class="d-flex align-items-center gap-1 flex-shrink-0">
                            <span class="badge text-bg-{{ $catColor }} move-cat">{{ $catLabel }}</span>
                            @if($move['potencia'] > 0)
                                <span class="badge text-bg-dark move-power">Pot. {{ $move['potencia'] }}</span>
                            @endif
                        </span>
                    </div>
                    {{-- Segunda línea: daño destacado + efectividad + STAB + estados + cambios --}}
                    <div class="d-flex flex-wrap gap-1 align-items-center mt-1 small">
                        @if($move['potencia'] > 0)
                            <span class="move-dmg fs-5 fw-bold {{ $isBest ? 'text-success' : 'text-dark' }}">{{ ceil($move['daño']) }} daño</span>
                        @endif
                        <span class="badge text-bg-{{ $tipoColor }} move-type">{{ $tipoLabel }}</span>
                        <span class="badge text-bg-{{ $effColor }} efectividad">×{{ $effText }}</span>
                        @if($move['stab'])
                            <span class="badge text-bg-info stab-badge">STAB</span>
                        @endif
                        @if($move['directo'] ?? false)
                            <span class="badge text-bg-danger directo-badge">DIRECTO</span>
                        @endif
                        @if(!empty($move['statusEffect']) && $move['statusEffect'] !== 'none')
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
                        @if(!empty($move['selfStatChanges']))
                            @foreach($move['selfStatChanges'] as $sc)
                                <span class="badge stage-up stat-self">
                                    {{ $sc['stages'] > 0 ? '+' : '' }}{{ $sc['stages'] }} {{ $statLabels[$sc['stat']] ?? $sc['stat'] }}
                                </span>
                            @endforeach
                        @endif
                        @if(!empty($move['targetStatChanges']))
                            @foreach($move['targetStatChanges'] as $tc)
                                <span class="badge stage-down stat-target">
                                    {{ $tc['stages'] > 0 ? '+' : '' }}{{ $tc['stages'] }} {{ $statLabels[$tc['stat']] ?? $tc['stat'] }}
                                </span>
                            @endforeach
                        @endif
                    </div>
                </button>
            @endforeach
        </div>
    @elseif($phase === 'player_target')
        <div class="alert alert-info target-hint" role="status">
            <strong>Selecciona un objetivo</strong>
            <p class="penalty-note mb-0 small">Retaguardia: -50% daño si hay vanguardia viva</p>
        </div>
    @elseif($phase === 'battle_over')
        <div class="alert alert-success battle-over" role="status">
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

    <div class="mt-3">
        <span class="badge bg-primary round-info">Ronda {{ $round }}</span>
    </div>
</div>