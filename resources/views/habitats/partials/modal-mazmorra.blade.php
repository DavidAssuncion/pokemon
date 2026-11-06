    <!-- Mazmorra Modal -->
    <template x-if="showMazmorraModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="mazmorra-modal-title" @keydown.escape.window="closeMazmorraModal()">
            <div class="absolute inset-0 scrim" @click="closeMazmorraModal()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-line">
                    <h3 id="mazmorra-modal-title" class="text-lg font-bold text-ink">Mazmorra del Hábitat</h3>
                    <button @click="closeMazmorraModal()" class="p-2 rounded-control hover:bg-surface-sunken text-ink-muted focus-ring" aria-label="Cerrar modal de mazmorra">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-6">
                    <!-- Loading -->
                    <div x-show="mazmorraLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-ink-muted">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Cargando mazmorra...
                    </div>

                    <!-- Error -->
                    <div x-show="mazmorraError" x-cloak role="alert" class="text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2">
                        <span x-text="mazmorraError"></span>
                        <button @click="loadMazmorra()" class="ml-2 underline font-medium">Reintentar</button>
                    </div>

                    <!-- Empty: sin pisos -->
                    <template x-if="!mazmorraLoading && !mazmorraError && (!pisos || pisos.length === 0)">
                        <div class="text-center py-10 text-ink-muted">
                            <p>Esta mazmorra aún no tiene pisos configurados.</p>
                        </div>
                    </template>

                    <template x-if="!mazmorraLoading && !mazmorraError && pisos && pisos.length > 0">
                        <div class="space-y-4">
                            <p class="text-xs text-ink-muted">
                                En cada piso te enfrentas al pokémon más débil del hábitat. Si pierdes, tendrás
                                que esperar 1 hora para repetir el piso; los pisos ganados no se pueden repetir.
                            </p>

                            <!-- Pisos -->
                            <div class="space-y-2">
                                <template x-for="piso in pisos" :key="'piso-'+piso.piso">
                                    <div
                                        class="flex items-center justify-between gap-3 p-3 rounded-card border-2 transition-all"
                                        :class="estadoPisoClass(piso)"
                                    >
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
                                                  :class="estadoPisoIconoClass(piso)">
                                                <span x-text="piso.piso"></span>
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-ink truncate" x-text="piso.nombre || 'Piso ' + piso.piso"></p>
                                                <p class="text-xs text-ink-muted" x-text="estadoPisoTexto(piso)"></p>
                                            </div>
                                        </div>
                                        <template x-if="piso.estado === 'disponible'">
                                            <button @click="selectPiso(piso)"
                                                    class="shrink-0 px-3 py-1.5 bg-primary text-on-primary rounded-control text-xs font-bold hover:bg-primary-hover transition-colors"
                                                    :disabled="mazmorraCombatiendo">
                                                Combatir
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <!-- Acción: conectar equipo + formación para el piso seleccionado -->
                            <template x-if="pisoSeleccionado">
                                <div class="border-t border-line pt-4">
                                    <p class="text-xs font-semibold text-ink-muted uppercase tracking-wide mb-2">Equipo y formación</p>
                                    <div class="space-y-2 mb-3">
                                        <template x-if="availableTeams.length === 0">
                                            <p class="text-sm text-ink-subtle text-center py-2">No tienes equipos disponibles</p>
                                        </template>
                                        <template x-for="team in availableTeams" :key="team.id">
                                            <button type="button" @click="selectMazmorraTeam(team.id, team.name)"
                                                    :class="mazmorraTeamId === team.id
                                                        ? 'border-primary bg-primary-soft'
                                                        : 'border-line hover:border-line-strong'"
                                                    class="w-full p-3 rounded-control border-2 text-left transition-all flex items-center justify-between">
                                                <span class="text-sm font-medium text-ink" x-text="team.name"></span>
                                                <template x-if="mazmorraTeamId === team.id">
                                                    <svg class="w-5 h-5 text-primary-ink" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                </template>
                                            </button>
                                        </template>
                                    </div>

                                    <!-- Formación (hasta 3 miembros) -->
                                    <div x-show="mazmorraTeamId" x-cloak class="space-y-3 mb-4">
                                        <p class="text-xs font-semibold text-ink-muted uppercase tracking-wide">Formación</p>
                                        <template x-for="miembro in (mazmorraTeamMembers || [])" :key="miembro.id">
                                            <div class="flex items-center justify-between p-3 bg-surface-sunken border border-line rounded-control">
                                                <div class="flex items-center gap-3">
                                                    <x-sprite size="64" x-bind:src="iconoReclutado(miembro.reclutado)" x-on:error="onSpriteError($event, miembro.reclutado.pokemon_id, miembro.reclutado.es_shiny)" x-bind:alt="miembro.reclutado.nombre" />
                                                    <div>
                                                        <p class="text-sm font-medium text-ink" x-text="miembro.reclutado.nombre || miembro.reclutado?.pokemon?.name"></p>
                                                        <p class="text-xs text-ink-muted" x-text="'Posición ' + miembro.slot"></p>
                                                    </div>
                                                </div>
                                                <button
                                                    @click="toggleFormacionSlot(miembro.slot)"
                                                    :class="mazmorraFormacion[miembro.slot] === 'vanguardia'
                                                        ? 'bg-primary text-on-primary border-primary'
                                                        : 'bg-surface-sunken text-ink border-line-strong'"
                                                    class="px-3 py-1.5 rounded-control text-xs font-bold border transition-colors shrink-0"
                                                    x-text="(mazmorraFormacion[miembro.slot] === 'vanguardia' ? 'Vanguardia' : 'Retaguardia')"
                                                ></button>
                                            </div>
                                        </template>
                                        <template x-if="!mazmorraTeamMembers || mazmorraTeamMembers.length === 0">
                                            <p class="text-sm text-ink-subtle text-center py-3">Selecciona un equipo primero</p>
                                        </template>
                                    </div>

                                    <!-- Error de combate -->
                                    <div x-show="mazmorraCombatError" x-cloak role="alert" class="text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2 mb-3">
                                        <span x-text="mazmorraCombatError"></span>
                                    </div>

                                    <button @click="confirmarPiso()"
                                            :disabled="!mazmorraTeamId || mazmorraCombatiendo"
                                            class="w-full px-4 py-2.5 bg-danger text-on-primary rounded-control text-sm font-bold hover:bg-danger/90 transition-colors disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed">
                                        <span x-show="!mazmorraCombatiendo">¡Combatir en el piso <span x-text="pisoSeleccionado ? pisoSeleccionado.piso : ''"></span>!</span>
                                        <span x-show="mazmorraCombatiendo" x-cloak class="inline-flex items-center gap-2">
                                            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            Iniciando...
                                        </span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
