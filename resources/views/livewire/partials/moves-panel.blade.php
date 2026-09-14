<div class="moves-panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h5 mb-0">Ataques</h3>
        <span class="badge bg-primary round-info">Ronda {{ $round }}</span>
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

    @if($phase === 'player_move')
        <div class="d-grid gap-2">
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
                    $catLabel = match ($move['categoria']) { 'fisico' => 'Físico', 'especial' => 'Especial', default => 'Estado' };
                    $catColor = match ($move['categoria']) { 'fisico' => 'danger', 'especial' => 'primary', default => 'secondary' };
                    $isBest = $move['potencia'] > 0 && $maxDmg > 0 && (float) $move['daño'] >= $maxDmg;
                @endphp
                <button class="btn btn-outline-primary w-100 text-start position-relative {{ $isBest ? 'border-primary border-2 shadow-sm' : '' }}"
                        style="background-color: {{ $tipoColor }}; color: #fff"
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
                        <span class="badge move-type" style="background-color: {{ $tipoColor }}; color: #fff">{{ $tipoLabel }}</span>
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
                                @php
                                    $pct = (int) round((($sc['factor'] ?? 1.0) - 1) * 100);
                                @endphp
                                <span class="badge stage-up stat-self">
                                    {{ $pct > 0 ? '+' : '' }}{{ $pct }}% {{ $statLabels[$sc['stat']] ?? $sc['stat'] }}
                                </span>
                            @endforeach
                        @endif
                        @if(!empty($move['targetStatChanges']))
                            @foreach($move['targetStatChanges'] as $tc)
                                @php
                                    $pct = (int) round((($tc['factor'] ?? 1.0) - 1) * 100);
                                @endphp
                                <span class="badge stage-down stat-target">
                                    {{ $pct > 0 ? '+' : '' }}{{ $pct }}% {{ $statLabels[$tc['stat']] ?? $tc['stat'] }}
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
</div>