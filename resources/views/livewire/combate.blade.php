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
        <div class="col-lg-8">
            {{-- LEFT: CAMPO DE COMBATE --}}
            @include('livewire.partials.battle-field')
        </div>
        <div class="col-lg-4">
            {{-- RIGHT: ATAQUES --}}
            @include('livewire.partials.moves-panel')
        </div>
    </div>

    {{-- BATTLE LOG (50% del ancho, debajo del combate) --}}
    <div class="row g-3 mt-0">
        <div class="col-lg-6">
            @include('livewire.partials.battle-log')
        </div>
    </div>
</div>