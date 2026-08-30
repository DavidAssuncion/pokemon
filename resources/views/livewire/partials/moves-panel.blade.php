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
    @endphp

    @if($phase === 'player_move')
        <div class="d-grid gap-2">
            @foreach($currentMoves as $idx => $move)
                @php
                    $tipoValue = (int) $move['tipo'];
                    $tipoLabel = \Src\Shared\Tipos\TipoPokemon::tryFrom($tipoValue)?->label() ?? $move['tipo'];
                    $tipoColor = $tipoColors[$tipoValue] ?? 'secondary';
                    $eff = (float) $move['efectividad'];
                    $effText = $eff == (int) $eff ? (string) (int) $eff : number_format($eff, 1);
                    $effColor = $eff <= 0 ? 'dark' : ($eff < 1 ? 'warning' : ($eff <= 1 ? 'light border' : 'success'));
                    $catLabel = match ($move['categoria']) { 'fisico' => 'Físico', 'especial' => 'Especial', default => 'Estado' };
                    $catColor = match ($move['categoria']) { 'fisico' => 'danger', 'especial' => 'primary', default => 'secondary' };
                @endphp
                <button class="btn btn-outline-primary w-100 text-start" wire:click="selectMove({{ $idx }})">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <span class="fw-bold move-name">{{ $move['nombre'] }}</span>
                        <span class="badge text-bg-{{ $tipoColor }} move-type">{{ $tipoLabel }}</span>
                    </div>
                    <div class="d-flex flex-wrap gap-1 align-items-center mt-1 small">
                        <span class="badge text-bg-{{ $catColor }} move-cat">{{ $catLabel }}</span>
                        @if($move['potencia'] > 0)
                            <span class="badge text-bg-dark move-power">Pot. {{ $move['potencia'] }}</span>
                        @endif
                        @if($move['potencia'] > 0)
                            <span class="badge text-bg-dark move-dmg">{{ ceil($move['daño']) }} daño</span>
                        @endif
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
