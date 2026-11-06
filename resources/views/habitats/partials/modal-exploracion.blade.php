    <!-- Exploration Modal (individual) -->
    <template x-if="showExplorationModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="exploration-modal-title" @keydown.escape.window="closeExplorationModal()">
            <div class="absolute inset-0 scrim" @click="closeExplorationModal()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-md p-6">
                <h3 id="exploration-modal-title" class="text-lg font-bold text-ink mb-4">Confirmar Exploración</h3>
                <p class="text-sm text-ink-muted mb-4">
                    ¿Quieres enviar a explorar a <strong class="text-ink capitalize" x-text="selectedFavoritoName"></strong> a la zona <strong class="text-ink">{{ $habitat['name'] }}</strong>?
                </p>

                <!-- Preparación de la expedición (preview individual) -->
                <div class="mb-4">
                    <div x-show="previewLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-ink-muted bg-surface-sunken border border-line rounded-control px-3 py-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Calculando la preparación de la expedición...
                    </div>
                    <div x-show="previewError" x-cloak role="alert" class="text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2">
                        <span x-text="previewError"></span>
                    </div>
                    <template x-if="previewLoaded && preview">
                        <div class="space-y-3 bg-surface-sunken border border-line rounded-control p-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-ink-muted">Peligro de la zona</span>
                                <span class="text-sm text-warning-ink tracking-tight" x-html="starRating(preview.peligro)"
                                      :title="'Peligro ' + preview.peligro + ' de 5 estrellas'" aria-hidden="true"></span>
                            </div>
                            <template x-if="preview.modo">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-ink-muted">Modo</span>
                                    <span class="px-2 py-0.5 text-2xs font-bold rounded-full uppercase bg-primary-soft text-primary-ink" x-text="modoLabel(preview.modo)"></span>
                                </div>
                            </template>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-ink-muted">Nivel del Pokémon</span>
                                <span class="text-sm font-medium text-ink" x-text="'Nv ' + preview.nivel_pokemon"></span>
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
                                    <span class="text-xs font-medium text-ink-muted">Nivel mínimo requerido</span>
                                    <span class="text-xs font-medium text-ink">Nv <span x-text="preview.min_lvl"></span></span>
                                </div>
                            </template>
                            <div class="flex items-center justify-between">
                                <span class="px-2 py-0.5 text-2xs font-bold rounded-full uppercase" :class="riesgoClass(preview.riesgo)" x-text="'Riesgo ' + preview.riesgo"></span>
                            </div>
                            <template x-if="preview.rol || preview.rol_sugerido">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-ink-muted">Rol</span>
                                    <span class="px-2 py-0.5 text-2xs font-bold rounded-full uppercase bg-info-soft text-info-ink" x-text="preview.rol || preview.rol_sugerido"></span>
                                </div>
                            </template>
                            <!-- Recompensas esperadas (tolerante: se oculta si el backend aún no las expone) -->
                            <div class="space-y-1.5 pt-2 border-t border-line"
                                 x-show="previewLoaded && preview?.recompensas_esperadas">
                                <p class="text-xs font-bold text-ink">Ganarás esto</p>
                                <template x-if="preview.recompensas_esperadas?.por_horas">
                                    <p class="text-xs text-ink-muted">
                                        Duración estimada:
                                        <span class="font-medium text-ink" x-text="preview.recompensas_esperadas.por_horas + ' h'"></span>
                                    </p>
                                </template>
                                <template x-if="preview.recompensas_esperadas?.items?.length">
                                    <ul class="space-y-1">
                                        <template x-for="(item, i) in preview.recompensas_esperadas.items" :key="i">
                                            <li class="flex items-center justify-between gap-3 text-xs text-ink-muted">
                                                <span x-text="item.label"></span>
                                                <span class="font-medium text-ink" x-text="'entre ' + item.min + ' y ' + item.max"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </template>
                                <template x-if="preview.recompensas_esperadas?.aviso">
                                    <p class="text-2xs text-ink-subtle italic" x-text="preview.recompensas_esperadas.aviso"></p>
                                </template>
                                {{-- Aviso económico: el backend calcula factor y mensaje; la vista solo los presenta. --}}
                                <template x-if="preview?.aviso_economia">
                                    <div class="flex items-start gap-1.5 pt-0.5">
                                        <p role="note" class="text-2xs leading-snug text-warning-ink" x-text="preview.aviso_economia"></p>
                                        <template x-if="preview?.economia?.factor">
                                            <span class="shrink-0 whitespace-nowrap px-1.5 py-0.5 rounded-full border border-warning bg-warning-soft text-2xs font-medium text-warning-ink"
                                                  x-text="'Factor ×' + Number(preview.economia.factor).toLocaleString('es-ES', { maximumFractionDigits: 2 })"></span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Exploration mode (F1: modo de exploracion) -->
                <fieldset class="mb-6">
                    <legend class="text-xs font-semibold text-ink-muted uppercase tracking-wide mb-2">Modo de exploraci&oacute;n</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <label class="flex sm:flex-col items-start sm:items-center gap-2 sm:gap-1 p-3 rounded-control border border-line cursor-pointer hover:bg-surface-sunken transition-colors text-center"
                            :class="{ 'border-primary bg-primary-soft': modoExploracion === 'experiencia' }"
                        >
                            <input id="modo-exploracion-experiencia" type="radio" name="modo_exploracion" value="experiencia" x-model="modoExploracion" @change="loadPreview()" class="w-4 h-4 text-primary-ink focus-ring">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-ink">Pelear</span>
                                <span class="block text-2xs text-ink-muted leading-snug">Prioriza experiencia en combate</span>
                            </span>
                        </label>
                        <label class="flex sm:flex-col items-start sm:items-center gap-2 sm:gap-1 p-3 rounded-control border border-line cursor-pointer hover:bg-surface-sunken transition-colors text-center"
                            :class="{ 'border-primary bg-primary-soft': modoExploracion === 'reclutamiento' }"
                        >
                            <input id="modo-exploracion-reclutamiento" type="radio" name="modo_exploracion" value="reclutamiento" x-model="modoExploracion" @change="loadPreview()" class="w-4 h-4 text-primary-ink focus-ring">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-ink">Buscar</span>
                                <span class="block text-2xs text-ink-muted leading-snug">Prioriza capturar y reclutar Pok&eacute;mon</span>
                            </span>
                        </label>
                        <label class="flex sm:flex-col items-start sm:items-center gap-2 sm:gap-1 p-3 rounded-control border border-line cursor-pointer hover:bg-surface-sunken transition-colors text-center"
                            :class="{ 'border-primary bg-primary-soft': modoExploracion === 'botin' }"
                        >
                            <input id="modo-exploracion-botin" type="radio" name="modo_exploracion" value="botin" x-model="modoExploracion" @change="loadPreview()" class="w-4 h-4 text-primary-ink focus-ring">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-ink">Explorar</span>
                                <span class="block text-2xs text-ink-muted leading-snug">Prioriza bayas y bonguris</span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <!-- Duration options (copied from existing) -->
                <div class="space-y-3 mb-6">
                    <label class="flex items-center gap-3 p-3 rounded-control border border-line cursor-pointer hover:bg-surface-sunken transition-colors"
                        :class="{ 'border-primary bg-primary-soft': durationMode === 'hours' }"
                    >
                        <input type="radio" x-model="durationMode" value="hours" class="w-4 h-4 text-primary-ink" @change="loadPreview()">
                        <span class="text-sm text-ink">Duración: </span>
                        <input
                            type="number"
                            x-model.number="durationHours"
                            min="1"
                            max="72"
                            :disabled="durationMode !== 'hours'"
                            class="w-16 px-2 py-1 text-sm border border-line-strong rounded bg-surface text-ink disabled:opacity-50"
                            @click.stop
                            @change="loadPreview()"
                        >
                        <span class="text-sm text-ink-muted">horas</span>
                    </label>

                    <label class="flex items-center gap-3 p-3 rounded-control border border-line cursor-pointer hover:bg-surface-sunken transition-colors"
                        :class="{ 'border-primary bg-primary-soft': durationMode === 'return_time' }"
                    >
                        <input type="radio" x-model="durationMode" value="return_time" class="w-4 h-4 text-primary-ink" @change="loadPreview()">
                        <span class="text-sm text-ink">Regresar antes de las </span>
                        <input
                            type="time"
                            x-model="returnTime"
                            :disabled="durationMode !== 'return_time'"
                            class="px-2 py-1 text-sm border border-line-strong rounded bg-surface text-ink disabled:opacity-50"
                            @click.stop
                            @change="loadPreview()"
                        >
                    </label>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3">
                    <button
                        @click="closeExplorationModal()"
                        class="flex-1 px-4 py-2.5 bg-surface-sunken text-ink rounded-control text-sm font-medium hover:bg-line transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="confirmExploration()"
                        :disabled="!previewLoaded"
                        class="flex-1 px-4 py-2.5 bg-success text-on-primary rounded-control text-sm font-bold hover:bg-success/90 transition-colors disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed"
                    >
                        Enviar expedición
                    </button>
                </div>
            </div>
        </div>
    </template>
