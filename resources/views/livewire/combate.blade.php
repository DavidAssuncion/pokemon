<div>
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
        <div class="col-lg-8 d-flex flex-column gap-3">
            {{-- LEFT: CAMPO DE COMBATE --}}
            @include('livewire.partials.battle-field')
            {{-- BATTLE LOG (debajo del campo, misma columna) --}}
            @include('livewire.partials.battle-log')
        </div>
        <div class="col-lg-4">
            {{-- RIGHT: ATAQUES --}}
            @include('livewire.partials.moves-panel')
        </div>
    </div>
</div>

    {{-- MODAL VICTORIA: recompensas al ganar contra entrenador --}}
    @if($phase === 'battle_over' && !empty($rewards))
        <div class="modal fade show d-block reward-modal" tabindex="-1" role="dialog" aria-modal="true" style="background: rgba(0,0,0,0.6);">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-success fw-bold">🏆 ¡Victoria!</h5>
                    </div>
                    <div class="modal-body">
                        <p class="fw-bold text-success mb-3">Has derrotado al entrenador</p>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>EXP para tu cuenta</span>
                                <strong class="text-success">+{{ number_format((int) ($rewards['exp_total'] ?? 0)) }}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>EXP para cada pokémon</span>
                                <strong class="text-success">+{{ number_format((int) ($rewards['exp_miembro'] ?? 0)) }}</strong>
                            </li>
                            @foreach(($rewards['caramelos'] ?? []) as $caramelo)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="d-flex align-items-center gap-2">
                                        <img src="{{ $caramelo['imagen'] ?? '/images/candy_pokemon/0.webp' }}" alt="{{ $caramelo['nombre'] ?? 'Caramelo' }}" style="width:28px;height:28px;object-fit:contain" loading="lazy">
                                        {{ $caramelo['nombre'] ?? 'Caramelo' }}
                                    </span>
                                    <strong class="text-success">+{{ (int) ($caramelo['cantidad'] ?? 0) }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <a href="/habitats/{{ $habitatId }}" class="btn btn-success w-100 fw-bold">Volver al hábitat</a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>