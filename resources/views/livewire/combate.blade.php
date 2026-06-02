<div class="battle-container"
     wire:key="battle-{{ $round }}"
     x-init="$nextTick(() => {
         let timer = null;
         $wire.$watch('animTick', () => {
             if (timer) clearTimeout(timer);
             if (!$wire.get('animAttackerId')) return;
             timer = setTimeout(() => $wire.commitAction(), 700);
         });
         if ($wire.get('animAttackerId')) {
             timer = setTimeout(() => $wire.commitAction(), 700);
         }
     })">

    {{-- TOP: TURN ORDER --}}
    <div class="turn-bar">
        <div class="turn-label">PRÓXIMOS TURNOS</div>
        <div class="turn-icons">
            @forelse($turnQueue as $idx => $turn)
                @php $p = $turn['team'] === 0 ? ($team1[$turn['index']] ?? null) : ($team2[$turn['index']] ?? null); @endphp
                @if($p && $p['alive'])
                    <div class="turn-icon {{ $idx === 0 ? 'active' : '' }} {{ $p['refId'] === $actingRefId ? 'acting' : '' }}" title="{{ $p['nombre'] }}">
                        <img src="{{ $p['icon'] }}" alt="{{ $p['nombre'] }}" class="turn-icon-img">
                        <small>{{ $p['accumulatedSpeed'] }}</small>
                    </div>
                @endif
            @empty
                <span class="text-muted">Esperando...</span>
            @endforelse
        </div>
    </div>

    {{-- MAIN: CAMPO + ATAQUES --}}
    <div class="battle-main">
        {{-- LEFT: CAMPO DE COMBATE --}}
        <div class="battle-field">
            <h3 class="section-title">CAMPO DE COMBATE</h3>
            @if($phase === 'animating' && $animAttackerNombre && $animDefenderNombre)
                <div class="anim-banner">
                    <span class="anim-attacker">{{ $animAttackerNombre }}</span>
                    <span class="anim-arrow">→</span>
                    <span class="anim-move">{{ $animMoveNombre }}</span>
                    <span class="anim-arrow">→</span>
                    <span class="anim-defender">{{ $animDefenderNombre }}</span>
                </div>
            @endif
            <div class="field-grid">
                {{-- TEAM 1 (PLAYER) --}}
                <div class="team-column">
                    <div class="team-header">Tú</div>
                    <div class="position-row">
                        <div class="position-column">
                            <div class="position-label">RETAGUARDIA</div>
                            @foreach($team1 as $idx => $p)
                                @if($p['posicion'] === 'retaguardia')
                                    @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 0])
                                @endif
                            @endforeach
                        </div>
                        <div class="position-column">
                            <div class="position-label">VANGUARDIA</div>
                            @foreach($team1 as $idx => $p)
                                @if($p['posicion'] === 'vanguardia')
                                    @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 0])
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="vs-divider">VS</div>

                {{-- TEAM 2 (ENEMY) --}}
                <div class="team-column">
                    <div class="team-header">Rival</div>
                    <div class="position-row">
                        <div class="position-column">
                            <div class="position-label">VANGUARDIA</div>
                            @foreach($team2 as $idx => $p)
                                @if($p['posicion'] === 'vanguardia')
                                    @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 1])
                                @endif
                            @endforeach
                        </div>
                        <div class="position-column">
                            <div class="position-label">RETAGUARDIA</div>
                            @foreach($team2 as $idx => $p)
                                @if($p['posicion'] === 'retaguardia')
                                    @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 1])
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: ATAQUES --}}
        <div class="moves-panel">
            <h3 class="section-title">ATAQUES</h3>
            @if($phase === 'player_move')
                <div class="move-list">
                    @foreach($currentMoves as $idx => $move)
                        <button class="move-btn" wire:click="selectMove({{ $idx }})">
                            <span class="move-name">{{ $move['nombre'] }}</span>
                            <span class="move-type type-{{ $move['tipo'] }}">{{ \Src\Shared\Tipos\TipoPokemon::from($move['tipo'])->name }}</span>
                            <span class="move-power">P. {{ $move['potencia'] }}</span>
                            <span class="move-cat">{{ $move['categoria'] }}</span>
                            <span class="move-dmg">{{ $move['daño'] }} daño</span>
                            <span class="move-multipliers">
                                @if($move['stab'])
                                    <span class="stab-badge">STAB</span>
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
                <p class="hint">Haz clic en otro objetivo para cambiar</p>
            @elseif($phase === 'player_target')
                <div class="target-hint">
                    <strong>Selecciona un objetivo</strong>
                    <p>Haz clic en un Pokémon enemigo</p>
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
    </div>

    {{-- BATTLE LOG --}}
    <div class="battle-log">
        <h4>Registro de batalla</h4>
        <div class="log-entries">
            @foreach(array_slice($log, -10) as $entry)
                <div class="log-entry">{{ $entry }}</div>
            @endforeach
        </div>
    </div>
</div>
