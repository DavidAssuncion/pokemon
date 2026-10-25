<div class="battle-field">
    @if($phase === 'battle_over')
        <div class="alert {{ $battleField['claseFin'] }} fin-batalla {{ $battleField['ocultarFin'] ? 'd-none' : '' }} text-center fw-bold py-2 mb-2" role="status">
            {{ $battleField['victoria'] ? '🏆 ¡Victoria!' : '💀 Derrota...' }}
        </div>
    @endif

    @if($battleField['clima'] !== null)
        <div class="alert {{ $battleField['clima']['variante'] }} weather-{{ $battleField['clima']['tipo'] }} py-1 px-3 mb-2" role="status">
            <span class="weather-label fw-semibold">{{ $battleField['clima']['icono'] }} {{ $battleField['clima']['label'] }}</span>
            @if($battleField['clima']['mostrarBonusRoca'])
                <span class="weather-power d-block small">(+500 potencia a movimientos Roca)</span>
            @endif
        </div>
    @endif

    @if($battleField['mostrarAnimacion'])
        <div class="alert alert-info py-1 px-3 mb-2 d-flex align-items-center gap-2 flex-wrap" role="status">
            <span class="anim-attacker fw-bold">{{ $battleField['animAttackerNombre'] }}</span>
            <span class="anim-arrow">→</span>
            <span class="anim-move fst-italic">{{ $battleField['animMoveNombre'] !== '' ? $battleField['animMoveNombre'] : 'ataca' }}</span>
            <span class="anim-arrow">→</span>
            <span class="anim-defender fw-bold">{{ $battleField['animDefenderNombre'] }}</span>
        </div>
    @endif

    {{-- Una sola fila de 4 columnas; la línea central separa los equipos. --}}
    <div class="battle-teams">
        <div class="row g-2">
            {{-- COL 1: Jugador — Retaguardia --}}
            <div class="col-6 col-md-3 d-flex flex-column">
                <div class="pokemon-column">
                    @foreach($team1Vista as $idx => $p)
                        @if($p['posicion'] === 'retaguardia')
                            @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx])
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- COL 2: Jugador — Vanguardia --}}
            <div class="col-6 col-md-3 d-flex flex-column">
                <div class="pokemon-column">
                    @foreach($team1Vista as $idx => $p)
                        @if($p['posicion'] === 'vanguardia')
                            @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx])
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- COL 3: Rival — Vanguardia --}}
            <div class="col-6 col-md-3 d-flex flex-column">
                <div class="pokemon-column">
                    @foreach($team2Vista as $idx => $p)
                        @if($p['posicion'] === 'vanguardia')
                            @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx])
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- COL 4: Rival — Retaguardia --}}
            <div class="col-6 col-md-3 d-flex flex-column">
                <div class="pokemon-column">
                    @foreach($team2Vista as $idx => $p)
                        @if($p['posicion'] === 'retaguardia')
                            @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx])
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <div class="battle-teams-divider" aria-hidden="true"></div>
    </div>
</div>
