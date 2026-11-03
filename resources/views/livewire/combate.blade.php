<div>
<div class="container-fluid py-3 battle-container"
     wire:key="battle-{{ $battleId }}"
     x-init="$nextTick(() => {
         const DURACION_ACCION = 700;
         const animacionDisponible = typeof instantaneaVitales === 'function' && typeof animarVitales === 'function';
         let timer = null;
         let reproduciendo = false;

         const bloquearInterfaz = (bloquear) => {
             ['.moves-panel', '.turn-bar', '.battle-field'].forEach((selector) => {
                 document.querySelector(selector)?.toggleAttribute('inert', bloquear);
             });
         };

         // El servidor pinta .anim-ataque en el siguiente actor durante el commit.
         // Se retira para que no se mezcle con el impacto y se reinicia al terminar
         // la cola: así la embestida del siguiente turno se ve completa.
         const limpiarAtaques = () => {
             document.querySelectorAll('.pokemon-card.anim-ataque').forEach((carta) => {
                 carta.classList.remove('anim-ataque');
             });
         };

         const marcarSiguienteAtacante = () => {
             const refId = $wire.get('animAttackerId');
             if (!refId) {
                 return;
             }

             const carta = buscarCarta(refId);
             if (!carta) {
                 return;
             }

             carta.classList.remove('anim-ataque');
             void carta.offsetWidth;
             carta.classList.add('anim-ataque');
         };

         // Resuelve la acción y reproduce en orden los eventos que devuelve el
         // servidor. La interfaz queda bloqueada hasta terminar la cola para que
         // ningún clic provoque un render intermedio (ver ANALISIS_FRONTEND).
         const resolverAccion = async () => {
             if (reproduciendo) return;
             reproduciendo = true;
             const previos = animacionDisponible ? instantaneaVitales() : null;
             const logPrevio = document.querySelector('.battle-log ul')?.innerHTML ?? null;
             let logFinal = null;
             try {
                 await $wire.commitAction();
                 limpiarAtaques();

                 // El registro también se adelantaría al resultado; se conserva
                 // el texto anterior hasta que termine la cola de animaciones.
                 const logDespues = document.querySelector('.battle-log ul');
                 if (logDespues) {
                     logFinal = logDespues.innerHTML;
                     if (logPrevio !== null) {
                         logDespues.innerHTML = logPrevio;
                     }
                 }

                 const eventos = $wire.get('eventos') || [];
                 if (animacionDisponible && typeof reproducirEventos === 'function') {
                     bloquearInterfaz(true);
                     await reproducirEventos(eventos, previos);
                 } else if (previos) {
                     animarVitales(previos);
                 }
             } finally {
                 const logAhora = document.querySelector('.battle-log ul');
                 if (logAhora && logFinal !== null) {
                     logAhora.innerHTML = logFinal;
                 }
                 bloquearInterfaz(false);
                 document.querySelectorAll('.pokemon-card.estado-final-oculto').forEach((carta) => {
                     carta.classList.remove('estado-final-oculto');
                 });
                 if (typeof mostrarModalVictoria === 'function') mostrarModalVictoria();
                 reproduciendo = false;
                 // Deja terminar el impacto antes de reiniciar la embestida del
                 // siguiente actor y abrir su ventana de 700 ms.
                 window.setTimeout(() => {
                     marcarSiguienteAtacante();
                     programarAccion();
                 }, 90);
             }
         };

         const programarAccion = (demora = DURACION_ACCION) => {
             if (timer) clearTimeout(timer);
             timer = null;
             if (reproduciendo) return;
             if (!$wire.get('animAttackerId')) return;
             timer = setTimeout(() => { resolverAccion(); }, demora);
         };

         $wire.$watch('animTick', () => programarAccion());
         if ($wire.get('animAttackerId')) programarAccion();
     })">

    {{-- TOP: TURN ORDER --}}
    @include('livewire.partials.turn-bar')

    {{-- CAMPO (2/3) Y ATAQUES (1/3) --}}
    <div class="row g-3">
        <div class="col-12 col-lg-9">
            @include('livewire.partials.battle-field')
        </div>
        <div class="col-12 col-lg-3">
            @include('livewire.partials.moves-panel')
        </div>
    </div>
</div>

{{-- REGISTRO DE BATALLA: se consulta en modal para liberar la columna --}}
@include('livewire.partials.battle-log-modal')

