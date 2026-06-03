<div class="pokemon-card 
     {{ $p['alive'] ? '' : 'fainted' }} 
     {{ $p['refId'] === $actingRefId ? 'acting' : '' }}
     {{ $p['refId'] === $animAttackerId ? 'attacker' : '' }}
     {{ $p['refId'] === $animDefenderId ? 'defender' : '' }}
      {{ ($phase === 'player_target' || $phase === 'player_move') && $team === 1 && ($p['canTarget'] ?? true) ? 'selectable' : '' }}
      {{ ($phase === 'player_target' || $phase === 'player_move') && $team === 1 && !($p['canTarget'] ?? true) ? 'blocked' : '' }}
     {{ $p['refId'] === $selectedTargetRefId ? 'targeted' : '' }}"
     @if(($phase === 'player_target' || $phase === 'player_move') && $team === 1 && ($p['canTarget'] ?? true)) wire:click="previewTarget({{ $team }}, {{ $idx }})" @endif
     @if($p['refId'] === $animDefenderId) x-ref="defender-{{ $idx }}" @endif>
    <img src="{{ $p['icon'] }}" alt="{{ $p['nombre'] }}" class="pkmn-icon-img">
    <div class="pkmn-name">{{ $p['nombre'] }}</div>
    @if(!empty($p['item']))
        @php
            $itemNames = ['leftovers' => 'Restos', 'focus_sash' => 'Banda Focus', 'life_orb' => 'Orbe Vida'];
            $itemIcons = ['leftovers' => '🧴', 'focus_sash' => '🎗️', 'life_orb' => '🔮'];
            $itemName = $itemNames[$p['item']] ?? $p['item'];
            $itemIcon = $itemIcons[$p['item']] ?? '📦';
        @endphp
        <div class="item-badge" title="{{ $itemName }}">{{ $itemIcon }}</div>
    @endif
    <div class="hp-bar" title="PS: {{ ceil($p['hp']) }}/{{ $p['maxHp'] }}">
        <div class="hp-fill" style="width: {{ $p['maxHp'] > 0 ? ($p['hp'] / $p['maxHp'] * 100) : 0 }}%"></div>
    </div>
    <div class="barrier-bar" title="Defensa: {{ ceil($p['defHp']) }}/{{ $p['maxDefHp'] }}">
        <div class="barrier-fill def" style="width: {{ $p['maxDefHp'] > 0 ? ($p['defHp'] / $p['maxDefHp'] * 100) : 0 }}%"></div>
    </div>
    <div class="barrier-bar" title="Def. Especial: {{ ceil($p['spDefHp']) }}/{{ $p['maxSpDefHp'] }}">
        <div class="barrier-fill spdef" style="width: {{ $p['maxSpDefHp'] > 0 ? ($p['spDefHp'] / $p['maxSpDefHp'] * 100) : 0 }}%"></div>
    </div>
    <div class="hp-text">{{ ceil($p['hp']) }}/{{ $p['maxHp'] }}</div>
    @if(!empty($p['stages']))
        @php $hasStages = array_filter($p['stages'], fn($v) => $v !== 0); @endphp
        @if(!empty($hasStages))
            <div class="stages-row">
                @foreach($hasStages as $stat => $stage)
                    <span class="stage-badge stage-{{ $stat }} stage-{{ $stage > 0 ? 'up' : 'down' }}">
                        {{ $stage > 0 ? '+' : '' }}{{ $stage }}
                    </span>
                @endforeach
            </div>
        @endif
    @endif
    @if(!empty($p['status']) && $p['status'] !== 'none')
        <div class="status-indicator status-{{ $p['status'] }}" title="{{ \Src\Battle\Domain\Combatiente::STATUS_LABELS[$p['status']] ?? $p['status'] }}{{ $p['statusTurns'] > 0 ? ' ('.$p['statusTurns'].' turnos)' : '' }}">
            @switch($p['status'])
                @case('burn') 🔥 @break
                @case('poison') ☠ @break
                @case('bad_poison') ☠ @break
                @case('paralysis') ⚡ @break
                @case('sleep') 💤 @break
                @case('freeze') ❄ @break
                @case('confusion') 😵 @break
            @endswitch
        </div>
    @endif
</div>
