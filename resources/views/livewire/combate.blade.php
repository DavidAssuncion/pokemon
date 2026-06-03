<div class="battle-container"
     wire:key="battle-{{ $round }}"
     x-init="$nextTick(() => {
         let timer = null;
         $wire.$watch('animTick', () => {
             if (timer) clearTimeout(timer);
             if (!$wire.get('animAttackerId')) return;
             timer = setTimeout(() => $wire.commitAction(), 700);
         });
         if ($wire.get('animAttackerId')) {
             timer = setTimeout(() => $wire.commitAction(), 700);
         }
     })">

    {{-- TOP: TURN ORDER --}}
    @include('livewire.partials.turn-bar')

    {{-- MAIN: CAMPO + ATAQUES --}}
    <div class="battle-main">
        {{-- LEFT: CAMPO DE COMBATE --}}
        @include('livewire.partials.battle-field')

        {{-- RIGHT: ATAQUES --}}
        @include('livewire.partials.moves-panel')
    </div>

    {{-- BATTLE LOG --}}
    @include('livewire.partials.battle-log')
</div>
