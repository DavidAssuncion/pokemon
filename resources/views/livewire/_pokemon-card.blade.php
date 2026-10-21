@php
    $hpPct = $p['maxHp'] > 0 ? ($p['hp'] / $p['maxHp'] * 100) : 0;
    $defPct = $p['maxDefHp'] > 0 ? ($p['defHp'] / $p['maxDefHp'] * 100) : 0;
    $spDefPct = $p['maxSpDefHp'] > 0 ? ($p['spDefHp'] / $p['maxSpDefHp'] * 100) : 0;
    $hpClass = $hpPct <= 25 ? 'bg-hp-low' : ($hpPct <= 50 ? 'bg-hp-mid' : 'bg-hp-high');
    // Ventana de animación activa (700 ms): bloquea la selección para que ningún render
    // intermedio cancele la animación ni pise la acción pendiente (ANALISIS_FRONTEND 2026-10-21).
    $ventanaActiva = ($animAttackerId ?? '') !== '';
    $selectable = ($phase === 'player_target' || $phase === 'player_move')
        && $team === 1
        && $p['alive']
        && ($p['canTarget'] ?? true)
        && ! $ventanaActiva;
    $blocked = ($phase === 'player_target' || $phase === 'player_move') && $team === 1 && ! ($p['canTarget'] ?? true);
    $itemNames = ['leftovers' => 'Restos', 'focus_sash' => 'Banda Focus', 'life_orb' => 'Orbe Vida'];
    $itemIcons = ['leftovers' => '🧴', 'focus_sash' => '🎗️', 'life_orb' => '🔮'];
    $itemName = $itemNames[$p['item']] ?? $p['item'];
    $itemIcon = $itemIcons[$p['item']] ?? '📦';
    $esShiny = ($p['es_shiny'] ?? false) || ($p['esShiny'] ?? false) || str_contains((string) ($p['icon'] ?? ''), '/shiny/');
    $iconoNormal = preg_replace('#/shiny/(\d+)[^/]*\.webp$#', '/images/iconos_webp/$1.webp', (string) ($p['icon'] ?? ''));
    $hpBaja = $p['alive'] && $hpPct <= 25;
    $hpCritica = $p['alive'] && $hpPct <= 10;
    $esVanguardia = ($p['posicion'] ?? '') === 'vanguardia';
    $claseVanguardia = $esVanguardia ? ($team === 0 ? 'vanguardia-jugador' : 'vanguardia-rival') : '';
