<div>
@php
    $candyFallback = "this.src='/images/candy_pokemon/0.webp'; this.onerror=null;";
@endphp
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

    {{-- PREPARACIÓN DE RONDA --}}
    @include('livewire.partials.preparacion-panel')

    {{-- CAMPO Y ATAQUES A ANCHO COMPLETO --}}
    <div class="d-flex flex-column gap-3">
        @include('livewire.partials.battle-field')
        @include('livewire.partials.moves-panel')
    </div>
</div>

{{-- REGISTRO DE BATALLA: se consulta en modal para liberar la columna --}}
@include('livewire.partials.battle-log-modal')

    {{-- MODAL VICTORIA: recompensas al ganar contra entrenador --}}
    @if($phase === 'battle_over' && !empty($rewards))
        <div class="modal fade show d-block reward-modal {{ !empty($eventos) ? 'modal-pendiente' : '' }}"
             x-data
             x-init="if ($el.classList.contains('modal-pendiente')) setTimeout(() => $el.classList.remove('modal-pendiente'), 8000)"
             tabindex="-1" role="dialog" aria-modal="true" style="background: rgba(0,0,0,0.6);">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-success fw-bold">🏆 ¡Victoria!</h5>
                    </div>
                    <div class="modal-body">
                        @php
                            // Ruta: las recompensas vienen agrupadas (familia/EV/tipo) + capturas.
                            // Entrenador/gimnasio: lista plana + medalla opcional.
                            $tieneCaramelos = !empty($rewards['caramelos_familia'])
                                || !empty($rewards['caramelos_ev'])
                                || !empty($rewards['caramelos_tipo']);
                            $rutaRecompensas = $tieneCaramelos || !empty($rewards['capturas']);
                        @endphp

                        @if($rutaRecompensas)
                            {{-- RUTA: caramelos y capturas apilados en una sola tarjeta --}}
                            <p class="fw-bold text-success mb-3">¡Has derrotado a los pokémon salvajes!</p>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>EXP para tu cuenta</span>
                                <strong class="text-success">+{{ number_format((int) ($rewards['exp_total'] ?? 0)) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>EXP para cada pokémon</span>
                                <strong class="text-success">+{{ number_format((int) ($rewards['exp_miembro'] ?? 0)) }}</strong>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3">
                                @if($tieneCaramelos)
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
                                @endif

                                @if($tieneCaramelos && !empty($rewards['capturas']))
                                    <hr class="my-3 border-gray-200 dark:border-gray-700">
                                @endif

                                @if(!empty($rewards['capturas']))
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">Capturados</p>
                                    <div class="flex flex-wrap gap-3">
                                        @foreach($rewards['capturas'] as $captura)
                                            @php
                                                // El backend propaga es_shiny (bool) en el contrato de ruta;
                                                // tolerante a data legacy que aún no lo emite.
                                                $capturaShiny = !empty($captura['es_shiny']);
                                                $capturaId = (int) ($captura['pokemon_id'] ?? 0);
                                                $capturaSrc = $capturaShiny
                                                    ? '/images/iconos_webp/shiny/' . $capturaId . '_s.webp'
                                                    : '/images/iconos_webp/' . $capturaId . '.webp';
                                                $capturaOnerror = $capturaShiny
                                                    ? "this.onerror=null; this.src='/images/iconos_webp/" . $capturaId . ".webp';"
                                                    : "this.style.display='none'";
                                            @endphp
                                            <div class="text-center" title="{{ $capturaShiny ? '¡Captura shiny!' : 'Pokémon capturado' }}">
                                                <div class="relative inline-block {{ $capturaShiny ? 'ring-2 ring-yellow-400 rounded-full animate-pulse' : '' }}">
                                                    <img
                                                        src="{{ $capturaSrc }}"
                                                        loading="lazy"
                                                        decoding="async"
                                                        alt="Capturado"
                                                        title="Pokémon capturado"
                                                        class="w-16 h-16 object-contain rounded-full {{ $capturaShiny ? 'bg-yellow-100 dark:bg-yellow-900/40' : '' }}"
                                                        onerror="{{ $capturaOnerror }}"
                                                    >
                                                    @if($capturaShiny)
                                                        <span class="absolute -top-1.5 -left-1.5 px-1 py-0.5 bg-yellow-400 text-yellow-900 text-[10px] font-bold rounded-full shadow" title="Shiny">✨</span>
                                                    @endif
                                                    <span class="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 bg-green-600 text-white text-[10px] font-bold rounded-full">
                                                        ×{{ (int) ($captura['cantidad'] ?? 0) }}
                                                    </span>
                                                </div>
                                            </div>
                                        @endforeach
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