{{-- PREPARACIÓN DE RONDA: se ajusta en modal para no ocupar espacio --}}
@include('livewire.partials.preparacion-modal')

    {{-- MODAL VICTORIA: recompensas al ganar --}}
    @if($phase === 'battle_over' && $rewardsVista !== null)
        <div class="modal fade show d-block reward-modal scrim {{ !empty($eventos) ? 'modal-pendiente' : '' }}"
             x-data
             x-init="if ($el.classList.contains('modal-pendiente')) setTimeout(() => $el.classList.remove('modal-pendiente'), 8000)"
             tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="victory-modal-title">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="victory-modal-title" class="modal-title text-success-ink fw-bold">¡Victoria!</h5>
                    </div>
                    <div class="modal-body">
                        @if($rewardsVista['layout'] === 'ruta')
                            {{-- RUTA: capturas y caramelos en columnas separadas --}}
                            <p class="fw-bold text-success-ink mb-3">¡Has derrotado a los pokémon salvajes!</p>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>EXP para tu cuenta</span>
                                <strong class="text-success-ink">+{{ number_format($rewardsVista['exp_total']) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>EXP para cada pokémon</span>
                                <strong class="text-success-ink">+{{ number_format($rewardsVista['exp_miembro']) }}</strong>
                            </div>

                            @php
                                $tieneCapturas = $rewardsVista['capturas'] !== [];
                            @endphp

                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
                                @if($tieneCapturas)
                                    <div class="bg-surface-sunken rounded-control p-3 {{ $rewardsVista['tieneCaramelos'] ? 'lg:col-span-1' : 'lg:col-span-3' }}">
                                        <p class="text-xs font-semibold text-ink-muted uppercase tracking-wide mb-2">Capturados</p>
                                        <div class="flex flex-wrap gap-3">
                                            @foreach($rewardsVista['capturas'] as $captura)
                                                <div class="text-center" title="{{ $captura['title'] }}">
                                                    <div class="relative inline-block {{ $captura['es_shiny'] ? 'ring-2 ring-warning rounded-full animate-pulse' : '' }}">
                                                        <x-sprite
                                                            size="64"
                                                            :id="$captura['pokemon_id']"
                                                            :shiny="$captura['es_shiny']"
                                                            name="Capturado"
                                                            title="Pokémon capturado"
                                                            class="rounded-full {{ $captura['es_shiny'] ? 'bg-warning-soft' : '' }}"
                                                        />
                                                        @if($captura['es_shiny'])
                                                            <span class="absolute -top-1.5 -left-1.5 px-1 py-0.5 bg-warning-soft text-warning-ink text-2xs font-bold rounded-full shadow" title="Shiny">Shiny</span>
                                                        @endif
                                                        <span class="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 bg-success text-on-primary text-2xs font-bold rounded-full">
                                                            ×{{ $captura['cantidad'] }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if($rewardsVista['tieneCaramelos'])
                                    <div class="bg-surface-sunken rounded-control p-3 {{ $tieneCapturas ? 'lg:col-span-2' : 'lg:col-span-3' }}">
                                        <div class="flex flex-col gap-3">
                                            @if(! $rewardsVista['caramelos_familia']->isEmpty())
                                                <div class="flex flex-wrap gap-3">
                                                    @foreach($rewardsVista['caramelos_familia'] as $candy)
                                                        @include('exploraciones._caramelo', ['caramelo' => $candy->toArray()])
                                                    @endforeach
                                                </div>
                                            @endif

                                            @if(! $rewardsVista['caramelos_ev']->isEmpty())
                                                <div class="flex flex-wrap gap-3">
                                                    @foreach($rewardsVista['caramelos_ev'] as $candy)
                                                        @include('exploraciones._caramelo', ['caramelo' => $candy->toArray()])
                                                    @endforeach
                                                </div>
                                            @endif

                                            @if(! $rewardsVista['caramelos_tipo']->isEmpty())
                                                <div class="flex flex-wrap gap-3">
                                                    @foreach($rewardsVista['caramelos_tipo'] as $candy)
                                                        @include('exploraciones._caramelo', ['caramelo' => $candy->toArray()])
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            {{-- ENTRENADOR / GIMNASIO: lista plana legada --}}
                            <p class="fw-bold text-success-ink mb-3">Has derrotado al entrenador</p>

                            {{-- Medalla (gimnasio) --}}
                            @if($rewardsVista['medalla'] !== null)
                            <div class="alert alert-warning text-center py-3 mb-3" role="alert">
                                <strong class="d-block text-warning-ink mt-1" style="font-size:1.1rem;">
                                    ¡Has ganado la {{ $rewardsVista['medalla'] }}!
                                </strong>
                            </div>
                            @endif

                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>EXP para tu cuenta</span>
                                    <strong class="text-success-ink">+{{ number_format($rewardsVista['exp_total']) }}</strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>EXP para cada pokémon</span>
                                    <strong class="text-success-ink">+{{ number_format($rewardsVista['exp_miembro']) }}</strong>
                                </li>
                                @foreach($rewardsVista['caramelos'] as $caramelo)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span class="d-flex align-items-center gap-2">
                                            <img src="{{ $caramelo['imagen'] }}" alt="{{ $caramelo['nombre'] }}" style="width:28px;height:28px;object-fit:contain" loading="lazy">
                                            {{ $caramelo['nombre'] }}
                                        </span>
                                        <strong class="text-success-ink">+{{ $caramelo['cantidad'] }}</strong>
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