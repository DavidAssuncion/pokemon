@php
    $climaTipo = $battleField['clima']['tipo'] ?? '';
    $climaVideo = match ($climaTipo) {
        'tormenta_arena' => ['base' => 'weather-gen6-sandstorm', 'poster' => 'weather-sandstorm.png'],
        'diluvio' => ['base' => 'weather-gen6-raindance', 'poster' => 'weather-raindance.jpg'],
        'granizo' => ['base' => 'weather-gen6-hail', 'poster' => 'weather-hail.png'],
        'sequia' => ['base' => 'weather-gen6-sunnyday', 'poster' => 'weather-sunnyday.jpg'],
        default => null,
    };
@endphp

<div class="battle-field">
    @if($climaVideo !== null || $battleField['clima'] !== null)
        {{-- La clave por clima fuerza a Livewire a reemplazar el bloque cuando
             cambia el tiempo; si no, el navegador sigue reproduciendo el vídeo
             anterior (p. ej. granizo después de pasar a tormenta de arena). --}}
        <div class="battle-weather-layer" wire:key="clima-{{ $climaTipo }}" aria-hidden="true">
            @if($climaVideo !== null)
                <video class="battle-weather-video battle-weather-video--{{ $climaTipo }}"
                       autoplay
                       muted
                       loop
                       playsinline
                       preload="metadata"
                       poster="/showdown/fx/{{ $climaVideo['poster'] }}">
                    <source src="/showdown/fx/{{ $climaVideo['base'] }}.webm" type="video/webm">
                    <source src="/showdown/fx/{{ $climaVideo['base'] }}.mp4" type="video/mp4">
                </video>
            @endif

            @if($battleField['clima'] !== null)
                <div class="battle-weather-effect weather-{{ $battleField['clima']['tipo'] }}"></div>
            @endif
        </div>
    @endif

    @if($trampas1 !== [])
        <div class="battle-hazards battle-hazards--player" aria-label="Trampas del equipo">
            @foreach($trampas1 as $trampa)
                @php($iconoTrampa = match ($trampa['clave']) {
                    'puas' => 'tierra',
                    'puas_toxicas' => 'veneno',
                    'trampa_rocas' => 'roca',
                    default => 'normal',
                })
                <span class="battle-hazard battle-hazard--{{ $trampa['clave'] }}" title="{{ $trampa['label'] }} · {{ $trampa['acumulaciones'] }}/3">
                    <img src="/images/type/{{ $iconoTrampa }}.webp" alt="" loading="lazy" class="battle-hazard__icon">
                    <span>{{ $trampa['label'] }}</span>
                    @if($trampa['acumulaciones'] > 1)
                        <span class="battle-hazard__count">×{{ $trampa['acumulaciones'] }}</span>
                    @endif
                </span>
            @endforeach
        </div>
    @endif

    @if($trampas2 !== [])
        <div class="battle-hazards battle-hazards--rival" aria-label="Trampas del rival">
            @foreach($trampas2 as $trampa)
                @php($iconoTrampa = match ($trampa['clave']) {
                    'puas' => 'tierra',
                    'puas_toxicas' => 'veneno',
                    'trampa_rocas' => 'roca',
                    default => 'normal',
                })
                <span class="battle-hazard battle-hazard--{{ $trampa['clave'] }}" title="{{ $trampa['label'] }} · {{ $trampa['acumulaciones'] }}/3">
                    <img src="/images/type/{{ $iconoTrampa }}.webp" alt="" loading="lazy" class="battle-hazard__icon">
                    <span>{{ $trampa['label'] }}</span>
                    @if($trampa['acumulaciones'] > 1)
                        <span class="battle-hazard__count">×{{ $trampa['acumulaciones'] }}</span>
                    @endif
                </span>
            @endforeach
        </div>
    @endif

    @if($phase === 'battle_over')
        <div class="alert {{ $battleField['claseFin'] }} fin-batalla {{ $battleField['ocultarFin'] ? 'd-none' : '' }} text-center fw-bold py-2 mb-2" role="status">
            {{ $battleField['victoria'] ? '¡Victoria!' : 'Derrota...' }}
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
