                <template x-if="activeSeccion === 'pokemon'">
                <div class="space-y-6">
                    <!-- Search and Filter -->
                <div class="space-y-3">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <input
                                type="text"
                                x-model="searchQuery"
                                placeholder="Buscar por nombre o ID..."
                                class="w-full px-4 py-2 pl-10 bg-surface border border-line rounded-control text-sm text-ink placeholder:text-ink-subtle focus-ring transition-colors"
                            >
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Filtro de tipo: botones con icono, selección múltiple -->
                    <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Filtrar por tipo">
                        <span class="mr-1 text-3xs font-bold uppercase tracking-wide text-ink-muted">Tipo</span>
                        @foreach($tiposVista as $tipo)
                        <button
                            type="button"
                            @click="toggleTypeFilter({{ $tipo['id'] }})"
                            :aria-pressed="filtroTipoActivo({{ $tipo['id'] }}) ? 'true' : 'false'"
                            :title="'Filtrar por tipo {{ $tipo['nombre'] }}'"
                            class="tipo-badge inline-flex items-center gap-1 font-semibold transition-opacity"
                            :style="filtroTipoActivo({{ $tipo['id'] }}) ? '--type-color: ' + tipoBadge('{{ $tipo['slug'] ?? $tipo['nombre'] }}').color : ''"
                            :class="filtroTipoActivo({{ $tipo['id'] }})
                                ? 'border-ink'
                                : 'bg-surface text-ink-muted border-line opacity-60 hover:opacity-100'"
                        >
                            <img src="/images/type/{{ $tipo['slug'] }}.webp" alt="" loading="lazy" decoding="async" class="w-5 h-5 object-contain">
                            <span>{{ $tipo['nombre'] }}</span>
                        </button>
                        @endforeach
                        <button
                            type="button"
                            x-show="typeFilters.length > 0"
                            x-cloak
                            @click="typeFilters = []"
                            class="ml-auto text-3xs font-medium text-primary-ink hover:underline"
                            title="Quitar todos los filtros de tipo"
                        >
                            Limpiar tipos
                        </button>
                    </div>
                </div>

                <!-- Reclutados: disponibles y asignados comparten cuadrícula -->
                <div>
                    <div class="flex items-center justify-between bg-surface-sunken border border-line rounded-control px-4 py-2 mb-4">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-ink">Reclutados</h2>
                        <span class="text-3xs font-medium text-ink-muted" x-text="pokemonsEquipoFiltrados.length + ' Pokémon'"></span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-2">
                        <template x-for="pokemon in pokemonsEquipoFiltrados" :key="'eq-pokemon-'+pokemon.id">
                            <div
                                class="relative bg-surface rounded-control shadow-sm border border-line text-center group pokemon-card-quality hover:z-30"
                                :class="[calidadClase(pokemon), pokemonTeamId(pokemon) !== null ? 'pokemon-card-assigned' : '']"
                                x-data="{ showTeamDropdown: false }"
                            >
                                <!-- Nombre del equipo encima / libre -->
                                <div class="px-1 pt-1 pb-1.5 border-b border-line">
                                    <p
                                        class="text-2xs font-bold uppercase tracking-wide truncate"
                                        :class="pokemonTeamId(pokemon) !== null ? 'text-ink-muted' : 'text-primary-ink'"
                                        x-text="pokemonTeamId(pokemon) !== null ? getTeamName(pokemonTeamId(pokemon)) : 'Libre'"
                                    ></p>
                                </div>
                                <x-sprite
                                    size="96"
                                    wrapper-class="mx-auto"
                                    x-bind:src="iconoDe(pokemon)"
                                    x-on:error="onSpriteError($event, pokemon.pokemon_id, pokemon.es_shiny)"
                                    x-bind:alt="pokemon.nombre || pokemon.pokemon_id || 'Pokémon'"
                                    x-bind:title="(pokemon.nombre || pokemon.pokemon_id || 'Pokémon') + (pokemonTeamId(pokemon) !== null ? ' — ' + getTeamName(pokemonTeamId(pokemon)) : '')"
                                    x-bind:class="pokemonTeamId(pokemon) !== null ? 'grayscale opacity-60' : ''"
                                />
                                <p
                                    class="text-2xs truncate px-1 pb-1 text-ink-muted"
                                    x-text="nombreDe(pokemon)"
                                ></p>
                                <template x-if="cpDe(pokemon) != null">
                                    <p
                                        class="text-2xs font-bold leading-none text-info-ink"
                                        x-text="'CP ' + cpDe(pokemon)"
                                    ></p>
                                </template>

                                <!-- Resumen en hover: habilidad activa y movimientos asignados -->
                                <div class="pointer-events-none absolute left-1/2 top-full z-50 mt-2 hidden w-56 -translate-x-1/2 group-hover:block">
                                    <div class="rounded-card border border-line bg-surface p-2.5 text-left shadow-modal border-line-strong bg-surface-sunken">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-2xs font-bold uppercase tracking-wide text-success-ink">Habilidad</span>
                                            <span class="text-2xs text-ink-subtle" x-text="habilidadActivaDe(pokemon) ? 'Asignada' : 'Automática'"></span>
                                        </div>
                                        <template x-if="(pokemon.habilidades_disponibles || []).length > 0">
                                            <div class="mt-1 space-y-0.5">
                                                <template x-for="hab in pokemon.habilidades_disponibles" :key="'card-hab-' + pokemon.id + '-' + hab.id">
                                                    <div class="flex items-center gap-1.5" :title="hab.description || hab.name">
                                                        <span class="h-1 w-1 shrink-0 rounded-full" :class="String(pokemon.habilidad_activa_id) === String(hab.id) ? 'bg-success' : 'bg-surface-sunken'"></span>
                                                        <span class="truncate text-2xs leading-snug" :class="String(pokemon.habilidad_activa_id) === String(hab.id) ? 'font-semibold text-success-ink' : 'text-ink-muted'" x-text="hab.name"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <p x-show="(pokemon.habilidades_disponibles || []).length === 0" class="mt-1 text-2xs text-ink-subtle">Sin habilidades registradas</p>

                                        <p class="mt-2 text-2xs font-bold uppercase tracking-wide text-primary-ink">Movimientos</p>
                                        <template x-if="movimientosAsignadosDe(pokemon).length > 0">
                                            <div class="mt-1 space-y-0.5">
                                                <template x-for="mov in movimientosAsignadosDe(pokemon)" :key="'card-mov-' + pokemon.id + '-' + mov.slug">
                                                    <div class="flex items-center gap-1.5">
                                                        <img :src="tipoIconoUrl(mov)" :alt="tipoDeMovimiento(mov) || 'Tipo'" loading="lazy" decoding="async" class="w-3.5 h-3.5 shrink-0 object-contain">
                                                        <span class="truncate text-2xs text-ink-muted" x-text="mov.name"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <p x-show="movimientosAsignadosDe(pokemon).length === 0" class="mt-1 text-2xs text-ink-subtle">Sin asignar · catálogo completo</p>
                                    </div>
                                </div>

                                <!-- Action buttons -->
                                <div class="absolute inset-0 bg-scrim opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2 rounded-control">
                                    <template x-if="pokemonTeamId(pokemon) === null">
                                        <div class="relative">
                                            <button
                                                @click="showTeamDropdown = !showTeamDropdown"
                                                class="w-8 h-8 bg-success text-on-primary rounded-full flex items-center justify-center hover:bg-success/90 transition-colors text-lg font-bold"
                                                :aria-label="'Agregar ' + nombreDe(pokemon) + ' a equipo'"
                                                :title="'Agregar ' + nombreDe(pokemon) + ' a equipo'"
                                            >
                                                +
                                            </button>
                                            <!-- Team dropdown -->
                                            <template x-if="showTeamDropdown && teams.length > 0">
                                                <div class="absolute left-1/2 -translate-x-1/2 top-full mt-1 bg-surface border border-line rounded-control shadow-modal p-1 w-44 max-h-56 overflow-y-auto z-50">
                                                    <template x-for="team in teams" :key="'dd-'+team.id">
                                                        <button
                                                            @click="selectTeamForPokemon(pokemon, team); showTeamDropdown = false"
                                                            class="w-full text-left px-3 py-1.5 rounded hover:bg-line transition-colors"
                                                            :title="team.members.length >= 3 ? 'Equipo completo: elige a quién reemplazar' : 'Añadir al equipo'"
                                                        >
                                                            <span class="block text-xs font-medium text-ink truncate" x-text="team.name"></span>
                                                            <span
                                                                class="block text-2xs font-semibold"
                                                                :class="team.members.length >= 3 ? 'text-warning-ink' : 'text-success-ink'"
                                                                x-text="team.members.length >= 3 ? 'Completo · reemplazar' : 'Libre · añadir'"
                                                            ></span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                    <!-- Inspect (always available, read-only) -->
                                    <button
                                        @click="openDetail(pokemon)"
                                        class="w-8 h-8 bg-primary text-on-primary rounded-full flex items-center justify-center hover:bg-primary transition-colors"
                                        :aria-label="'Ver detalle de ' + nombreDe(pokemon)"
                                        :title="'Ver detalle de ' + nombreDe(pokemon)"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                    <!-- Remove from team (blocked while the team is exploring) -->
                                    <template x-if="pokemonTeamId(pokemon) !== null">
                                        <button
                                            @click="!pokemonEnExploracion(pokemon) && removeFromTeam(pokemon)"
                                            :disabled="pokemonEnExploracion(pokemon)"
                                            :title="pokemonEnExploracion(pokemon) ? 'El Pokémon está en exploración' : 'Quitar ' + nombreDe(pokemon) + ' de equipo'"
                                            class="w-8 h-8 bg-warning text-on-primary rounded-full flex items-center justify-center hover:bg-warning/90 transition-colors text-sm disabled:opacity-30 disabled:cursor-not-allowed"
                                            :aria-label="'Quitar ' + nombreDe(pokemon) + ' de equipo'"
                                        >
                                            →
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Empty -->
                        <template x-if="pokemonsEquipoFiltrados.length === 0">
                            <div class="col-span-full text-center py-8">
                                <p class="text-sm text-ink-subtle" x-text="hayFiltrosEquipo() ? 'No se encontraron Pokémon' : 'No hay Pokémon reclutados'"></p>
                            </div>
                        </template>
                    </div>
                </div>
                </div>
                    </template>
