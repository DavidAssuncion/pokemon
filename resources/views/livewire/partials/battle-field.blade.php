<div class="battle-field">
    <h3 class="section-title">CAMPO DE COMBATE</h3>

    @if($weather && $weather !== 'none')
        @php
            $clima = \Src\Battle\Domain\Enums\TipoClima::tryFrom($weather);
            $climaIcono = match ($weather) {
                'sequia' => '☀️',
                'diluvio' => '🌧',
                'niebla' => '🌫',
                'granizo' => '❄️',
                'tormenta_arena' => '🌪',
                'turbulencias' => '💨',
                default => '🌤',
            };
        @endphp
        <div class="weather-banner weather-{{ $weather }} mb-2 rounded-lg border px-3 py-1.5 text-center text-sm font-semibold">
            <span class="weather-label">{{ $climaIcono }} {{ $clima?->label() ?? $weather }}</span>
            @if($weather === 'tormenta_arena')
                <span class="weather-power">(+500 potencia a movimientos Roca)</span>
            @endif
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
