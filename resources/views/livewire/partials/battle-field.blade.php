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

    {{-- 4 columnas: Tú Retaguardia | Tú Vanguardia | Rival Vanguardia | Rival Retaguardia --}}
    <div class="row g-2">
        {{-- COL 1: Tú — Retaguardia --}}
        <div class="col-6 col-md-3">
            <div class="position-label text-uppercase small text-muted fw-semibold mb-1">Tú · Retaguardia</div>
            <div class="d-flex flex-column align-items-center">
                @foreach($team1 as $idx => $p)
                    @if($p['posicion'] === 'retaguardia')
                        @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 0])
                    @endif
                @endforeach
            </div>
        </div>

        {{-- COL 2: Tú — Vanguardia --}}
        <div class="col-6 col-md-3">
            <div class="position-label text-uppercase small text-muted fw-semibold mb-1">Tú · Vanguardia</div>
            <div class="d-flex flex-column align-items-center">
                @foreach($team1 as $idx => $p)
                    @if($p['posicion'] === 'vanguardia')
                        @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 0])
                    @endif
                @endforeach
            </div>
        </div>

        {{-- COL 3: Rival — Vanguardia --}}
        <div class="col-6 col-md-3">
            <div class="position-label text-uppercase small text-muted fw-semibold mb-1">Rival · Vanguardia</div>
            <div class="d-flex flex-column align-items-center">
                @foreach($team2 as $idx => $p)
                    @if($p['posicion'] === 'vanguardia')
                        @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 1])
                    @endif
                @endforeach
            </div>
        </div>

        {{-- COL 4: Rival — Retaguardia --}}
        <div class="col-6 col-md-3">
            <div class="position-label text-uppercase small text-muted fw-semibold mb-1">Rival · Retaguardia</div>
            <div class="d-flex flex-column align-items-center">
                @foreach($team2 as $idx => $p)
                    @if($p['posicion'] === 'retaguardia')
                        @include('livewire._pokemon-card', ['p' => $p, 'idx' => $idx, 'team' => 1])
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>