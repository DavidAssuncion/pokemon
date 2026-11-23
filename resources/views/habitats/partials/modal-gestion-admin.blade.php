    <!-- Admin Gestion Modal -->
    <template x-if="showGestionModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="gestion-modal-title" @keydown.escape.window="closeGestionModal()">
            <div class="absolute inset-0 scrim" @click="closeGestionModal()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-line">
                    <h3 id="gestion-modal-title" class="text-lg font-bold text-ink">Admin - Gestión de Familias</h3>
                    <button @click="closeGestionModal()" class="p-2 rounded-control hover:bg-surface-sunken text-ink-muted focus-ring" aria-label="Cerrar modal">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Tabs -->
                <div class="border-b border-line px-6">
                    <nav class="flex gap-6" role="tablist" aria-label="Gestión de familias">
                        <button
                            role="tab"
                            :aria-selected="gestionTab === 'assign'"
                            :class="['py-3 text-sm font-medium border-b-2 transition-colors', gestionTab === 'assign' ? 'border-primary text-primary-ink' : 'border-transparent text-ink-muted hover:text-ink']"
                            @click="gestionTab = 'assign'"
                        >
                            Asignar
                        </button>
                        <button
                            role="tab"
                            :aria-selected="gestionTab === 'unassign'"
                            :class="['py-3 text-sm font-medium border-b-2 transition-colors', gestionTab === 'unassign' ? 'border-danger text-danger-ink' : 'border-transparent text-ink-muted hover:text-ink']"
                            @click="gestionTab = 'unassign'"
                        >
                            Ya Asignados
                        </button>
                    </nav>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-6">
                    <!-- ASSIGN TAB -->
                    <template x-if="gestionTab === 'assign'">
                        <div class="space-y-4">
                            <!-- Filters -->
                            <div class="flex flex-col sm:flex-row gap-3">
                                <input
                                    type="text"
                                    x-model="assignSearch"
                                    placeholder="Buscar familia por nombre..."
                                    class="flex-1 px-3 py-2 text-sm border border-line-strong rounded-control bg-surface text-ink"
                                >
                                <select
                                    x-model="assignTypeFilter"
                                    class="px-3 py-2 text-sm border border-line-strong rounded-control bg-surface text-ink"
                                >
                                    <option value="">Todos los tipos</option>
                                    <template x-for="tipo in allTypes" :key="tipo.id">
                                        <option :value="tipo.id" x-text="tipo.name"></option>
                                    </template>
                                </select>
                            </div>

                            <!-- Unassigned families grid -->
                            <div x-show="filteredUnassigned.length === 0 && !gestionLoading" class="text-center py-12 text-ink-muted">
                                <p>No hay familias sin hábitat que coincidan con la búsqueda</p>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                <template x-for="family in filteredUnassigned" :key="family.evolution_chain_id">
                                    <button
                                        type="button"
                                        :disabled="gestionLoading"
                                        @click="assignFamily(family)"
                                        class="group card-surface p-3 text-center hover:border-primary hover:shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                        :title="family.base.name"
                                    >
                                        <div class="w-full aspect-square bg-surface-sunken rounded-control flex items-center justify-center overflow-hidden mb-2">
                                            <x-sprite
                                                size="96"
                                                class="group-hover:scale-105 transition-transform"
                                                x-bind:src="'/images/iconos_webp/' + family.base.id + '.webp'"
                                                x-bind:alt="family.base.name"
                                            />
                                        </div>
                                        <p class="text-sm font-medium text-ink truncate" x-text="family.base.name"></p>
                                        <div class="flex flex-wrap justify-center gap-1 mt-1">
                                            <template x-for="tipo in (family.types || [])" :key="tipo.id">
                                                <span class="tipo-badge" :style="'--type-color: ' + tipoBadgePorId(tipo.id).color">
                                                    <template x-if="tipoBadgePorId(tipo.id).icono">
                                                        <img :src="tipoBadgePorId(tipo.id).icono" alt="" loading="lazy" decoding="async" class="w-4 h-4 object-contain">
                                                    </template>
                                                    <span x-text="tipo.name"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- YA ASIGNADOS TAB: agrupado por nivel (1, 2, 3) -->
                    <template x-if="gestionTab === 'unassign'">
                        <div class="space-y-6">
                            <div x-show="assignedFamilies.length === 0 && !gestionLoading" class="text-center py-12 text-ink-muted">
                                <p>No hay familias asignadas a este hábitat</p>
                            </div>

                            <template x-for="level in [1, 2, 3]" :key="'level-' + level">
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-sm font-semibold text-ink">Nivel <span x-text="level"></span></h4>
                                        <span class="text-xs text-ink-muted" x-text="assignedByLevel[level].length + ' pokémon'"></span>
                                    </div>

                                    <div x-show="assignedByLevel[level].length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                        <template x-for="pokemon in assignedByLevel[level]" :key="pokemon.evolution_chain_id + '-' + pokemon.id">
                                            <div class="relative card-surface p-3 text-center group">
                                                <template x-if="pokemon.is_base">
                                                    <button
                                                        type="button"
                                                        :disabled="gestionLoading"
                                                        @click="removeFamily(pokemon.evolution_chain_id)"
                                                        class="absolute -top-2 -right-2 w-6 h-6 bg-danger text-on-primary rounded-full text-xs flex items-center justify-center hover:bg-danger/90 transition-colors disabled:opacity-30"
                                                        :aria-label="'Quitar la familia completa de ' + pokemon.name"
                                                        title="Quitar familia completa"
                                                    >
                                                        ✕
                                                    </button>
                                                </template>
                                                <div class="w-full aspect-square bg-surface-sunken rounded-control flex items-center justify-center overflow-hidden mb-2">
                                                    <x-sprite
                                                        size="96"
                                                        x-bind:src="'/images/iconos_webp/' + pokemon.id + '.webp'"
                                                        x-bind:alt="pokemon.name"
                                                    />
                                                </div>
                                                <p class="text-sm font-medium text-ink truncate" x-text="pokemon.name"></p>
                                                <p class="text-2xs text-ink-muted mt-0.5" x-text="pokemon.is_base ? 'Base de la familia' : 'Evolución'"></p>
                                                <div class="flex justify-center gap-1 mt-2">
                                                    <button
                                                        type="button"
                                                        :disabled="gestionLoading"
                                                        @click="updatePokemonLevel(pokemon.evolution_chain_id, pokemon.id, 1)"
                                                        :class="pokemon.level === 1 ? 'bg-primary text-on-primary border-primary' : 'bg-surface text-ink-muted border-line hover:border-primary'"
                                                        class="w-8 h-7 text-xs font-bold rounded border transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                        :aria-label="'Mover ' + pokemon.name + ' al nivel 1'"
                                                        title="Nivel 1"
                                                    >1</button>
                                                    <button
                                                        type="button"
                                                        :disabled="gestionLoading"
                                                        @click="updatePokemonLevel(pokemon.evolution_chain_id, pokemon.id, 2)"
                                                        :class="pokemon.level === 2 ? 'bg-primary text-on-primary border-primary' : 'bg-surface text-ink-muted border-line hover:border-primary'"
                                                        class="w-8 h-7 text-xs font-bold rounded border transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                        :aria-label="'Mover ' + pokemon.name + ' al nivel 2'"
                                                        title="Nivel 2"
                                                    >2</button>
                                                    <button
                                                        type="button"
                                                        :disabled="gestionLoading"
                                                        @click="updatePokemonLevel(pokemon.evolution_chain_id, pokemon.id, 3)"
                                                        :class="pokemon.level === 3 ? 'bg-primary text-on-primary border-primary' : 'bg-surface text-ink-muted border-line hover:border-primary'"
                                                        class="w-8 h-7 text-xs font-bold rounded border transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                        :aria-label="'Mover ' + pokemon.name + ' al nivel 3'"
                                                        title="Nivel 3"
                                                    >3</button>
                                                </div>
                                                <div class="flex items-center justify-center gap-1 mt-2">
                                                    <label class="text-2xs text-ink-muted" :for="'min-level-' + pokemon.id">Nv mín.</label>
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        max="100"
                                                        :id="'min-level-' + pokemon.id"
                                                        x-model.number="pokemon.min_level"
                                                        @change="updatePokemonMinLevel(pokemon.id, pokemon.min_level)"
                                                        :disabled="gestionLoading"
                                                        class="w-14 rounded border border-line bg-surface px-1 py-0.5 text-center text-xs text-ink disabled:opacity-30"
                                                        :title="pokemon.manual ? 'Nivel mínimo fijado manualmente' : 'Nivel mínimo calculado automáticamente'"
                                                    >
                                                    <button
                                                        type="button"
                                                        x-show="pokemon.manual"
                                                        :disabled="gestionLoading"
                                                        @click="resetPokemonMinLevel(pokemon.id)"
                                                        class="text-2xs text-primary-ink underline disabled:opacity-30"
                                                        title="Volver al cálculo automático"
                                                    >auto</button>
                                                </div>
                                                <p x-show="pokemon.manual" x-cloak class="mt-1 text-2xs text-warning-ink">Manual</p>
                                            </div>
                                        </template>
                                    </div>

                                    <div x-show="assignedByLevel[level].length === 0 && assignedFamilies.length > 0 && !gestionLoading" class="text-center py-6 text-ink-subtle border border-dashed border-line rounded-card">
                                        <p>Sin pokémon en este nivel</p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
