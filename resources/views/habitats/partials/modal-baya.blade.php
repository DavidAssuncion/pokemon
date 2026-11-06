    <!-- Modal de selección de baya -->
    <template x-if="showBayaModal">
        <div
            class="fixed inset-0 z-[60] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="baya-modal-title"
        >
            <div class="absolute inset-0 scrim" @click="cerrarBayaModal()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-[90vw] max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">
                <div class="flex shrink-0 items-start justify-between gap-3 px-6 py-4 border-b border-line">
                    <div>
                        <h3 id="baya-modal-title" class="text-lg font-bold text-ink">
                            Elegir baya · Hueco <span x-text="slotPlantando?.slot ?? ''"></span>
                        </h3>
                        <p class="text-xs text-ink-muted">Las bayas con existencias aparecen primero.</p>
                    </div>
                    <button
                        type="button"
                        @click="cerrarBayaModal()"
                        class="p-2 rounded-control hover:bg-surface-sunken text-ink-muted focus-ring"
                        aria-label="Cerrar selector de baya"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="shrink-0 border-b border-line px-6 py-3 space-y-3">
                    <div>
                        <label for="baya-buscar" class="sr-only">Buscar baya</label>
                        <input
                            id="baya-buscar"
                            x-ref="bayaBuscar"
                            type="search"
                            x-model="bayaBusqueda"
                            placeholder="Buscar por nombre o categoría…"
                            autocomplete="off"
                            class="w-full px-3 py-2 rounded-control border border-line bg-surface text-sm text-ink focus-ring"
                        >
                    </div>
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar bayas por categoría">
                        <template x-for="grupo in bayaGrupos" :key="'baya-grupo-' + grupo.valor">
                            <button
                                type="button"
                                @click="bayaGrupoActivo = grupo.valor"
                                :aria-pressed="bayaGrupoActivo === grupo.valor ? 'true' : 'false'"
                                :class="bayaGrupoActivo === grupo.valor
                                    ? 'bg-primary text-on-primary border-primary'
                                    : 'bg-surface text-ink-muted border-line hover:bg-surface-sunken'"
                                class="px-3 py-1.5 rounded-full border text-3xs font-bold uppercase transition-colors focus-ring"
                                x-text="grupo.etiqueta"
                            ></button>
                        </template>
                    </div>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto px-6 py-4">
                    <div x-show="bayasCargando" x-cloak role="status" class="flex items-center gap-2 text-sm text-ink-muted">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Cargando catálogo de bayas…
                    </div>

                    <div x-show="!bayasCargando && bayaError" x-cloak class="text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2">
                        <span aria-live="assertive" x-text="bayaError"></span>
                        <button type="button" @click="cargarCatalogoBayas()" class="ml-2 underline font-medium">Reintentar</button>
                    </div>

                    <p x-show="!bayasCargando && !bayaError && bayasCatalogo.length === 0" x-cloak class="py-10 text-center text-sm text-ink-muted">
                        No hay bayas disponibles en el catálogo.
                    </p>

                    <p x-show="!bayasCargando && !bayaError && bayasCatalogo.length > 0 && bayasFiltradas.length === 0" x-cloak class="py-10 text-center text-sm text-ink-muted">
                        No hay bayas que coincidan.
                    </p>

                    <ul x-show="!bayasCargando && !bayaError && bayasFiltradas.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <template x-for="baya in bayasFiltradas" :key="'baya-' + baya.clave">
                            <li>
                                <button
                                    type="button"
                                    @click="plantarBayaDesdeModal(baya)"
                                    :disabled="baya.cantidad < 1 || bayasPlantando"
                                    :aria-label="baya.nombre + ' · ' + baya.grupo_nombre + ' · ' + (baya.cantidad > 0 ? baya.cantidad + ' disponibles' : 'sin stock')"
                                    class="w-full flex items-center gap-3 rounded-control border border-line bg-surface px-3 py-2 text-left transition-colors hover:border-primary focus-ring disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:border-line"
                                >
                                    <img :src="baya.icono" alt="" loading="lazy" decoding="async" class="w-8 h-8 shrink-0 object-contain" :class="baya.cantidad < 1 ? 'opacity-40' : ''">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-xs font-semibold text-ink" x-text="baya.nombre"></span>
                                        <span class="block text-3xs text-ink-muted" x-text="baya.grupo_nombre"></span>
                                    </span>
                                    <span class="shrink-0 text-right text-2xs font-bold" :class="baya.cantidad > 0 ? 'text-ink' : 'text-ink-muted'" x-text="baya.cantidad > 0 ? baya.cantidad + ' disp.' : 'Sin stock'"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>

                <div x-show="bayasPlantando" x-cloak class="shrink-0 border-t border-line px-6 py-3">
                    <p role="status" class="text-xs font-semibold text-primary-ink">Plantando baya…</p>
                </div>
            </div>
        </div>
    </template>
