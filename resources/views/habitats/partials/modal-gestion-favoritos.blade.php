    <!-- Gestion Favoritos Habitat Modal -->
    <template x-if="showGestionFavoritosModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="gestion-favoritos-title" @keydown.escape.window="closeGestionFavoritosModal()">
            <div class="absolute inset-0 scrim" @click="closeGestionFavoritosModal()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-line">
                    <h3 id="gestion-favoritos-title" class="text-lg font-bold text-ink">Favoritos de esta hábitat</h3>
                    <button @click="closeGestionFavoritosModal()" class="p-2 rounded-control hover:bg-surface-sunken text-ink-muted focus-ring" aria-label="Cerrar">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-6">
                    <div x-show="gestionFavoritosLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-ink-muted">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Cargando...
                    </div>
                    <div x-show="gestionFavoritosError" x-cloak role="alert" class="text-sm text-warning-ink bg-warning-soft border border-warning rounded-control px-3 py-2">
                        <span x-text="gestionFavoritosError"></span>
                    </div>
                    <div x-show="!gestionFavoritosLoading && !gestionFavoritosError">
                        <!-- Máximo alcanzado -->
                        <div x-show="favoritos.length >= 6" class="mb-3 px-3 py-2 rounded-control bg-warning-soft border border-warning text-xs text-warning-ink">
                            Ya tienes 6 favoritos en este hábitat (máximo). Quita uno para poder añadir otro.
                        </div>
                        <!-- Grid de reclutados del usuario -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                            <template x-for="pokemon in allReclutados" :key="pokemon.id">
                                <div
                                    class="relative bg-surface rounded-control shadow-sm border text-center p-2 transition-all cursor-pointer select-none"
                                    :class="esFavoritoHabitat(pokemon.id)
                                        ? 'border-warning ring-1 ring-warning/30'
                                        : 'border-line hover:border-line-strong'"
                                    @click="toggleFavoritoHabitat(pokemon)"
                                    :title="esFavoritoHabitat(pokemon.id) ? 'Quitar de favoritos de este hábitat' : 'Añadir a favoritos de este hábitat'"
                                >
                                    <div class="w-24 h-24 mx-auto">
                                        <x-sprite
                                            size="96"
                                            x-bind:src="iconoReclutado(pokemon)"
                                            x-on:error="onSpriteError($event, pokemon.pokemon_id, pokemon.es_shiny)"
                                            x-bind:alt="pokemon.nombre"
                                        />
                                    </div>
                                    <p class="text-2xs text-ink-muted truncate mt-1" x-text="pokemon.nombre"></p>
                                    <p class="text-2xs text-ink-subtle" x-text="'Nv ' + (pokemon.nivel || 1)"></p>
                                    <div class="mt-1">
                                        <template x-if="esFavoritoHabitat(pokemon.id)">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-warning-soft text-warning-ink text-2xs font-bold rounded-full">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                Favorito
                                            </span>
                                        </template>
                                        <template x-if="!esFavoritoHabitat(pokemon.id)">
                                            <span class="text-2xs text-ink-subtle">Click para marcar</span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div x-show="allReclutados.length === 0" class="text-center py-12 text-ink-muted">
                            <p>No hay Pokémon disponibles</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
