    <!-- Formacion Modal (Entrenadores / Ruta) -->
    <template x-if="showFormacionPopup">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="formacion-modal-title" @keydown.escape.window="closeFormacionPopup()">
            <div class="absolute inset-0 scrim" @click="closeFormacionPopup()"></div>
            <div class="relative flex max-h-[90vh] w-full max-w-6xl flex-col rounded-panel bg-surface-raised shadow-modal">
                <div class="shrink-0 px-6 pt-6">
                    <h3 id="formacion-modal-title" class="text-lg font-bold text-ink mb-2" x-text="formacionContexto === 'ruta' ? 'Combate de Ruta · Configurar Formación' : 'Configurar Formación'"></h3>
                    <p class="text-sm text-ink-muted">
                        Ajusta la posición de cada pokémon antes del combate.
                    </p>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-6 py-4">
                    <!-- Error 422 inline (ruta: sin redirigir) -->
                    <div x-show="rutaCombatError" x-cloak role="alert" class="mb-4 text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2">
                        <span x-text="rutaCombatError"></span>
                    </div>

                    <!-- Miembros del equipo con toggle -->
                    <div class="space-y-3 mb-6">
                        <template x-for="miembro in (selectedTeamMembers || [])" :key="miembro.id">
                            <div class="flex items-center justify-between p-3 bg-surface-sunken border border-line rounded-control">
                                <div class="flex items-center gap-3">
                                    <x-sprite size="64" x-bind:src="iconoReclutado(miembro.reclutado)" x-on:error="onSpriteError($event, miembro.reclutado.pokemon_id, miembro.reclutado.es_shiny)" x-bind:alt="miembro.reclutado.nombre" />
                                    <div>
                                        <p class="text-sm font-medium text-ink" x-text="miembro.reclutado.nombre || miembro.reclutado?.pokemon?.name"></p>
                                    </div>
                                </div>
                                <button
                                    @click="toggleFormacionSlot(miembro.slot)"
                                    :class="formacion[miembro.slot] === 'vanguardia'
                                        ? 'bg-primary text-on-primary border-primary'
                                        : 'bg-surface-sunken text-ink border-line-strong'"
                                    class="px-3 py-1.5 rounded-control text-xs font-bold border transition-colors"
                                    x-text="formacion[miembro.slot] === 'vanguardia' ? 'Vanguardia' : 'Retaguardia'"
                                ></button>
                            </div>
                        </template>
                        <template x-if="!selectedTeamMembers || selectedTeamMembers.length === 0">
                            <p class="text-sm text-ink-subtle text-center py-4">Selecciona un equipo primero</p>
                        </template>
                    </div>

                    @include('partials.comparacion-pc')
                    @include('partials.leyenda-ivs-evs', ['columnas' => 3, 'colapsable' => true])
                </div>

                <!-- Buttons -->
                <div class="flex shrink-0 flex-col gap-3 border-t border-line px-6 py-4 sm:flex-row sm:justify-end">
                    <button
                        @click="closeFormacionPopup()"
                        class="w-full px-4 py-2.5 bg-surface-sunken text-ink rounded-control text-sm font-medium hover:bg-line transition-colors sm:w-auto sm:px-6"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="confirmarCombate()"
                        :disabled="formacionContexto === 'ruta'
                            ? (!selectedTeamId || rutaCombatiendo)
                            : (!selectedTeamId || !selectedTrainer)"
                        class="w-full px-4 py-2.5 bg-danger text-on-primary rounded-control text-sm font-bold hover:bg-danger/90 transition-colors disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed sm:w-auto sm:px-6"
                        x-text="formacionContexto === 'ruta'
                            ? (rutaCombatiendo ? 'Iniciando combate...' : '¡Combatir! (Ruta 3v3)')
                            : '¡Combatir!'"
                    ></button>
                </div>
            </div>
        </div>
    </template>
