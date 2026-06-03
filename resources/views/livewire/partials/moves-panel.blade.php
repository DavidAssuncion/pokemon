<div class="moves-panel">
    <h3 class="section-title">ATAQUES</h3>

    @php
        $statLabels = ['attack' => 'Ataque', 'defense' => 'Defensa', 'spAtk' => 'At.Esp', 'spDef' => 'Def.Esp', 'speed' => 'Vel'];
    @endphp

    @if($phase === 'player_move')
        <div class="move-list">
            @foreach($currentMoves as $idx => $move)
                <button class="move-btn" wire:click="selectMove({{ $idx }})">
                    <span class="move-name">{{ $move['nombre'] }}</span>
                    <span class="move-type type-{{ $move['tipo'] }}">{{ \Src\Shared\Tipos\TipoPokemon::from($move['tipo'])->name }}</span>
                    @if($move['potencia'] > 0)
                        <span class="move-power">P. {{ $move['potencia'] }}</span>
                    @endif
                    <span class="move-cat">{{ $move['categoria'] }}</span>
                    @if($move['potencia'] > 0)
                        <span class="move-dmg">{{ $move['daño'] }} daño</span>
                    @endif
                    <span class="move-multipliers">
                        @if($move['stab'])
                            <span class="stab-badge">STAB</span>
                        @endif
                        @if($move['directo'] ?? false)
                            <span class="directo-badge">DIRECTO</span>
                        @endif
                        @if($move['statusEffect'] ?? false)
                            <span class="status-badge status-{{ $move['statusEffect'] }}">
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
                                <span class="stat-badge stat-self">
                                    {{ $sc['stages'] > 0 ? '+' : '' }}{{ $sc['stages'] }} {{ $statLabels[$sc['stat']] ?? $sc['stat'] }}
                                </span>
                            @endforeach
                        @endif
                        @if(!empty($move['targetStatChanges']))
                            @foreach($move['targetStatChanges'] as $tc)
                                <span class="stat-badge stat-target">
                                    {{ $tc['stages'] > 0 ? '+' : '' }}{{ $tc['stages'] }} {{ $statLabels[$tc['stat']] ?? $tc['stat'] }}
                                </span>
                            @endforeach
                        @endif
                        @if($move['efectividad'] > 1)
                            <span class="efectividad efectividad-alta">×{{ number_format($move['efectividad'], 1) }}</span>
                        @elseif($move['efectividad'] <= 0)
                            <span class="efectividad efectividad-cero">×0</span>
                        @elseif($move['efectividad'] < 1)
                            <span class="efectividad efectividad-baja">×{{ number_format($move['efectividad'], 1) }}</span>
                        @endif
                    </span>
                </button>
            @endforeach
        </div>
    @elseif($phase === 'player_target')
        <div class="target-hint">
            <strong>Selecciona un objetivo</strong>
            <p class="penalty-note">Retaguardia: -50% daño si hay vanguardia viva</p>
        </div>
    @elseif($phase === 'battle_over')
        <div class="battle-over">
            ¡Batalla terminada!
        </div>
    @elseif($phase === 'animating')
        <div class="waiting">
            <p>⚔ Ejecutando turno...</p>
        </div>
    @else
        <div class="waiting">
            <p>Ejecutando turno...</p>
        </div>
    @endif

    <div class="round-info">
        Ronda {{ $round }}
    </div>
</div>
