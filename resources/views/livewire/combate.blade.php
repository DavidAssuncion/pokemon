<div class="container-fluid py-3 battle-container"
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
    <div class="row g-3">
        <div class="col-lg-7">
            {{-- LEFT: CAMPO DE COMBATE --}}
            @include('livewire.partials.battle-field')
        </div>
        <div class="col-lg-5">
            {{-- RIGHT: ATAQUES --}}
            @include('livewire.partials.moves-panel')
        </div>
    </div>

    {{-- BATTLE LOG --}}
    @include('livewire.partials.battle-log')
</div>