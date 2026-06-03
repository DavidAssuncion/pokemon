<div class="battle-field">
    <h3 class="section-title">CAMPO DE COMBATE</h3>

    @if($weather && $weather !== 'none')
        <div class="weather-banner weather-{{ $weather }}">
            @switch($weather)
                @case('sandstorm')
                    🌪 Tormenta de arena <span class="weather-power">(+500 potencia a movimientos Roca)</span>
                    @break
                @case('sun')
                    ☀️ Día soleado
                    @break
                @case('rain')
                    🌧 Lluvia
                    @break
                @case('hail')
                    ❄️ Granizo
                    @break
            @endswitch
        </div>
    @endif

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
