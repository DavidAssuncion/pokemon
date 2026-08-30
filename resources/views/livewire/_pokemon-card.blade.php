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
<div class="card mb-2 shadow-sm pokemon-card {{ $p['alive'] ? '' : 'opacity-50' }} {{ $p['refId'] === $actingRefId ? 'border border-warning' : '' }} {{ $p['refId'] === $selectedTargetRefId ? 'border border-primary' : '' }} {{ $selectable ? 'cursor-pointer border-primary' : '' }} {{ $blocked ? 'opacity-50 cursor-not-allowed' : '' }}"
     @if($selectable) wire:click="previewTarget({{ $team }}, {{ $idx }})" role="button" tabindex="0" @endif
     @if($p['refId'] === $animDefenderId) x-ref="defender-{{ $idx }}" @endif>
    <div class="card-body p-2 d-flex align-items-center gap-2">
        <img src="{{ $p['icon'] }}" alt="{{ $p['nombre'] }}" class="img-fluid rounded flex-shrink-0" style="width:64px; height:64px; object-fit:contain" loading="lazy">
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex justify-content-between align-items-center gap-1">
                <span class="fw-bold small text-truncate {{ $p['alive'] ? '' : 'text-muted' }}">{{ $p['nombre'] }}</span>
                <span class="small text-muted flex-shrink-0">{{ ceil($p['hp']) }}/{{ $p['maxHp'] }}</span>
            </div>
            <div class="progress mt-1" style="height:8px" title="PS: {{ ceil($p['hp']) }}/{{ $p['maxHp'] }}">
                <div class="progress-bar {{ $hpClass }}" style="width: {{ $hpPct }}%" role="progressbar" aria-valuenow="{{ $hpPct }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="progress mt-1" style="height:4px" title="Defensa: {{ ceil($p['defHp']) }}/{{ $p['maxDefHp'] }}">
                <div class="progress-bar bg-info" style="width: {{ $defPct }}%" role="progressbar" aria-valuenow="{{ $defPct }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="progress mt-1" style="height:4px" title="Def. Especial: {{ ceil($p['spDefHp']) }}/{{ $p['maxSpDefHp'] }}">
                <div class="progress-bar bg-primary" style="width: {{ $spDefPct }}%" role="progressbar" aria-valuenow="{{ $spDefPct }}" aria-valuemin="0" aria-valuemax="100"></div>
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
