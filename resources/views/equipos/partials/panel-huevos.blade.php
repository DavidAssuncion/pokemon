                    <!-- ═══ Huevos (tab) ═══ -->
                    <template x-if="activeSeccion === 'huevos'">
                        <div class="space-y-6">
                            <!-- Feedback de acciones huevos -->
                            <template x-if="huevosFeedback.mensaje">
                                <div class="px-3 py-2 rounded-control text-xs" role="status"
                                    :class="huevosFeedback.tipo === 'error'
                                        ? 'bg-danger-soft text-danger-ink'
                                        : 'bg-success-soft text-success-ink'"
                                    x-text="huevosFeedback.mensaje">
                                </div>
                            </template>

                            <!-- Límite de huevos activo -->
                            <template x-if="limiteExcedido()">
                                <div class="px-3 py-2 rounded-control bg-primary-soft text-primary-ink text-xs" role="status">
                                    <span x-text="limiteMensaje()"></span>
                                </div>
                            </template>

                            <!-- Loading -->
                            <template x-if="huevosLoading">
                                <div class="flex items-center gap-2 text-sm text-ink-subtle py-6 justify-center" role="status">
                                    <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Cargando huevos...
                                </div>
                            </template>

                            <!-- Error / módulo no disponible -->
                            <template x-if="!huevosLoading && huevosError">
                                <div class="text-center py-8">
                                    <p class="text-sm text-ink-muted mb-3" x-text="huevosError"></p>
                                    <button
                                        @click="cargarHuevos(true)"
                                        class="px-4 py-2 bg-primary text-on-primary rounded-control text-sm font-medium hover:bg-primary-hover transition-colors"
                                    >
                                        Reintentar
                                    </button>
                                </div>
                            </template>

                            <template x-if="!huevosLoading && !huevosError && huevosLoaded">
                                <div class="space-y-6">
                                <!-- Mis huevos -->
                                <div>
                                    <div class="flex items-center justify-between bg-surface-sunken border border-line rounded-control px-4 py-2 mb-4">
                                        <h2 class="text-sm font-bold uppercase tracking-wide text-ink">Mis Huevos</h2>
                                    </div>
                                    <template x-if="huevos.length === 0">
                                        <div class="text-center py-6 text-sm text-ink-subtle">No tienes huevos en este momento.</div>
                                    </template>
                                    <template x-if="huevos.length > 0">
                                        <div class="space-y-3">
                                            <template x-for="huevo in huevos" :key="'huevo-'+huevo.id">
                                                <div class="flex items-center gap-3 bg-surface rounded-control shadow-sm border border-line p-3">
                                                    <div class="w-16 h-16 shrink-0 flex items-center justify-center">
                                                        <x-sprite
                                                            size="64"
                                                            x-bind:src="huevo.pokemon?.icon ? huevo.pokemon.icon : '/images/iconos_webp/' + (huevo.pokemon?.id ?? 0) + '.webp'"
                                                            x-bind:alt="huevo.pokemon?.name ?? 'Huevo'"
                                                        />
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2 flex-wrap">
                                                            <span class="text-sm font-medium text-ink capitalize truncate" x-text="huevo.pokemon?.name ?? 'Pokémon por descubrir'"></span>
                                                            <span class="px-1.5 py-0.5 text-2xs font-bold rounded uppercase" :class="estadoHuevo(huevo).clase" x-text="estadoHuevo(huevo).texto"></span>
                                                        </div>
                                                        <div class="flex items-center gap-2 mt-1.5">
                                                            <div class="flex-1 h-1.5 bg-surface-sunken rounded-full overflow-hidden">
                                                                <div class="h-full bg-info rounded-full" :style="'width: ' + huevoProgreso(huevo) + '%'"></div>
                                                            </div>
                                                            <span class="text-2xs text-ink-muted flex-shrink-0" x-text="huevoEtiquetaRestante(huevo)"></span>
                                                        </div>
                                                        <p class="text-2xs text-ink-subtle mt-1" x-text="'Nido Nv ' + (huevo.nido_nivel ?? 0) + ' · IV ' + (huevo.rango_iv?.min ?? 0) + '–' + (huevo.rango_iv?.max ?? 0)"></p>
                                                    </div>
                                                    <template x-if="huevo.puede_eclosionar">
                                                        <button
                                                            @click="eclosionarHuevo(huevo)"
                                                            :disabled="huevosActuando"
                                                            class="shrink-0 px-3 py-1.5 bg-info text-on-primary rounded-control text-xs font-bold hover:bg-info/90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                                        >
                                                            <span x-show="huevosAccionId === huevo.id" x-cloak>Eclosionando…</span>
                                                            <span x-show="huevosAccionId !== huevo.id">Eclosionar</span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>

                                <!-- Familias disponibles -->
                                <div>
                                    <div class="flex items-center justify-between bg-surface-sunken border border-line rounded-control px-4 py-2 mb-4">
                                        <h2 class="text-sm font-bold uppercase tracking-wide text-ink">Familias Disponibles</h2>
                                    </div>
                                    <template x-if="huevosDisponibles.length === 0">
                                        <div class="text-center py-6 text-sm text-ink-subtle">No hay huevos disponibles para incubar.</div>
                                    </template>
                                    <template x-if="huevosDisponibles.length > 0">
                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                            <template x-for="op in huevosDisponibles" :key="'disp-'+op.evolution_chain_id">
                                                <div class="bg-surface rounded-control shadow-sm border border-line text-center p-3">
                                                    <div class="w-24 h-24 mx-auto">
                                                        <x-sprite
                                                            size="96"
                                                            x-bind:src="op.pokemon?.icon ? op.pokemon.icon : '/images/iconos_webp/' + (op.pokemon?.id ?? 0) + '.webp'"
                                                            x-bind:alt="op.pokemon?.name ?? 'Pokémon'"
                                                        />
                                                    </div>
                                                    <p class="text-2xs text-ink-muted truncate mt-1 capitalize" x-text="op.pokemon?.name ?? 'Pokémon'"></p>
                                                    <p class="text-2xs text-ink-subtle" x-text="'Captura ' + (op.capture_rate ?? '—')"></p>
                                                    <p class="text-2xs text-warning-ink font-semibold" x-text="'Nido Nv ' + (op.nido_nivel ?? 0) + ' · IV ' + (op.rango_iv?.min ?? 0) + '–' + (op.rango_iv?.max ?? 0)"></p>
                                                    <button
                                                        @click="ponerHuevo(op.evolution_chain_id)"
                                                        :disabled="huevosActuando || !puedePonerHuevo()"
                                                        :title="!puedePonerHuevo() ? limiteMensaje() : 'Poner a gestar este huevo'"
                                                        class="mt-2 w-full px-2 py-1.5 bg-success text-on-primary rounded-control text-xs font-bold hover:bg-success/90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                                    >
                                                        Poner huevo
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                                </div>
                            </template>
                        </div>
                    </template>
