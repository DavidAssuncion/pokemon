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
</div>
