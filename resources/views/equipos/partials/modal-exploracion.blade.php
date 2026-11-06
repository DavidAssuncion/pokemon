    <!-- Exploration Modal (individual) -->
    <template x-if="showExploracionModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeExploracionModal()" role="dialog" aria-modal="true" aria-labelledby="exploracion-modal-title">
            <div class="absolute inset-0 bg-scrim" @click="closeExploracionModal()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-md p-6">
                <h3 id="exploracion-modal-title" class="text-lg font-bold text-ink mb-2">Enviar a explorar</h3>
                <p class="text-sm text-ink-muted mb-4">
                    Enviar a <strong class="text-ink capitalize" x-text="exploracionPokemon ? nombreDe(exploracionPokemon) : ''"></strong> (Nv <span x-text="exploracionPokemon ? nivelDe(exploracionPokemon) : ''"></span>)
                </p>

                <!-- Habitat selector -->
                <div class="mb-4">
                    <label class="block text-xs font-medium text-ink-muted mb-1">Hábitat de destino</label>
                    <template x-if="habitatsLoading">
                        <p class="text-sm text-ink-subtle flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Cargando hábitats...
                        </p>
                    </template>
                    <template x-if="habitatsError">
                        <p class="text-sm text-warning-ink bg-warning-soft border border-warning rounded-control px-3 py-2" role="alert">
                            <span x-text="habitatsError"></span>
                        </p>
                    </template>
                    <template x-if="!habitatsLoading && !habitatsError && habitats.length > 0">
                        <select
                            x-model="exploracionHabitatId"
                            class="w-full px-3 py-2 bg-surface-sunken border border-line rounded-control text-sm text-ink focus-ring"
                        >
                            <option value="">Selecciona un hábitat</option>
                            <template x-for="h in habitats" :key="h.id">
                                <option :value="h.id" x-text="h.name"></option>
                            </template>
                        </select>
                    </template>
                </div>

                <!-- Level selector -->
                <div class="mb-4">
                    <label class="block text-xs font-medium text-ink-muted mb-1">Nivel de exploración</label>
                    <div class="flex gap-2">
                        <template x-for="lvl in [1, 2, 3]" :key="lvl">
                            <button
                                @click="exploracionLevel = lvl"
                                class="flex-1 px-3 py-2 rounded-control border-2 text-sm font-medium transition-all"
                                :class="exploracionLevel === lvl
                                    ? 'border-primary bg-primary-soft text-primary-ink'
                                    : 'border-line text-ink hover:border-line-strong'"
                                x-text="'Nv ' + lvl"
                            ></button>
                        </template>
                    </div>
                </div>

                <!-- Duration options -->
                <div class="space-y-3 mb-4">
                    <label class="flex items-center gap-3 p-3 rounded-control border border-line cursor-pointer hover:bg-surface-sunken transition-colors"
                        :class="{ 'border-primary bg-primary-soft': durationMode === 'hours' }"
                    >
                        <input type="radio" x-model="durationMode" value="hours" class="w-4 h-4 text-primary-ink">
                        <span class="text-sm text-ink">Duración: </span>
                        <input
                            type="number"
                            x-model.number="durationHours"
                            min="1"
                            max="72"
                            :disabled="durationMode !== 'hours'"
                            class="w-16 px-2 py-1 text-sm border border-line-strong rounded bg-surface-sunken text-ink disabled:opacity-50"
                            @click.stop
                        >
                        <span class="text-sm text-ink-muted">horas</span>
                    </label>

                    <label class="flex items-center gap-3 p-3 rounded-control border border-line cursor-pointer hover:bg-surface-sunken transition-colors"
                        :class="{ 'border-primary bg-primary-soft': durationMode === 'return_time' }"
                    >
                        <input type="radio" x-model="durationMode" value="return_time" class="w-4 h-4 text-primary-ink">
                        <span class="text-sm text-ink">Regresar antes de las </span>
                        <input
                            type="time"
                            x-model="returnTime"
                            :disabled="durationMode !== 'return_time'"
                            class="px-2 py-1 text-sm border border-line-strong rounded bg-surface-sunken text-ink disabled:opacity-50"
                            @click.stop
                        >
                    </label>
                </div>

                <!-- Preview (capacidades individuales) -->
                <div class="mb-4">
                    <div x-show="previewLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-ink-muted bg-surface-sunken border border-line rounded-control px-3 py-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Calculando preparación de la expedición...
                    </div>
                    <div x-show="previewError" x-cloak role="alert" class="text-sm text-warning-ink bg-warning-soft border border-warning rounded-control px-3 py-2">
                        <span x-text="previewError"></span>
                    </div>
                    <template x-if="previewLoaded && preview">
                        <div class="space-y-3 bg-surface-sunken border border-line rounded-control p-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-ink-muted">Riesgo</span>
                                <span class="px-2 py-0.5 text-2xs font-bold rounded-full uppercase" :class="riesgoClass(preview.riesgo)" x-text="preview.riesgo"></span>
                            </div>
                            <template x-if="preview.capacidades">
                                <div>
                                    <p class="text-xs font-medium text-ink-muted mb-1.5">Capacidades del Pokémon</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <template x-for="(valor, key) in preview.capacidades" :key="key">
                                            <div class="flex items-center justify-between px-2 py-1 bg-surface rounded border border-line">
                                                <span class="text-2xs text-ink-muted capitalize" x-text="key"></span>
                                                <span class="text-2xs font-medium text-ink" x-text="Math.round(valor * 100) / 100"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <template x-if="preview.min_lvl">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-ink-muted">Nivel mínimo requerido</span>
                                    <span class="text-xs font-medium text-ink">Nv <span x-text="preview.min_lvl"></span></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3">
                    <button
                        @click="closeExploracionModal()"
                        class="flex-1 px-4 py-2.5 bg-surface-sunken text-ink rounded-control text-sm font-medium hover:bg-line transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="confirmExploracion()"
                        :disabled="!exploracionHabitatId || sendingExploracion"
                        class="flex-1 px-4 py-2.5 bg-success text-on-primary rounded-control text-sm font-bold hover:bg-success/90 transition-colors disabled:bg-surface-sunken disabled:text-ink-subtle disabled:cursor-not-allowed"
                    >
                        <span x-text="sendingExploracion ? 'Enviando…' : 'Enviar expedición'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
