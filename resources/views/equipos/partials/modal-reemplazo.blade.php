    <!-- Replace Team Member Modal (equipo completo) -->
    <template x-if="showReplaceModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="cerrarReemplazo()" role="dialog" aria-modal="true" aria-labelledby="replace-modal-title">
            <div class="absolute inset-0 bg-scrim" @click="cerrarReemplazo()"></div>
            <div class="relative bg-surface rounded-panel shadow-modal w-full max-w-md p-6">
                <h3 id="replace-modal-title" class="text-lg font-bold text-ink">Equipo completo</h3>
                <p class="mt-1 text-sm text-ink-muted">
                    Elige qué Pokémon de <strong class="text-ink" x-text="replaceTeam?.name"></strong> ocupará el lugar de
                    <strong class="text-ink" x-text="replacePokemon ? nombreFormateado(replacePokemon) : ''"></strong>.
                </p>

                <template x-if="replaceError">
                    <p class="mt-3 px-3 py-2 rounded-control bg-danger-soft text-danger-ink text-xs" role="alert" x-text="replaceError"></p>
                </template>

                <div class="mt-4 space-y-2">
                    <template x-for="slot in [1,2,3]" :key="'replace-slot-'+slot">
                        <template x-if="replaceTeam && getMember(replaceTeam, slot)">
                            <button
                                type="button"
                                @click="reemplazarMiembroEquipo(slot)"
                                :disabled="replaceSaving"
                                class="w-full flex items-center gap-3 p-3 rounded-card border border-line hover:border-warning hover:bg-warning-soft transition-colors text-left disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <x-sprite
                                    size="64"
                                    x-bind:src="iconoDe(reclutadoDeSlot(replaceTeam, slot))"
                                    x-on:error="onSpriteError($event, reclutadoDeSlot(replaceTeam, slot).pokemon_id, reclutadoDeSlot(replaceTeam, slot).es_shiny)"
                                    x-bind:alt="nombreFormateado(reclutadoDeSlot(replaceTeam, slot))"
                                />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-ink truncate" x-text="nombreFormateado(reclutadoDeSlot(replaceTeam, slot))"></p>
                                    <p class="text-2xs text-ink-subtle">
                                        Slot <span x-text="slot"></span> ·
                                        <span x-text="'CP ' + (cpDe(reclutadoDeSlot(replaceTeam, slot)) ?? '—')"></span>
                                    </p>
                                </div>
                                <span class="text-2xs font-bold uppercase text-warning-ink">Reemplazar</span>
                            </button>
                        </template>
                    </template>
                </div>

                <div class="mt-5 flex gap-3">
                    <button
                        type="button"
                        @click="cerrarReemplazo()"
                        class="flex-1 px-4 py-2.5 bg-surface-sunken text-ink rounded-control text-sm font-medium hover:bg-line transition-colors"
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </template>
