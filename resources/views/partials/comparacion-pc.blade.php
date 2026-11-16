{{-- Comparación de PC: solo presentación. Los totales y el veredicto llegan calculados del backend. --}}
<section role="group" aria-labelledby="pc-comparacion-titulo" class="mb-6">
    <h4 id="pc-comparacion-titulo" class="mb-2 text-sm font-bold text-ink">Comparación de PC</h4>

    <div class="rounded-control border border-line bg-surface-sunken px-3 py-3 text-sm text-ink"
         aria-live="polite"
         aria-atomic="true"
         :aria-busy="pcCargando ? 'true' : 'false'">

        {{-- Sin equipo seleccionado --}}
        <p x-show="!selectedTeamId" class="text-ink-muted">
            Selecciona un equipo para ver la comparación.
        </p>

        {{-- Cargando --}}
        <div x-show="selectedTeamId && pcCargando" x-cloak role="status" class="flex items-center gap-2 text-ink-muted">
            <svg class="h-4 w-4 animate-spin motion-reduce:animate-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <span>Calculando PC...</span>
        </div>

        {{-- Error del indicador: nunca bloquea el combate --}}
        <p x-show="selectedTeamId && !pcCargando && pcError" x-cloak role="alert" class="text-danger-ink" x-text="pcError"></p>

        {{-- Resultado --}}
        <template x-if="selectedTeamId && !pcCargando && !pcError && pcComparacion">
            <div class="space-y-2">
                <template x-if="pcComparacion.estado === 'ok'">
                    <div class="space-y-2">
                        <dl class="space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-ink-muted">PC de tu equipo</dt>
                                <dd class="font-bold text-ink" x-text="formatoPc(pcComparacion.equipo.pc)"></dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-ink-muted" x-text="etiquetaPcRival()"></dt>
                                <dd class="font-bold text-ink">
                                    <template x-if="pcComparacion.rival.aproximado">
                                        <span><span class="sr-only">Aproximadamente </span>≈ </span>
                                    </template><span x-text="formatoPc(pcComparacion.rival.pc)"></span>
                                </dd>
                            </div>
                        </dl>

                        <p class="flex items-center gap-2 rounded-control border px-2 py-1.5 font-semibold"
                           :class="claseVeredicto(pcComparacion.comparacion.veredicto)">
                            <span aria-hidden="true" x-text="iconoVeredicto(pcComparacion.comparacion.veredicto)"></span>
                            <span class="sr-only">Veredicto: </span>
                            <span x-text="textoVeredicto(pcComparacion.comparacion.veredicto)"></span>
                        </p>

                        <p x-show="pcComparacion.contexto && pcComparacion.contexto.tipo === 'ruta'" x-cloak class="text-xs text-ink-muted">
                            Rival fijado para este combate. Si cierras y vuelves a abrir el modal, se elegirá otro.
                        </p>
                        <p x-show="pcComparacion.contexto && pcComparacion.contexto.tipo === 'gimnasio'" x-cloak class="text-xs text-ink-muted">
                            Rival de la etapa actual. No se muestran especies, tipos ni movimientos del rival.
                        </p>
                    </div>
                </template>

                <template x-if="pcComparacion.estado === 'equipo_incompleto'">
                    <p class="text-ink-muted">El equipo necesita 3 miembros para combatir.</p>
                </template>

                <template x-if="pcComparacion.estado === 'sin_rivales'">
                    <p class="text-ink-muted">No hay rival disponible en este nivel.</p>
                </template>

                <template x-if="pcComparacion.estado === 'no_disponible'">
                    <p class="text-ink-muted">Este combate ya no está disponible.</p>
                </template>
            </div>
        </template>
    </div>
</section>