@endphp
<div class="card w-100 mb-2 shadow-sm pokemon-card {{ $claseVanguardia }} {{ $p['alive'] ? '' : 'opacity-50' }} {{ $p['refId'] === $actingRefId ? 'border border-2 border-warning' : '' }} {{ $p['refId'] === $selectedTargetRefId ? 'targeted-card' : '' }} {{ $selectable ? 'cursor-pointer border-primary' : '' }} {{ $blocked ? 'opacity-50 cursor-not-allowed' : '' }} {{ $hpBaja ? 'hp-bajo' : '' }} {{ $hpCritica ? 'hp-critico' : '' }} {{ $p['refId'] === $animAttackerId ? 'anim-ataque' : '' }}"
     data-ref-id="{{ $p['refId'] }}"
     data-team="{{ $team }}"
     @if($selectable) wire:click="previewTarget({{ $team }}, {{ $idx }})" role="button" tabindex="0" @endif>
    <div class="card-body p-2 d-flex flex-column gap-1">
        {{-- Sin nombre: el sprite y las insignias identifican al pokémon --}}
        <div class="d-flex flex-wrap align-items-center gap-1">
            @if(! empty($p['cp']))
                <span class="badge bg-purple-100 text-purple-700 small" title="Poder de combate">CP {{ number_format($p['cp']) }}</span>
            @endif
            @if(!empty($p['item']))
                <span class="badge bg-light border text-dark" title="{{ $itemName }}">{{ $itemIcon }}</span>
            @endif
            @if(!empty($p['status']) && $p['status'] !== 'none')
                @php
                    $statusLabel = \Src\Battle\Domain\Enums\EstadoPokemon::tryFrom($p['status'])?->label() ?? $p['status'];
                @endphp
                <span class="badge bg-danger status-badge" title="{{ $statusLabel }}{{ $p['statusTurns'] > 0 ? ' ('.$p['statusTurns'].' turnos)' : '' }}">
                    @switch($p['status'])
                        @case('burn')
                            <img src="/images/type/fuego.webp" alt="Quemadura" style="width:20px;height:20px;object-fit:contain">
                        @break
                        @case('poison')
                        @case('bad_poison')
                            <img src="/images/type/veneno.webp" alt="Envenenamiento" style="width:20px;height:20px;object-fit:contain">
                        @break
                        @case('paralysis')
                            <img src="/images/type/electrico.webp" alt="Parálisis" style="width:20px;height:20px;object-fit:contain">
                        @break
                        @case('sleep') 💤 @break
                        @case('freeze') ❄ @break
                        @case('confusion') 😵 @break
                    @endswitch
                </span>
            @endif
            @if(! $p['alive'])
                <span class="badge bg-dark ko-badge">K.O.</span>
            @endif
            @if(!empty($p['stages']))
                @php $hasStages = array_filter($p['stages'], fn($v) => $v !== 0); @endphp
                @if(!empty($hasStages))
                    @foreach($hasStages as $stat => $stage)
                        @php
                            $statLabel = \Src\Battle\Domain\Enums\StatClave::tryFrom($stat)?->label() ?? $stat;
                        @endphp
                        <span class="badge {{ $stage > 0 ? 'stage-up' : 'stage-down' }}"
                              title="{{ $statLabel }} {{ $stage > 0 ? 'subió' : 'bajó' }} {{ abs($stage) }}">
                            {{ $stage > 0 ? '+' : '' }}{{ $stage }} {{ $statLabel }}
                        </span>
                    @endforeach
                @endif
            @endif
        </div>

        {{-- Barreras (def física y especial) — texto encima y barra a todo el ancho --}}
        <div class="d-flex gap-2 min-w-0">
            <div class="flex-fill min-w-0" title="Defensa: {{ ceil($p['defHp']) }}/{{ ceil($p['maxDefHp']) }}">
                <div class="vital-label-row">
                    <span class="vital-label text-truncate">DEF</span>
                    <span class="vital-value vital-def-num">{{ ceil($p['defHp']) }}/{{ ceil($p['maxDefHp']) }}</span>
                </div>
                <div class="progress barrier-progress">
                    <div class="progress-bar progress-bar-trail vital-def-trail" aria-hidden="true"></div>
                    <div class="progress-bar bg-info vital-def" style="width: {{ $defPct }}%" role="progressbar" aria-valuenow="{{ $defPct }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
            <div class="flex-fill min-w-0" title="Def. Especial: {{ ceil($p['spDefHp']) }}/{{ ceil($p['maxSpDefHp']) }}">
                <div class="vital-label-row">
                    <span class="vital-label text-truncate">DEF.ESP</span>
                    <span class="vital-value vital-spdef-num">{{ ceil($p['spDefHp']) }}/{{ ceil($p['maxSpDefHp']) }}</span>
                </div>
                <div class="progress barrier-progress">
                    <div class="progress-bar progress-bar-trail vital-spdef-trail" aria-hidden="true"></div>
                    <div class="progress-bar bg-primary vital-spdef" style="width: {{ $spDefPct }}%" role="progressbar" aria-valuenow="{{ $spDefPct }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>

        {{-- Vida — texto encima y barra a todo el ancho --}}
        <div title="PS: {{ ceil($p['hp']) }}/{{ ceil($p['maxHp']) }}">
            <div class="vital-label-row">
                <span class="vital-label">PS</span>
                <span class="vital-value vital-hp-num">{{ ceil($p['hp']) }}/{{ ceil($p['maxHp']) }}</span>
            </div>
            <div class="progress hp-progress">
                <div class="progress-bar progress-bar-trail vital-hp-trail" aria-hidden="true"></div>
                <div class="progress-bar {{ $hpClass }} vital-hp" style="width: {{ $hpPct }}%" role="progressbar" aria-valuenow="{{ $hpPct }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>

        {{-- El pokémon va debajo de las barras, centrado --}}
        <div class="text-center pt-1">
            <img src="{{ $p['icon'] }}" alt="{{ $p['nombre'] }}" class="img-fluid rounded pokemon-sprite" style="width:72px; height:72px; object-fit:contain; margin: 0 auto;" loading="lazy" @if($esShiny) onerror="this.onerror=null; this.src='{{ $iconoNormal }}';" @endif>
        </div>
    </div>
</div>
