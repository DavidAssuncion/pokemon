<div>
@php
    $candyFallback = "this.src='/images/candy_pokemon/0.webp'; this.onerror=null;";
@endphp
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
                        @php
                            // Ruta: las recompensas vienen agrupadas (familia/EV/tipo) + capturas.
                            // Entrenador/gimnasio: lista plana + medalla opcional.
                            $rutaRecompensas = !empty($rewards['caramelos_familia'])
                                || !empty($rewards['caramelos_ev'])
                                || !empty($rewards['caramelos_tipo'])
                                || !empty($rewards['capturas']);
                        @endphp

                        @if($rutaRecompensas)
                            {{-- RUTA: recompensas en grid Tailwind (mismo patrón que exploraciones) --}}
                            <p class="fw-bold text-success mb-3">¡Has derrotado a los pokémon salvajes!</p>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>EXP para tu cuenta</span>
                                <strong class="text-success">+{{ number_format((int) ($rewards['exp_total'] ?? 0)) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>EXP para cada pokémon</span>
                                <strong class="text-success">+{{ number_format((int) ($rewards['exp_miembro'] ?? 0)) }}</strong>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                                @if(!empty($rewards['caramelos_familia']) || !empty($rewards['caramelos_ev']) || !empty($rewards['caramelos_tipo']))
                                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3 {{ !empty($rewards['capturas']) ? 'lg:col-span-3' : 'lg:col-span-4' }}">
                                    @if(!empty($rewards['caramelos_familia']))
                                        <div class="flex flex-wrap gap-3">
                                            @foreach($rewards['caramelos_familia'] as $candy)
                                                @include('exploraciones._caramelo', ['caramelo' => $candy])
                                            @endforeach
                                        </div>
                                        @if(!empty($rewards['caramelos_ev']) || !empty($rewards['caramelos_tipo']))
                                            <hr class="my-3 border-gray-200 dark:border-gray-700">
                                        @endif
                                    @endif

                                    @if(!empty($rewards['caramelos_ev']))
                                        <div class="flex flex-wrap gap-3">
                                            @foreach($rewards['caramelos_ev'] as $candy)
                                                @include('exploraciones._caramelo', ['caramelo' => $candy])
                                            @endforeach
                                        </div>
                                        @if(!empty($rewards['caramelos_tipo']))
                                            <hr class="my-3 border-gray-200 dark:border-gray-700">
                                        @endif
                                    @endif

                                    @if(!empty($rewards['caramelos_tipo']))
                                        <div class="flex flex-wrap gap-3">
                                            @foreach($rewards['caramelos_tipo'] as $candy)
                                                @include('exploraciones._caramelo', ['caramelo' => $candy])
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                @endif

                                @if(!empty($rewards['capturas']))
                                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3 {{ !empty($rewards['caramelos_familia']) || !empty($rewards['caramelos_ev']) || !empty($rewards['caramelos_tipo']) ? 'lg:col-span-1' : 'lg:col-span-4' }}">
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">Capturados</p>
                                    <div class="flex flex-wrap gap-3">
                                        @foreach($rewards['capturas'] as $captura)
                                            <div class="text-center" title="Pokémon capturado">
                                                <div class="relative inline-block">
                                                    <img
                                                        src="/images/iconos_webp/{{ (int) ($captura['pokemon_id'] ?? 0) }}.webp"
                                                        loading="lazy"
                                                        decoding="async"
                                                        alt="Capturado"
                                                        title="Pokémon capturado"
                                                        class="w-16 h-16 object-contain"
                                                        onerror="this.style.display='none'"
                                                    >
                                                    <span class="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 bg-green-600 text-white text-[10px] font-bold rounded-full">
                                                        ×{{ (int) ($captura['cantidad'] ?? 0) }}
                                                    </span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>
                        @else
                            {{-- ENTRENADOR / GIMNASIO: lista plana legada --}}
                            <p class="fw-bold text-success mb-3">Has derrotado al entrenador</p>

                            {{-- Medalla (gimnasio) --}}
                            @if(!empty($rewards['medalla']))
                            <div class="alert alert-warning text-center py-3 mb-3" role="alert">
                                <span class="d-block" style="font-size:2rem;">🏅</span>
                                <strong class="d-block text-warning-emphasis mt-1" style="font-size:1.1rem;">
                                    ¡Has ganado la {{ $rewards['medalla'] }}!
                                </strong>
                            </div>
                            @endif

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
                        @endif
                    </div>
                    <div class="modal-footer">
                        @if($habitatId > 0)
                            <a href="/habitats/{{ $habitatId }}" class="btn btn-success w-100 fw-bold">Volver al hábitat</a>
                        @else
                            <a href="{{ route('gimnasios.index') }}" class="btn btn-success w-100 fw-bold">Volver a gimnasios</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>