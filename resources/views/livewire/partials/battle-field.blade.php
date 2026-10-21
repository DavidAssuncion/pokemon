<div class="battle-field">
    @if($phase === 'battle_over')
        @php
            $victoria = collect($team2)->every(fn ($p) => ! $p['alive'])
                && ! collect($team1)->every(fn ($p) => ! $p['alive']);
        @endphp
        <div class="alert {{ $victoria ? 'alert-success' : 'alert-danger' }} fin-batalla {{ !empty($eventos) ? 'd-none' : '' }} text-center fw-bold py-2 mb-2" role="status">
            {{ $victoria ? '🏆 ¡Victoria!' : '💀 Derrota...' }}
        </div>
    @endif

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
            $climaVariant = in_array($weather, ['sequia', 'tormenta_arena'], true) ? 'alert-warning' : 'alert-info';
        @endphp
        <div class="alert {{ $climaVariant }} weather-{{ $weather }} py-1 px-3 mb-2" role="status">
            <span class="weather-label fw-semibold">{{ $climaIcono }} {{ $clima?->label() ?? $weather }}</span>
            @if($weather === 'tormenta_arena')
                <span class="weather-power d-block small">(+500 potencia a movimientos Roca)</span>
            @endif
        </div>
    @endif

    @if($phase === 'animating' && $animAttackerNombre && $animDefenderNombre)
        <div class="alert alert-info py-1 px-3 mb-2 d-flex align-items-center gap-2 flex-wrap" role="status">
            <span class="anim-attacker fw-bold">{{ $animAttackerNombre }}</span>
            <span class="anim-arrow">→</span>
            <span class="anim-move fst-italic">{{ $animMoveNombre !== '' ? $animMoveNombre : 'ataca' }}</span>
            <span class="anim-arrow">→</span>
            <span class="anim-defender fw-bold">{{ $animDefenderNombre }}</span>
        </div>
    @endif

    {{-- Una sola fila de 4 columnas; la línea central separa los equipos. --}}
    <div class="battle-teams">
        <div class="row g-2">
            {{-- COL 1: Jugador — Retaguardia --}}
            <div class="col-6 col-md-3 d-flex flex-column">
                <div class="pokemon-column">
                    @foreach($team1 as $idx => $p)
                        @if($p['posicion'] === 'retaguardia')
                            @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 0])
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- COL 2: Jugador — Vanguardia --}}
            <div class="col-6 col-md-3 d-flex flex-column">
                <div class="pokemon-column">
                    @foreach($team1 as $idx => $p)
                        @if($p['posicion'] === 'vanguardia')
                            @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 0])
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- COL 3: Rival — Vanguardia --}}
            <div class="col-6 col-md-3 d-flex flex-column">
                <div class="pokemon-column">
                    @foreach($team2 as $idx => $p)
                        @if($p['posicion'] === 'vanguardia')
                            @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 1])
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- COL 4: Rival — Retaguardia --}}
            <div class="col-6 col-md-3 d-flex flex-column">
                <div class="pokemon-column">
                    @foreach($team2 as $idx => $p)
                        @if($p['posicion'] === 'retaguardia')
                            @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 1])
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <div class="battle-teams-divider" aria-hidden="true"></div>
    </div>
</div>