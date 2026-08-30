<div class="battle-field">
    <h3 class="h5 mb-3">Campo de Combate</h3>

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

    <div class="row g-3">
        {{-- TEAM 1 (PLAYER) --}}
        <div class="col-md-6 team-column">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary fw-semibold">Tú</div>
                <div class="card-body">
                    <div class="position-label text-uppercase small text-muted fw-semibold mb-1">Retaguardia</div>
                    @foreach($team1 as $idx => $p)
                        @if($p['posicion'] === 'retaguardia')
                            @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 0])
                        @endif
                    @endforeach
                    <div class="position-label text-uppercase small text-muted fw-semibold mt-3 mb-1">Vanguardia</div>
                    @foreach($team1 as $idx => $p)
                        @if($p['posicion'] === 'vanguardia')
                            @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 0])
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- TEAM 2 (ENEMY) --}}
        <div class="col-md-6 team-column">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary fw-semibold">Rival</div>
                <div class="card-body">
                    <div class="position-label text-uppercase small text-muted fw-semibold mb-1">Retaguardia</div>
                    @foreach($team2 as $idx => $p)
                        @if($p['posicion'] === 'retaguardia')
                            @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 1])
                        @endif
                    @endforeach
                    <div class="position-label text-uppercase small text-muted fw-semibold mt-3 mb-1">Vanguardia</div>
                    @foreach($team2 as $idx => $p)
                        @if($p['posicion'] === 'vanguardia')
                            @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 1])
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
