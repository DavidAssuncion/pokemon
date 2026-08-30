@php
    $hpPct = $p['maxHp'] > 0 ? ($p['hp'] / $p['maxHp'] * 100) : 0;
    $defPct = $p['maxDefHp'] > 0 ? ($p['defHp'] / $p['maxDefHp'] * 100) : 0;
    $spDefPct = $p['maxSpDefHp'] > 0 ? ($p['spDefHp'] / $p['maxSpDefHp'] * 100) : 0;
    $hpClass = $hpPct <= 25 ? 'bg-hp-low' : ($hpPct <= 50 ? 'bg-hp-mid' : 'bg-hp-high');
    $selectable = ($phase === 'player_target' || $phase === 'player_move') && $team === 1 && ($p['canTarget'] ?? true);
    $blocked = ($phase === 'player_target' || $phase === 'player_move') && $team === 1 && ! ($p['canTarget'] ?? true);
    $itemNames = ['leftovers' => 'Restos', 'focus_sash' => 'Banda Focus', 'life_orb' => 'Orbe Vida'];
    $itemIcons = ['leftovers' => '🧴', 'focus_sash' => '🎗️', 'life_orb' => '🔮'];
    $itemName = $itemNames[$p['item']] ?? $p['item'];
    $itemIcon = $itemIcons[$p['item']] ?? '📦';
@endphp
<div class="card w-100 mb-2 shadow-sm pokemon-card {{ $p['alive'] ? '' : 'opacity-50' }} {{ $p['refId'] === $actingRefId ? 'border border-warning' : '' }} {{ $p['refId'] === $selectedTargetRefId ? 'targeted-card' : '' }} {{ $selectable ? 'cursor-pointer border-primary' : '' }} {{ $blocked ? 'opacity-50 cursor-not-allowed' : '' }}"
     @if($selectable) wire:click="previewTarget({{ $team }}, {{ $idx }})" role="button" tabindex="0" @endif
     @if($p['refId'] === $animDefenderId) x-ref="defender-{{ $idx }}" @endif>
    <div class="card-body p-3 d-flex align-items-center gap-2">
        <img src="{{ $p['icon'] }}" alt="{{ $p['nombre'] }}" class="img-fluid rounded flex-shrink-0" style="width:64px; height:64px; object-fit:contain" loading="lazy">
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex justify-content-between align-items-center gap-1">
                <span class="fw-bold small text-truncate {{ $p['alive'] ? '' : 'text-muted' }}">{{ $p['nombre'] }}</span>
            </div>

            {{-- Barreras (def física y especial) — 50% ancho c/u, arriba de la vida --}}
            <div class="d-flex gap-1 mt-1 min-w-0">
                <div class="w-50" title="Defensa: {{ ceil($p['defHp']) }}/{{ ceil($p['maxDefHp']) }}">
                    <div class="d-flex justify-content-between align-items-center gap-1">
                        <span class="barrier-label small text-muted text-truncate" style="font-size:.6rem; line-height:1">DEF</span>
                        <span class="barrier-num small text-muted flex-shrink-0" style="font-size:.6rem; line-height:1">{{ ceil($p['defHp']) }}/{{ ceil($p['maxDefHp']) }}</span>
                    </div>
                    <div class="progress mt-1" style="height:10px">
                        <div class="progress-bar bg-info" style="width: {{ $defPct }}%" role="progressbar" aria-valuenow="{{ $defPct }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <div class="w-50" title="Def. Especial: {{ ceil($p['spDefHp']) }}/{{ ceil($p['maxSpDefHp']) }}">
                    <div class="d-flex justify-content-between align-items-center gap-1">
                        <span class="barrier-label small text-muted text-truncate" style="font-size:.6rem; line-height:1">DEF.ESP</span>
                        <span class="barrier-num small text-muted flex-shrink-0" style="font-size:.6rem; line-height:1">{{ ceil($p['spDefHp']) }}/{{ ceil($p['maxSpDefHp']) }}</span>
                    </div>
                    <div class="progress mt-1" style="height:10px">
                        <div class="progress-bar bg-primary" style="width: {{ $spDefPct }}%" role="progressbar" aria-valuenow="{{ $spDefPct }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>

            {{-- Vida — debajo de las barreras, con número restante --}}
            <div class="d-flex align-items-center gap-1 mt-1">
                <div class="progress flex-grow-1" style="height:8px" title="PS: {{ ceil($p['hp']) }}/{{ ceil($p['maxHp']) }}">
                    <div class="progress-bar {{ $hpClass }}" style="width: {{ $hpPct }}%" role="progressbar" aria-valuenow="{{ $hpPct }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <span class="hp-num small text-muted flex-shrink-0" style="font-size:.65rem; line-height:1">{{ ceil($p['hp']) }}/{{ ceil($p['maxHp']) }}</span>
            </div>
        </div>
        <div class="flex-shrink-0 d-flex flex-column align-items-end gap-1">
            @if(!empty($p['item']))
                <span class="badge bg-light border text-dark" title="{{ $itemName }}">{{ $itemIcon }}</span>
            @endif
            @if(!empty($p['status']) && $p['status'] !== 'none')
                @php
                    $statusLabel = \Src\Battle\Domain\Enums\EstadoPokemon::tryFrom($p['status'])?->label() ?? $p['status'];
                @endphp
                <span class="badge bg-danger" title="{{ $statusLabel }}{{ $p['statusTurns'] > 0 ? ' ('.$p['statusTurns'].' turnos)' : '' }}">
                    @switch($p['status'])
                        @case('burn') 🔥 @break
                        @case('poison') ☠ @break
                        @case('bad_poison') ☠ @break
                        @case('paralysis') ⚡ @break
                        @case('sleep') 💤 @break
                        @case('freeze') ❄ @break
                        @case('confusion') 😵 @break
                    @endswitch
                </span>
            @endif
            @if(!empty($p['stages']))
                @php $hasStages = array_filter($p['stages'], fn($v) => $v !== 0); @endphp
                @if(!empty($hasStages))
                    @foreach($hasStages as $stat => $stage)
                        <span class="badge {{ $stage > 0 ? 'stage-up' : 'stage-down' }}" title="{{ $stat }}">
                            {{ $stage > 0 ? '+' : '' }}{{ $stage }}
                        </span>
                    @endforeach
                @endif
            @endif
        </div>
    </div>
</div>
