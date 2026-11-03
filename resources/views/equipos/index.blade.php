@extends('layouts.app')

@section('title', 'Equipos')

@section('content')
<div x-data="favoritosApp()">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-ink">Equipos</h1>
        <p class="text-sm text-ink-muted mt-1">Gestiona tus equipos de combate</p>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- EQUIPOS                                                            -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left 1/3: Teams Panel -->
            <div class="space-y-4">
                <!-- New Team Button -->
                <button
                    @click="showNewTeamForm = !showNewTeamForm"
                    class="w-full px-4 py-3 bg-primary text-on-primary rounded-card text-sm font-medium hover:bg-primary-hover transition-colors flex items-center justify-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Nuevo Equipo
                </button>

                <!-- New Team Form -->
                <template x-if="showNewTeamForm">
                    <div class="bg-surface rounded-card shadow-sm border border-line p-4">
                        <input
                            type="text"
                            x-model="newTeamName"
                            placeholder="Nombre del equipo"
                            class="w-full px-3 py-2 bg-surface-sunken border border-line rounded-control text-sm text-ink placeholder:text-ink-subtle focus-ring mb-3"
                            @keydown.enter="createTeam()"
                        >
                        <div class="flex gap-2">
                            <button
                                @click="createTeam()"
                                :disabled="!newTeamName.trim()"
                                class="flex-1 px-3 py-2 bg-success text-on-primary rounded-control text-sm font-medium hover:bg-success/90 disabled:bg-surface-sunken disabled:cursor-not-allowed transition-colors"
                            >
                                Crear
                            </button>
                            <button
                                @click="showNewTeamForm = false; newTeamName = ''"
                                class="px-3 py-2 bg-surface-sunken text-ink rounded-control text-sm font-medium hover:bg-line transition-colors"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Teams List -->
                <div class="space-y-3">
                    <template x-for="team in teams" :key="'team-'+team.id">
                        <div
                            class="bg-surface rounded-card shadow-sm border-2 overflow-hidden transition-all"
                            :class="selectedTeamId === team.id
                                ? 'border-primary'
                                : 'border-line'"
                        >
                            <!-- Team header -->
                            <div class="flex items-center justify-between p-3 border-b border-line bg-surface-sunken">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <button
                                        @click="startRename(team)"
                                        class="text-sm font-medium text-ink hover:text-primary-ink transition-colors"
                                        x-text="team.name"
                                    ></button>
                                    <template x-if="team.members.length < 3">
                                        <span class="px-1.5 py-0.5 bg-surface-sunken text-ink-muted text-2xs font-bold rounded">
                                            INVÁLIDO
                                        </span>
                                    </template>
                                    <template x-if="teamEnExploracion(team) > 0">
                                        <span
                                            class="px-1.5 py-0.5 bg-warning-soft text-warning-ink text-2xs font-bold rounded uppercase"
                                            :title="teamEnExploracion(team) + ' miembro(s) del equipo en exploración'"
                                            x-text="teamEnExploracion(team) + ' en exploración'"
                                        ></span>
                                    </template>
                                    <template x-if="composicionBadge(team)">
                                        <span
                                            class="px-1.5 py-0.5 text-2xs font-bold rounded uppercase"
                                            :class="composicionBadge(team).clase"
                                            x-text="composicionBadge(team).texto"
                                        ></span>
                                    </template>
                                </div>
                                <button
                                    @click="confirmDeleteTeam(team)"
                                    class="w-6 h-6 flex items-center justify-center rounded text-ink-subtle hover:text-danger-ink hover:bg-danger-soft transition-colors"
                                    :aria-label="'Eliminar equipo ' + team.name"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <!-- Team members -->
                            <div class="p-3">
                                <div class="grid grid-cols-3 gap-1">
                                    <template x-for="slot in [1,2,3]" :key="'slot-'+team.id+'-'+slot">
                                        <div class="text-center min-w-0">
                                            <template x-if="getMember(team, slot)">
                                                <div class="relative">
                                                    <x-sprite
                                                        size="96"
                                                        wrapper-class="mx-auto cursor-pointer"
                                                        x-bind:src="iconoDe(getMember(team, slot))"
                                                        x-bind:alt="nombreFormateado(getMember(team, slot))"
                                                        x-bind:title="'Ver detalle de ' + nombreFormateado(getMember(team, slot))"
                                                        @click="openMemberDetail(team, slot)"
                                                        role="button"
                                                        tabindex="0"
                                                        @keydown.enter="openMemberDetail(team, slot)"
                                                    />
                                                    <button
                                                        @click="!enExploracion(getMember(team, slot).id) && removeMember(team, getMember(team, slot))"
                                                        :disabled="enExploracion(getMember(team, slot).id)"
                                                        :title="enExploracion(getMember(team, slot).id) ? 'El Pokémon está en exploración' : 'Quitar miembro del equipo'"
                                                        class="absolute -top-1 -right-1 w-4 h-4 bg-danger text-on-primary rounded-full text-2xs flex items-center justify-center hover:bg-danger/90 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                        aria-label="Quitar miembro"
                                                    >
                                                        ✕
                                                    </button>
                                                    <!-- Nombre del Pokémon -->
                                                    <p
                                                        class="text-2xs leading-tight text-ink-muted mt-0.5 cursor-pointer hover:text-primary-ink"
                                                        @click="openMemberDetail(team, slot)"
                                                        :title="'Ver detalle de ' + nombreFormateado(getMember(team, slot))"
                                                        x-text="nombreFormateado(getMember(team, slot))"
                                                    ></p>
                                                    <!-- Formación: iconos seleccionables -->
                                                     <div class="flex flex-row items-center justify-center gap-1">
                                                        <button
                                                            @click="setFormacionSlot(team, slot, 'vanguardia')"
                                                            :class="formacionDe(team, slot) === 'vanguardia' ? 'bg-primary-soft border-primary text-primary-ink' : 'border-transparent text-ink-subtle hover:text-ink opacity-70'"
                                                            class="rounded px-1.5 py-0.5 border transition-colors"
                                                            :aria-label="'Posición vanguardia de ' + nombreFormateado(getMember(team, slot))"
                                                            :aria-pressed="formacionDe(team, slot) === 'vanguardia'"
                                                            :title="'Posición vanguardia de ' + nombreFormateado(getMember(team, slot))"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 01-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 011-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 011.52 0C14.51 3.81 17 5 19 5a1 1 0 011 1z"/>
                                                            </svg>
                                                        </button>
                                                        <button
                                                            @click="setFormacionSlot(team, slot, 'retaguardia')"
                                                            :class="formacionDe(team, slot) === 'retaguardia' ? 'bg-danger-soft border-danger text-danger-ink' : 'border-transparent text-ink-subtle hover:text-ink opacity-70'"
                                                            class="rounded px-1.5 py-0.5 border transition-colors"
                                                            :aria-label="'Posición retaguardia de ' + nombreFormateado(getMember(team, slot))"
                                                            :aria-pressed="formacionDe(team, slot) === 'retaguardia'"
                                                            :title="'Posición retaguardia de ' + nombreFormateado(getMember(team, slot))"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path d="M14.5 17.5L3 6V3h3l11.5 11.5M13 19l6-6M16 16l4 4M19 21l2-2"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <!-- Postura de ronda -->
                                                    <select
                                                        :value="posturaDe(team, slot)"
                                                        @change="setPosturaSlot(team, slot, $event.target.value)"
                                                        class="mt-1 w-full rounded border border-line-strong bg-surface px-1 py-0.5 text-2xs text-ink border-line-strong bg-surface text-ink"
                                                        :aria-label="'Postura de ' + nombreFormateado(getMember(team, slot))"
                                                        :title="'Postura de ronda de ' + nombreFormateado(getMember(team, slot))"
                                                    >
                                                        <option value="atacar">Atacar</option>
                                                        <option value="guardia">Guardia</option>
                                                        <option value="cubrir">Cubrir</option>
                                                    </select>
                                                    <select
                                                        :value="itemDe(team, slot)"
                                                        @change="guardarItemSlot(team, slot, $event.target.value)"
                                                        :disabled="itemSavingTeamId === team.id"
                                                        class="mt-1 w-full rounded border border-line-strong bg-surface px-1 py-0.5 text-2xs text-ink disabled:opacity-40 disabled:cursor-not-allowed border-line-strong bg-surface text-ink"
                                                        :aria-label="'Objeto de ' + nombreFormateado(getMember(team, slot))"
                                                        :title="'Objeto equipado de ' + nombreFormateado(getMember(team, slot))"
                                                    >
                                                        <option value="">Sin objeto</option>
                                                        @foreach($objetos as $clave => $nombre)
                                                            <option value="{{ $clave }}">{{ $nombre }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </template>
                                            <template x-if="!getMember(team, slot)">
                                                <div>
                                                    <div class="w-full h-16 mx-auto rounded border-2 border-dashed border-line-strong flex items-center justify-center">
                                                        <span class="text-ink-subtle text-lg">+</span>
                                                    </div>
                                                    <p class="text-2xs text-ink-subtle mt-0.5">Slot <span x-text="slot"></span></p>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <!-- Guardar formación -->
                            <div class="px-3 pb-3">
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="guardarFormacion(team)"
                                        :disabled="formacionSavingTeamId === team.id"
                                        class="flex-1 px-3 py-1.5 bg-ink text-ink-inverse rounded-control text-2xs font-bold uppercase tracking-wide hover:opacity-90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                        x-text="formacionSavingTeamId === team.id ? 'Guardando...' : 'Guardar formación'"
                                    ></button>
                                </div>
                                <div x-show="formacionSaveError" x-cloak role="alert" class="mt-1 text-2xs text-danger-ink" x-text="formacionSaveError"></div>
                                <div x-show="formacionSaveSuccess" x-cloak role="status" class="mt-1 text-2xs text-success-ink" x-text="formacionSaveSuccess"></div>
                                <div class="flex items-center gap-2 mt-2">
                                    <button
                                        @click="guardarPosturas(team)"
                                        :disabled="posturaSavingTeamId === team.id"
                                        class="flex-1 px-3 py-1.5 bg-primary text-on-primary rounded-control text-2xs font-bold uppercase tracking-wide hover:bg-primary-hover transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                        x-text="posturaSavingTeamId === team.id ? 'Guardando...' : 'Guardar posturas'"
                                    ></button>
                                </div>
                                <div x-show="posturaSaveError" x-cloak role="alert" class="mt-1 text-2xs text-danger-ink" x-text="posturaSaveError"></div>
                                <div x-show="posturaSaveSuccess" x-cloak role="status" class="mt-1 text-2xs text-success-ink" x-text="posturaSaveSuccess"></div>
                                <div x-show="itemSaveError" x-cloak role="alert" class="mt-1 text-2xs text-danger-ink" x-text="itemSaveError"></div>
                                <div x-show="itemSaveSuccess" x-cloak role="status" class="mt-1 text-2xs text-success-ink" x-text="itemSaveSuccess"></div>
                            </div>
                        </div>
                    </template>

                    <!-- Empty teams -->
                    <template x-if="teams.length === 0">
                        <div class="text-center py-8 bg-surface rounded-card shadow-sm border border-line">
                            <svg class="w-12 h-12 mx-auto text-ink-subtle mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="text-sm text-ink-muted">No hay equipos creados</p>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Right 2/3: Available Pokemon (equipos) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Section switcher: Pokémon / Huevos -->
                <div class="flex rounded-card bg-surface-sunken border border-line p-1" role="tablist" aria-label="Secciones del equipo">
                    <button
                        @click="cambiarSeccion('pokemon')"
                        role="tab"
                        :aria-selected="activeSeccion === 'pokemon'"
                        class="flex-1 px-4 py-2 rounded-control text-sm font-medium transition-colors"
                        :class="activeSeccion === 'pokemon'
                            ? 'bg-surface text-primary-ink shadow-sm'
                            : 'text-ink-muted hover:text-ink'"
                    >
                        ⚔️ Pokémon
                    </button>
                    <button
                        @click="cambiarSeccion('huevos')"
                        role="tab"
                        :aria-selected="activeSeccion === 'huevos'"
                        class="flex-1 px-4 py-2 rounded-control text-sm font-medium transition-colors"
                        :class="activeSeccion === 'huevos'
                            ? 'bg-surface text-primary-ink shadow-sm'
                            : 'text-ink-muted hover:text-ink'"
                    >
                        🥚 Huevos
                    </button>
                </div>

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
                                    ⏳ <span x-text="limiteMensaje()"></span>
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
                                                            <span x-show="huevosAccionId !== huevo.id">🥚 Eclosionar</span>
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
            </div>
        </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- MODALS                                                              -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->

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
                            ⚠️ <span x-text="habitatsError"></span>
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

    <!-- Confirm Delete Team Modal -->
    <template x-if="showDeleteModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="showDeleteModal = false" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
            <div class="absolute inset-0 bg-scrim" @click="showDeleteModal = false"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-sm p-6">
                <div class="text-center mb-4">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-danger-soft flex items-center justify-center">
                        <svg class="w-6 h-6 text-danger-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </div>
                    <h3 id="delete-modal-title" class="text-lg font-bold text-ink">Eliminar equipo</h3>
                    <p class="text-sm text-ink-muted mt-2">
                        ¿Seguro que quieres eliminar <strong class="text-ink" x-text="teamToDelete?.name"></strong>? Los miembros serán liberados.
                    </p>
                </div>
                <div class="flex gap-3">
                    <button
                        @click="showDeleteModal = false; teamToDelete = null"
                        class="flex-1 px-4 py-2.5 bg-surface-sunken text-ink rounded-control text-sm font-medium hover:bg-line transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="deleteTeam()"
                        class="flex-1 px-4 py-2.5 bg-danger text-on-primary rounded-control text-sm font-bold hover:bg-danger/90 transition-colors"
                    >
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    </template>

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

    <!-- Pokemon Detail Modal (shared) -->
    <template x-if="showDetailModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeDetail()" role="dialog" aria-modal="true" aria-labelledby="detail-modal-title">
            <div class="absolute inset-0 bg-scrim" @click="closeDetail()"></div>
                <div class="relative bg-surface rounded-panel shadow-modal w-[90vw] max-w-[90vw] max-h-[94vh] overflow-y-auto pokemon-modal-quality" :class="calidadClase(detailPokemon)">

                <div class="flex items-start gap-4 p-6 border-b border-line">
                    <div class="w-32 h-32 shrink-0 rounded-card pokemon-quality flex items-center justify-center overflow-hidden" :class="calidadClase(detailPokemon)">
                        <x-sprite
                            size="128"
                            x-bind:src="iconoPrincipalDe(detailPokemon)"
                            x-bind:alt="nombreDe(detailPokemon)"
                            x-on:error="onSpriteError($event, detailPokemon.pokemon_id, detailPokemon.es_shiny)"
                        />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 id="detail-modal-title" class="text-2xl font-bold text-ink capitalize truncate" x-text="nombreDe(detailPokemon)"></h3>
                            <template x-if="detailPokemon.es_shiny">
                                <span class="px-1.5 py-0.5 bg-warning-soft text-warning-ink text-2xs font-bold rounded uppercase" title="Shiny">★ Shiny</span>
                            </template>
                        </div>
                        <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                            <template x-if="(detailPokemon.pokemon?.types || []).length">
                                <template x-for="t in detailPokemon.pokemon.types" :key="'header-type-'+(t.id ?? t.slot ?? t.type)">
                                    <span class="tipo-badge text-xs font-semibold inline-flex items-center gap-1" :style="'--type-color: ' + tipoBadge(tipoLabelDel(t)).color">
                                        <img :src="tipoIconoDeTipo(t)" :alt="tipoLabelDel(t)" loading="lazy" decoding="async" class="w-5 h-5 object-contain">
                                        <span x-text="tipoLabelDel(t)"></span>
                                    </span>
                                </template>
                            </template>
                        </div>
                        <div class="flex items-center gap-2 mt-2 flex-wrap">
                            <span class="px-2 py-0.5 bg-primary-soft text-primary-ink text-xs font-bold rounded-full">Nv <span x-text="nivelDe(detailPokemon)"></span></span>
                            <template x-if="cpDe(detailPokemon) != null">
                                <span class="px-2 py-0.5 bg-info-soft text-info-ink text-xs font-bold rounded-full" title="Poder de combate">CP <span x-text="cpDe(detailPokemon)"></span></span>
                            </template>
                            <span class="text-3xs font-medium text-ink-subtle" x-text="getTeamName(pokemonTeamId(detailPokemon)) || 'Sin equipo'"></span>
                        </div>
                    </div>
                    <div class="shrink-0 flex items-center gap-2">
                        <button
                            @click="releasePokemon()"
                            :disabled="detailLoading || (detailPokemon && (enExploracion(detailPokemon.id) || pokemonEnExploracion(detailPokemon)))"
                            :title="detailPokemon && (enExploracion(detailPokemon.id) || pokemonEnExploracion(detailPokemon)) ? 'El Pokémon está en exploración' : 'Liberar Pokémon'"
                            aria-label="Liberar Pokémon"
                            class="inline-flex items-center justify-center gap-1 px-2.5 py-2 bg-danger text-on-primary rounded-control text-xs font-bold hover:bg-danger/90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            <span class="hidden sm:inline">Liberar</span>
                        </button>
                        <button @click="closeDetail()" class="w-8 h-8 flex items-center justify-center rounded-control text-ink-subtle hover:text-ink hover:bg-line transition-colors" aria-label="Cerrar">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                        <!-- Columna 2/3: evolución, habilidades y movimientos -->
                        <div class="xl:col-span-2 space-y-4">
                            <!-- Evolución (solo si el Pokémon puede evolucionar) -->
                            <template x-if="detailEvoluciones.length > 0">
                                <div class="rounded-panel border border-line bg-surface-sunken p-4">
                                    <h4 class="text-sm font-bold uppercase tracking-wide text-ink-muted mb-2">Evolución</h4>
                                    <template x-if="detailPokemon && (enExploracion(detailPokemon.id) || pokemonEnExploracion(detailPokemon))">
                                        <div class="mb-3 px-3 py-2 rounded-control bg-warning-soft text-warning-ink text-xs" role="status">Este Pokémon está en exploración activa: no puedes evolucionarlo hasta que vuelva.</div>
                                    </template>
                                    <div class="space-y-3">
                                        <template x-if="detailEvoluciones.length > 1">
                                            <div>
                                                <p class="text-3xs text-ink-muted mb-2">Selecciona a qué Pokémon evolucionar</p>
                                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                                    <template x-for="opcion in detailEvoluciones" :key="opcion.pokemon_id">
                                                        <button @click="selectedEvolucionId = opcion.pokemon_id" class="flex flex-col items-center gap-1 p-2 rounded-control border-2 transition-colors" :class="selectedEvolucionId === opcion.pokemon_id ? 'border-primary ring-2 ring-primary/30' : 'border-line hover:border-line-strong'" :aria-pressed="selectedEvolucionId === opcion.pokemon_id" :aria-label="'Evolucionar a ' + opcion.nombre">
                                                            <x-sprite size="96" x-bind:src="opcion.imagen || '/images/iconos_webp/' + opcion.pokemon_id + '.webp'" x-bind:alt="opcion.nombre" />
                                                            <span class="text-2xs text-ink capitalize truncate" x-text="opcion.nombre"></span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="opcionSeleccionada()">
                                            <div class="space-y-2.5">
                                                <template x-for="req in opcionSeleccionada().requisitos" :key="req.slug">
                                                    <div>
                                                        <div class="flex items-center justify-between mb-1"><span class="text-2xs text-ink-muted" x-text="req.tipo"></span><span class="text-2xs text-ink-subtle" x-text="formatExp(req.actual) + ' / ' + formatExp(req.necesario)"></span></div>
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <div class="flex-1 min-w-[7rem] h-1.5 bg-surface-sunken rounded-full overflow-hidden" role="progressbar" :aria-valuenow="porcentajeExpRequisito(req)" aria-valuemin="0" aria-valuemax="100"><div class="h-full bg-primary rounded-full transition-all duration-500" :style="'width: ' + porcentajeExpRequisito(req) + '%'"></div></div>
                                                            <div class="flex shrink-0 items-center gap-1">
                                                                <span class="inline-flex items-center gap-1 rounded-full bg-surface-sunken px-1.5 py-0.5 text-2xs font-semibold text-ink-muted bg-surface-sunken text-ink" :title="'Caramelos de ' + req.tipo + ' disponibles: ' + req.caramelosDisponibles">
                                                                    <img :src="'/images/candy_type/' + req.slug + '.webp'" loading="lazy" decoding="async" :alt="'Caramelo de ' + req.tipo" class="w-3.5 h-3.5 object-contain" onerror="this.onerror=null; this.src='/images/candy_pokemon/0.webp';">
                                                                    <span x-text="'×' + req.caramelosDisponibles"></span>
                                                                </span>
                                                                <template x-for="cantidad in [1, 10, 100]" :key="'feed-'+req.slug+'-'+cantidad">
                                                                    <button type="button" @click="alimentarCaramelo(req, cantidad)" :disabled="!puedeAlimentar(req) || detailCandyFeeding" :title="'Consumir hasta ' + cantidad + ' caramelo(s) de ' + req.tipo + ' (+' + (cantidad * 100) + ' exp máx.)'" class="px-1.5 py-0.5 rounded-control bg-warning text-on-primary text-2xs font-bold hover:bg-warning/90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed" x-text="'x' + cantidad"></button>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="mt-3 border-t border-line pt-3">
                                        <button @click="evolvePokemon()" :disabled="detailLoading || (detailPokemon && (enExploracion(detailPokemon.id) || pokemonEnExploracion(detailPokemon))) || !opcionSeleccionada()?.puede_evolucionar" :title="(detailPokemon && (enExploracion(detailPokemon.id) || pokemonEnExploracion(detailPokemon))) ? 'El Pokémon está en exploración' : (opcionSeleccionada()?.puede_evolucionar ? 'Evolucionar Pokémon' : 'Completa la exp de tipo para evolucionar')" class="w-full px-4 py-2.5 bg-success text-on-primary rounded-control text-sm font-bold hover:bg-success/90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                            <span x-text="detailLoading ? 'Evolucionando…' : 'Evolucionar'"></span>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- Habilidades -->
                            <div class="rounded-panel border border-line bg-surface-sunken p-4">
                                <h4 class="text-sm font-bold uppercase tracking-wide text-ink-muted mb-2">Habilidades</h4>
                                <template x-if="(detailPokemon.habilidades_disponibles || []).length > 0">
                                    <div class="space-y-2">
                                        <template x-for="hab in detailPokemon.habilidades_disponibles" :key="'detail-hab-'+hab.id">
                                            <button type="button" @click="loadoutHabilidadId = (loadoutHabilidadId == hab.id ? '' : hab.id)" class="w-full text-left rounded-card border p-3 transition-colors" :class="loadoutHabilidadId == hab.id ? 'border-success bg-success-soft' : 'border-line bg-surface-sunken hover:border-success'">
                                                <div class="flex items-center justify-between gap-2"><span class="text-sm font-semibold text-ink" x-text="hab.name"></span><span x-show="loadoutHabilidadId == hab.id" class="text-3xs font-bold text-success-ink">ACTIVA</span></div>
                                                <p class="mt-1 text-xs leading-snug text-ink-muted" x-text="hab.description || 'Sin descripción.'"></p>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="(detailPokemon.habilidades_disponibles || []).length === 0">
                                    <p class="text-xs text-ink-subtle">Esta especie no tiene habilidades registradas.</p>
                                </template>
                            </div>

                            <!-- Movimientos asignados -->
                            <div class="rounded-panel border border-primary bg-primary-soft p-4">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <h4 class="text-sm font-bold uppercase tracking-wide text-primary-ink">Movimientos asignados</h4>
                                    <span class="text-3xs text-primary-ink" x-text="loadoutMovimientos.length + '/6'"></span>
                                </div>
                                <p class="text-xs text-ink-muted mb-2">Habilidad activa: <strong x-text="nombreHabilidadLoadout()"></strong></p>
                                <div class="flex flex-wrap gap-1.5 mb-3">
                                    <template x-for="slug in loadoutMovimientos" :key="'loadout-chip-'+slug">
                                        <span class="inline-flex items-center gap-1 pl-1 pr-1.5 py-0.5 rounded-full bg-surface border border-primary text-3xs text-primary-ink">
                                            <img :src="tipoIconoUrl(movimientoLoadout(slug))" :alt="tipoDeMovimiento(movimientoLoadout(slug)) || 'Tipo'" loading="lazy" decoding="async" class="w-4 h-4 object-contain">
                                            <span x-text="nombreMovimientoLoadout(slug) || slug"></span>
                                            <button type="button" @click="toggleLoadoutMovimiento(slug)" class="w-4 h-4 flex items-center justify-center rounded-full text-primary-ink hover:text-danger-ink hover:bg-danger-soft transition-colors" :aria-label="'Quitar ' + nombreMovimientoLoadout(slug) + ' del loadout'" title="Quitar del loadout">×</button>
                                        </span>
                                    </template>
                                    <span x-show="loadoutMovimientos.length === 0" class="text-3xs text-ink-subtle">Sin selección: se usa el catálogo desbloqueado completo.</span>
                                </div>
                                <button type="button" @click="guardarLoadout()" :disabled="loadoutSaving" class="w-full px-3 py-2 bg-primary text-on-primary rounded-control text-xs font-bold hover:bg-primary-hover transition-colors disabled:opacity-40 disabled:cursor-not-allowed" x-text="loadoutSaving ? 'Guardando…' : 'Guardar loadout'"></button>
                                <div x-show="loadoutFeedback.mensaje" x-cloak role="status" class="mt-2 px-2 py-1.5 rounded text-2xs" :class="loadoutFeedback.tipo === 'error' ? 'bg-danger-soft text-danger-ink' : 'bg-success-soft text-success-ink'" x-text="loadoutFeedback.mensaje"></div>
                            </div>

                            <!-- Movimientos disponibles -->
                            <div class="rounded-panel border border-line bg-surface-sunken p-4">
                                <div class="flex items-center justify-between mb-2 gap-2">
                                    <h4 class="text-sm font-bold uppercase tracking-wide text-ink-muted">Movimientos disponibles</h4>
                                    <span class="text-3xs text-ink-subtle"><span x-text="movimientosFiltrados.length + ' / ' + (detailPokemon.movimientos_disponibles || []).length"></span> · <span x-text="loadoutMovimientos.length + ' asignados'"></span></span>
                                </div>
                                <template x-if="detailMovimientosLoading">
                                    <p class="text-xs text-ink-subtle animate-pulse">Cargando movimientos…</p>
                                </template>
                                <template x-if="detailMovimientosError">
                                    <p class="text-xs text-warning-ink" role="alert" x-text="detailMovimientosError"></p>
                                </template>
                                <template x-if="(detailPokemon.movimientos_disponibles || []).length > 0">
                                    <div class="space-y-2 mb-2">
                                        <div class="relative max-w-sm">
                                            <input type="text" x-model="filtroNombreMovimiento" placeholder="Filtrar movimientos por nombre..." class="w-full px-3 py-1.5 pl-9 bg-surface border border-line rounded-control text-xs text-ink placeholder:text-ink-subtle focus-ring transition-colors">
                                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <template x-for="tipo in tiposMovimientoDisponibles" :key="'filtro-tipo-'+tipo.slug">
                                                <button type="button" @click="toggleFiltroTipoMovimiento(tipo.slug)" :aria-pressed="filtroTipoMovimientoActivo(tipo.slug) ? 'true' : 'false'" :title="'Filtrar por tipo ' + tipo.nombre" class="tipo-badge inline-flex items-center gap-1 font-semibold transition-opacity" :style="'--type-color: ' + tipoBadge(tipo.slug).color" :class="filtroTipoMovimientoActivo(tipo.slug) ? 'border-ink' : 'bg-surface text-ink-muted border-line opacity-60 hover:opacity-100'">
                                                    <img :src="'/images/type/' + tipo.slug + '.webp'" :alt="tipo.nombre" loading="lazy" decoding="async" class="w-5 h-5 object-contain"><span x-text="tipo.nombre"></span>
                                                </button>
                                            </template>
                                            <span class="mx-1 h-4 w-px bg-surface-sunken" aria-hidden="true"></span>
                                            <template x-for="categoria in categoriasMovimientoDisponibles" :key="'filtro-categoria-'+categoria">
                                                <button type="button" @click="toggleFiltroCategoriaMovimiento(categoria)" :aria-pressed="filtroCategoriaMovimientoActiva(categoria) ? 'true' : 'false'" :title="'Filtrar por categoría ' + etiquetaCategoriaSlug(categoria)" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-3xs font-semibold transition-opacity" :class="filtroCategoriaMovimientoActiva(categoria) ? claseCategoria(categoria) + ' border-ink' : 'bg-surface text-ink-muted border-line opacity-60 hover:opacity-100'">
                                                    <img :src="categoriaIconoDe(categoria)" :alt="etiquetaCategoriaSlug(categoria)" loading="lazy" decoding="async" class="w-5 h-5 object-contain"><span x-text="etiquetaCategoriaSlug(categoria)"></span>
                                                </button>
                                            </template>
                                            <button type="button" x-show="hayFiltrosMovimiento()" @click="limpiarFiltrosMovimiento()" class="ml-auto text-3xs font-medium text-primary-ink hover:underline" title="Quitar todos los filtros">Limpiar filtros</button>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="(detailPokemon.movimientos_disponibles || []).length > 0">
                                    <div class="space-y-5">
                                        <!-- Movimientos de nivel -->
                                        <div>
                                            <div class="flex items-center justify-between gap-2 mb-2">
                                                <h5 class="text-xs font-bold uppercase tracking-wide text-ink-muted">Movimientos de nivel</h5>
                                                <span class="text-3xs text-ink-subtle" x-text="movimientosNivelFiltrados.length + ' movimientos'"></span>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-3 gap-2">
                                                <template x-for="mov in movimientosNivelFiltrados" :key="'detail-mov-nivel-'+mov.slug">
                                                    @include('equipos.partials.movimiento-card')
                                                </template>
                                            </div>
                                            <p x-show="movimientosNivelFiltrados.length === 0" class="text-xs text-ink-subtle">Ningún movimiento de nivel coincide con los filtros.</p>
                                        </div>

                                        <!-- Desbloqueables (Sendas) -->
                                        <div>
                                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                                <div>
                                                    <h5 class="text-xs font-bold uppercase tracking-wide text-warning-ink">Desbloqueables</h5>
                                                    <p class="text-2xs text-ink-subtle">Se desbloquean en las Sendas y quedan disponibles para toda la cuenta. Los tramos 4-5 son maestrías pasivas.</p>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-3xs text-ink-subtle" x-text="movimientosSendaFiltrados.length + ' movimientos'"></span>
                                                    <a href="/sendas" class="px-2 py-1 rounded-control bg-warning text-on-primary text-2xs font-bold hover:bg-warning/90 transition-colors">Ir a Sendas</a>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-3 gap-2">
                                                <template x-for="mov in movimientosSendaFiltrados" :key="'detail-mov-senda-'+mov.slug">
                                                    @include('equipos.partials.movimiento-card')
                                                </template>
                                            </div>
                                            <p x-show="movimientosSendaFiltrados.length === 0" class="text-xs text-ink-subtle">Ningún movimiento desbloqueable coincide con los filtros.</p>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="(detailPokemon.movimientos_disponibles || []).length > 0 && movimientosFiltrados.length === 0">
                                    <p class="text-xs text-ink-subtle">Ningún movimiento coincide con los filtros seleccionados.</p>
                                </template>
                                <template x-if="!detailMovimientosLoading && !detailMovimientosError && (detailPokemon.movimientos_disponibles || []).length === 0">
                                    <p class="text-xs text-ink-subtle">Esta especie no tiene movimientos de catálogo.</p>
                                </template>
                            </div>
                        </div>

                        <!-- Columna 1/3: stats -->
                        <div class="space-y-4">
                            <!-- Stats calculados con el nivel de la cuenta -->
                            <div class="rounded-panel border border-line bg-surface-sunken p-4">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <h4 class="text-sm font-bold uppercase tracking-wide text-ink-muted">Stats</h4>
                                    <span class="text-2xs text-ink-subtle" title="Nivel de la cuenta">Nv <span x-text="nivelJugador"></span></span>
                                </div>
                                <div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-2xs font-semibold text-ink-muted">
                                    <span class="inline-flex items-center gap-1"><span class="h-1.5 w-3 rounded-full bg-success"></span>Stats</span>
                                    <span class="inline-flex items-center gap-1"><span class="h-1.5 w-3 rounded-full bg-primary"></span>IV</span>
                                    <span class="inline-flex items-center gap-1"><span class="h-1.5 w-3 rounded-full bg-warning"></span>EV</span>
                                </div>
                                <div class="relative w-full" style="height: 180px;"><canvas id="detail-calculated-chart" role="img" aria-label="Gráfico hexagonal de stats"></canvas></div>
                                <div class="mt-3 space-y-1.5">
                                    <template x-for="stat in hexStatsCalculadosConAporte(detailPokemon)" :key="'calc-stat-'+stat.label">
                                        <div class="grid grid-cols-[2.5rem_1fr_2.5rem] items-center gap-2">
                                            <span class="text-2xs font-semibold text-ink-muted truncate" x-text="stat.label"></span>
                                            <div class="h-1.5 bg-surface-sunken rounded-full overflow-hidden" :title="'Stats ' + stat.base + ' + IV ' + stat.iv + ' + EV ' + stat.ev + ' = ' + stat.total">
                                                <div class="flex h-full w-full">
                                                    <div class="h-full bg-success" :style="'width: ' + Math.min(100, (stat.base / escalaCalculada(detailPokemon)) * 100) + '%'"></div>
                                                    <div x-show="stat.iv > 0" class="h-full bg-primary" :style="'width: ' + Math.min(100, (stat.iv / escalaCalculada(detailPokemon)) * 100) + '%'"></div>
                                                    <div x-show="stat.ev > 0" class="h-full bg-warning" :style="'width: ' + Math.min(100, (stat.ev / escalaCalculada(detailPokemon)) * 100) + '%'"></div>
                                                </div>
                                            </div>
                                            <span class="text-3xs font-bold text-right text-ink" x-text="stat.total"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Entrenamiento EV -->
                            <template x-if="detailPokemon && (detailPokemon.evs || detailPokemon.ev)">
                                <div>
                                    <div class="flex items-center justify-between gap-2 mb-2">
                                        <h4 class="text-sm font-bold uppercase tracking-wide text-ink-muted">Entrenamiento (EV)</h4>
                                        <span class="text-2xs text-ink-subtle">Máx. 255</span>
                                    </div>
                                    <template x-if="detailEvFeedback.mensaje">
                                        <div class="px-3 py-2 rounded-control text-xs mb-2" role="status" :class="detailEvFeedback.tipo === 'error' ? 'bg-danger-soft text-danger-ink' : 'bg-success-soft text-success-ink'" x-text="detailEvFeedback.mensaje"></div>
                                    </template>
                                    <div class="grid grid-cols-3 gap-2">
                                        <template x-for="stat in evStats" :key="'ev-'+stat.key">
                                            <div class="flex flex-col items-center text-center gap-1 rounded-card border border-line bg-surface-sunken p-1.5">
                                                <div class="relative">
                                                    <img :src="'/images/candy_ev/' + slugCorto(stat.key) + '.webp'" loading="lazy" decoding="async" :alt="'Caramelo EV de ' + stat.label" class="w-8 h-8 object-contain" onerror="this.style.display='none'">
                                                    <span class="absolute -right-1 -top-1 min-w-[1.25rem] rounded-full px-1 py-0.5 text-center text-2xs font-bold leading-none shadow" :class="evCaramelosDe(detailPokemon, stat.key) > 0 ? 'bg-warning text-on-primary' : 'bg-surface-sunken text-ink-muted bg-surface-sunken text-ink'" :title="'Caramelos EV disponibles: ' + evCaramelosDe(detailPokemon, stat.key)" x-text="'×' + evCaramelosDe(detailPokemon, stat.key)"></span>
                                                </div>
                                                <span class="text-2xs font-semibold text-ink-muted truncate w-full" x-text="stat.label"></span>
                                                <div class="flex items-baseline gap-0.5"><span class="text-xs font-bold text-ink" x-text="evValue(detailPokemon, stat.key)"></span><span class="text-2xs text-ink-subtle">/255</span></div>
                                                <div class="w-full h-1.5 bg-surface-sunken rounded-full overflow-hidden"><div class="h-full bg-warning rounded-full" :style="'width: ' + evPct(evValue(detailPokemon, stat.key)) + '%'"></div></div>
                                                <div class="mt-1 grid w-full grid-cols-2 gap-1">
                                                    <button @click="darEv(stat.key, 1)" :disabled="!puedeDarEv(detailPokemon, stat.key)" :title="!puedeDarEv(detailPokemon, stat.key) ? 'No puedes dar caramelos EV ahora mismo' : 'Usar hasta 1 caramelo EV (+10 a ' + stat.label + ')'" class="w-full rounded-control bg-warning px-1 py-1 text-2xs font-bold text-on-primary transition-colors hover:bg-warning/90 disabled:cursor-not-allowed disabled:opacity-40">+10</button>
                                                    <button @click="darEv(stat.key, 10)" :disabled="!puedeDarEv(detailPokemon, stat.key)" :title="!puedeDarEv(detailPokemon, stat.key) ? 'No puedes dar caramelos EV ahora mismo' : 'Usar hasta 10 caramelos EV (+100 a ' + stat.label + ')'" class="w-full rounded-control bg-warning px-1 py-1 text-2xs font-bold text-on-primary transition-colors hover:bg-warning/90 disabled:cursor-not-allowed disabled:opacity-40">+100</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Stats base -->
                            <div class="rounded-panel border border-line bg-surface-sunken p-4">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <h4 class="text-sm font-bold uppercase tracking-wide text-ink-muted">Stats base</h4>
                                    <span class="text-2xs text-ink-subtle">0–<span x-text="escalaStats(detailPokemon)"></span></span>
                                </div>
                                <div class="relative w-full" style="height: 180px;"><canvas id="detail-stats-chart" role="img" aria-label="Gráfico hexagonal de stats base"></canvas></div>
                                <div class="mt-3 space-y-1.5">
                                    <template x-for="stat in hexStatsBarras(detailPokemon)" :key="'base-stat-'+stat.label">
                                        <div class="grid grid-cols-[2.5rem_1fr_2.5rem] items-center gap-2">
                                            <span class="text-2xs font-semibold text-ink-muted truncate" x-text="stat.label"></span>
                                            <div class="h-1.5 bg-surface-sunken rounded-full overflow-hidden">
                                                <div class="h-full bg-primary rounded-full" :style="'width: ' + Math.min(100, (stat.value / escalaStats(detailPokemon)) * 100) + '%'"></div>
                                            </div>
                                            <span class="text-3xs font-bold text-right text-ink" x-text="stat.value"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Valores IV -->
                            <div class="rounded-panel border border-line bg-surface-sunken p-4" :class="calidadClase(detailPokemon)">
                                <h4 class="text-sm font-bold uppercase tracking-wide text-ink-muted mb-2">Valores IV</h4>
                                <div class="relative w-full" style="height: 180px;"><canvas id="detail-iv-chart" role="img" aria-label="Gráfico hexagonal de valores individuales"></canvas></div>
                                <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5">
                                    <template x-for="stat in evStats" :key="'iv-stat-'+stat.key">
                                        <div class="flex items-center gap-1.5" :class="ivCalidadClase(ivValue(detailPokemon, stat.key))" :title="stat.label + ': ' + ivValue(detailPokemon, stat.key) + '/15'">
                                            <span class="w-10 shrink-0 text-2xs text-ink-muted truncate" x-text="stat.corto || stat.label"></span>
                                            <div class="flex-1 h-1.5 bg-surface-sunken rounded-full overflow-hidden"><div class="h-full rounded-full iv-bar-fill" :style="'width: ' + (ivValue(detailPokemon, stat.key) / 15 * 100) + '%'"></div></div>
                                            <span class="text-3xs font-bold iv-value" x-text="ivValue(detailPokemon, stat.key)"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

</div>

@push('scripts')
@include('partials.tipos-badge-script')
<script>
function favoritosApp() {
    return {
        // ─── Datos del servidor ─────────────────────────────────────────────
        nivelJugador: @json($nivelJugador ?? 1),
        reclutados: @json($reclutados ?? []),
        reclutadosEnExploracion: @json($reclutadosEnExploracion ?? []),
        teams: @json($teams ?? []),
        teamPokemonIds: @json($teamIds ?? []),
        inventarioCaramelos: @json($inventarioCaramelos ?? ['ev' => []]),

        // ─── Formación persistente (combate 3v3) ───────────────────────────
        // Draft local por equipo: {teamId: {slot: 'vanguardia'|'retaguardia'}}.
        // La formación persistida del backend (team.formacion) recién se conoce
        // tras el primer PATCH; el draft tiene prioridad sobre ella.
        formacionDraft: {},
        formacionSavingTeamId: null,
        formacionSaveError: '',
        formacionSaveSuccess: '',

        // ─── Posturas persistentes por miembro ─────────────────────────────
        // Draft local: {teamId: {slot: 'atacar'|'guardia'|'cubrir'}}.
        posturasDraft: {},
        posturaSavingTeamId: null,
        posturaSaveError: '',
        posturaSaveSuccess: '',

        // ─── Objetos equipados por miembro ──────────────────────────────────
        itemsDraft: {},
        itemSavingTeamId: null,
        itemSaveError: '',
        itemSaveSuccess: '',

        // ─── UI: Equipos ───────────────────────────────────────────────────
        // ─── UI: Buscar/filtro ─────────────────────────────────────────────
        searchQuery: '',
        typeFilters: [],

        selectedTeamId: null,
        showNewTeamForm: false,
        newTeamName: '',
        showDeleteModal: false,
        teamToDelete: null,

        // ─── Reemplazo de miembro en equipo completo ───────────────────────
        showReplaceModal: false,
        replaceTeam: null,
        replacePokemon: null,
        replaceSaving: false,
        replaceError: '',

        // ─── Detail Modal (shared) ─────────────────────────────────────────
        showDetailModal: false,
        detailPokemon: null,
        detailLoading: false,
        detailEvoluciones: [],
        detailEvolucionesLoading: false,
        detailEvolucionesError: false,
        selectedEvolucionId: null,
        detailChartInstances: [],
        // Catálogo de movimientos cargado bajo demanda al abrir el modal.
        detailMovimientosLoading: false,
        detailMovimientosError: '',
        movimientosCargadosIds: [],
        movimientosCargandoIds: [],

        // ─── Detail: loadout de combate ─────────────────────────────────────
        loadoutHabilidadId: '',
        loadoutMovimientos: [],
        filtrosMovimientoTipo: [],
        filtrosMovimientoCategoria: [],
        filtroNombreMovimiento: '',
        loadoutSaving: false,
        loadoutFeedback: { tipo: '', mensaje: '' },

        // ─── Detail: EV feeding (caramelos EV / IVs) ─────────────────────────
        evStats: [
            { key: 'hp', label: 'PS', corto: 'PS' },
            { key: 'atk', label: 'Ataque', corto: 'ATK' },
            { key: 'def', label: 'Defensa', corto: 'DEF' },
            { key: 'spAtk', label: 'Ataque Esp.', corto: 'A.Esp' },
            { key: 'spDef', label: 'Defensa Esp.', corto: 'D.Esp' },
            { key: 'speed', label: 'Velocidad', corto: 'VEL' },
        ],
        detailEvsFeeding: false,
        detailCandyFeeding: false,
        detailEvFeedback: { tipo: '', mensaje: '' },

        // ─── Huevos tab ──────────────────────────────────────────────────────
        activeSeccion: 'pokemon',
        huevos: [],
        huevosDisponibles: [],
        huevosLimites: null,
        huevosLoaded: false,
        huevosLoading: false,
        huevosError: '',
        huevosActuando: false,
        huevosAccionId: null,
        huevosFeedback: { tipo: '', mensaje: '' },

        // ─── Exploration Modal ──────────────────────────────────────────────
        showExploracionModal: false,
        exploracionPokemon: null,
        habitats: [],
        habitatsLoading: false,
        habitatsError: '',
        exploracionHabitatId: null,
        exploracionLevel: 1,
        durationMode: 'hours',
        durationHours: 4,
        returnTime: '18:00',
        preview: null,
        previewLoading: false,
        previewLoaded: false,
        previewError: '',
        sendingExploracion: false,

        // ═══════════════════════════════════════════════════════════════════════
        // COMPUTED GETTERS
        // ═══════════════════════════════════════════════════════════════════════

        /** Equipos tab: colección ordenada por CP desde el backend; el front solo filtra. */
        get pokemonsEquipoFiltrados() {
            let result = this.reclutados.slice();
            const q = this.normalizarTexto(this.searchQuery).trim();
            if (q) {
                result = result.filter(r => {
                    const texto = [
                        r.nombre,
                        r.pokemon?.name,
                        r.pokemon_id,
                    ].map(valor => this.normalizarTexto(valor)).join(' ');
                    return texto.includes(q);
                });
            }
            if (this.typeFilters.length > 0) {
                result = result.filter(r => {
                    const tipos = Array.isArray(r.pokemon?.types) ? r.pokemon.types : [];
                    return tipos.some(t => this.typeFilters.includes(this.tipoIdDe(t)));
                });
            }

            return result;
        },

        // ═══════════════════════════════════════════════════════════════════════
        // SHARED HELPERS
        // ═══════════════════════════════════════════════════════════════════════

        normalizarTexto(valor) {
            return String(valor ?? '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
        },

        hayFiltrosEquipo() {
            return this.searchQuery.trim() !== ''
                || this.typeFilters.length > 0;
        },

        toggleTypeFilter(tipoId) {
            const id = Number(tipoId);
            this.typeFilters = this.typeFilters.includes(id)
                ? this.typeFilters.filter(actual => actual !== id)
                : [...this.typeFilters, id];
        },

        filtroTipoActivo(tipoId) {
            return this.typeFilters.includes(Number(tipoId));
        },

        tipoIdDe(tipo) {
            if (!tipo) return null;
            const raw = tipo.type ?? tipo.tipo_id ?? null;
            return raw === null ? null : Number(raw);
        },

        enExploracion(reclutadoId) {
            return this.reclutadosEnExploracion.includes(reclutadoId);
        },

        /** Número de miembros del equipo que están explorando ahora mismo. */
        teamEnExploracion(team) {
            if (!team || !Array.isArray(team.members)) return 0;
            return team.members.filter(m => this.enExploracion(m.pokemon_id)).length;
        },

        // ─── Nivel/exp ─────────────────────────────────────────────────────

        expTotalDe(pokemon) {
            if (!pokemon) return 0;
            if (typeof pokemon.exp_total === 'number') return pokemon.exp_total;
            return pokemon.exp && typeof pokemon.exp.total === 'number' ? pokemon.exp.total : 0;
        },

        // CP (poder de combate) expuesto por el backend en el serializador de reclutado.
        // Tolerante a data legacy que aún no lo entrega → devuelve null y se omite la badge.
        cpDe(pokemon) {
            if (!pokemon) return null;
            if (typeof pokemon.cp === 'number') return pokemon.cp;
            return null;
        },

        // Nombre visible del reclutado: si no tiene nombre personalizado (null), usa el
        // nombre por defecto del pokémon CAPITALIZADO (ucfirst). Sirve para todos los sitios
        // que muestran un nombre en la UI (tarjetas, detalle, formación).
        nombreDe(pokemon) {
            if (!pokemon) return '';
            let nombre = pokemon.nombre;
            if (!nombre && pokemon.pokemon && pokemon.pokemon.name) nombre = pokemon.pokemon.name;
            return String(nombre || '').charAt(0).toUpperCase() + String(nombre || '').slice(1);
        },

        /** Nombre legible: quita guiones y capitaliza cada palabra ("raticate-alola" → "Raticate Alola"). */
        nombreFormateado(pokemon) {
            if (!pokemon) return '';
            let raw = pokemon.nombre;
            if (!raw && pokemon.pokemon && pokemon.pokemon.name) raw = pokemon.pokemon.name;
            raw = String(raw || '').replace(/-/g, ' ');
            return raw.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
        },

        /** URL del icono WebP normal; fallback a 0.webp si falta el id. */
        iconoDe(pokemon) {
            if (!pokemon || !pokemon.pokemon_id) return '/images/iconos_webp/0.webp';
            return '/images/iconos_webp/' + pokemon.pokemon_id + '.webp';
        },

        /** URL del icono WebP; shiny usa `_s` y el fallback lo resuelve onSpriteError. */
        iconoPrincipalDe(pokemon) {
            if (!pokemon || !pokemon.pokemon_id) return '/images/iconos_webp/0.webp';
            if (pokemon.es_shiny) {
                return '/images/iconos_webp/shiny/' + pokemon.pokemon_id + '_s.webp';
            }
            return '/images/iconos_webp/' + pokemon.pokemon_id + '.webp';
        },

        /** Lee un token CSS resuelto (Chart.js no acepta `var()`). */
        tokenCss(nombre) {
            if (typeof document === 'undefined') return '';
            return getComputedStyle(document.documentElement).getPropertyValue(nombre).trim();
        },

        /** Convierte `#rrggbb` o `rgb(...)`/`rgba(...)` a "r, g, b". */
        colorARgb(color) {
            const valor = String(color || '').trim();
            if (valor.startsWith('#')) {
                let hex = valor.slice(1);
                if (hex.length === 3) {
                    hex = hex.split('').map(c => c + c).join('');
                }
                if (hex.length !== 6) return '';
                const r = parseInt(hex.slice(0, 2), 16);
                const g = parseInt(hex.slice(2, 4), 16);
                const b = parseInt(hex.slice(4, 6), 16);
                return [r, g, b].every(n => Number.isFinite(n)) ? r + ', ' + g + ', ' + b : '';
            }
            const rgb = valor.match(/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)/i);
            return rgb ? Number(rgb[1]) + ', ' + Number(rgb[2]) + ', ' + Number(rgb[3]) : '';
        },

        /** Fallback de sprites: shiny → normal → 0.webp → ocultar. */
        onSpriteError(event, pokemonId, esShiny) {
            const img = event?.target;
            if (!img) return;
            if (!pokemonId) {
                img.style.display = 'none';
                return;
            }
            const intento = img.dataset.intento || (esShiny ? 'shiny' : 'normal');
            if (intento === 'shiny') {
                img.dataset.intento = 'normal';
                img.src = '/images/iconos_webp/' + pokemonId + '.webp';
                return;
            }
            if (intento === 'normal') {
                img.dataset.intento = 'cero';
                img.src = '/images/iconos_webp/0.webp';
                return;
            }
            img.style.display = 'none';
        },

        nivelDe(pokemon) {
            // El backend es el dueño del nivel; el front solo presenta el dato.
            return Number(pokemon?.nivel ?? 1);
        },

        formatExp(exp) {
            return Number(exp || 0).toLocaleString('es-ES');
        },

        porcentajeExpRequisito(req) {
            if (!req) return 0;
            const actual = Number(req.actual || 0);
            const necesario = Number(req.necesario || 0);
            if (!(necesario > 0)) return 0;
            return Math.min(100, Math.round((actual / necesario) * 100));
        },

        statsDe(pokemon) {
            if (!pokemon) return [];
            if (Array.isArray(pokemon.stats) && pokemon.stats.length > 0) return pokemon.stats;
            if (pokemon.pokemon && Array.isArray(pokemon.pokemon.stats) && pokemon.pokemon.stats.length > 0) return pokemon.pokemon.stats;
            return [];
        },

        // ═══════════════════════════════════════════════════════════════════════
        // DETAIL: chart hexagonal
        // ═══════════════════════════════════════════════════════════════════════

        hexStats(pokemon) {
            const stats = this.statsDe(pokemon);
            return [
                { label: 'PS', value: Number(stats[0]?.value ?? 0) },
                { label: 'ATK', value: Number(stats[1]?.value ?? 0) },
                { label: 'DEF', value: Number(stats[2]?.value ?? 0) },
                { label: 'VEL', value: Number(stats[5]?.value ?? 0) },
                { label: 'SpD', value: Number(stats[4]?.value ?? 0) },
                { label: 'SpA', value: Number(stats[3]?.value ?? 0) },
            ];
        },

        /** Orden de las barras horizontales: SpD y Velocidad al final. */
        hexStatsBarras(pokemon) {
            const stats = this.statsDe(pokemon);
            return [
                { label: 'PS', value: Number(stats[0]?.value ?? 0) },
                { label: 'ATK', value: Number(stats[1]?.value ?? 0) },
                { label: 'DEF', value: Number(stats[2]?.value ?? 0) },
                { label: 'SpA', value: Number(stats[3]?.value ?? 0) },
                { label: 'SpD', value: Number(stats[4]?.value ?? 0) },
                { label: 'VEL', value: Number(stats[5]?.value ?? 0) },
            ];
        },

        /** Stats finales y su desglose base/IV/EV, calculados por el backend. */
        statsCalculadosConAporte(pokemon) {
            const stats = pokemon?.stats_calculados || {};
            const claves = ['hp', 'atk', 'def', 'spAtk', 'spDef', 'speed'];
            const resultado = {};

            claves.forEach(clave => {
                const stat = stats[clave] || {};
                resultado[clave] = {
                    base: Number(stat.base ?? 0),
                    iv: Number(stat.iv ?? 0),
                    ev: Number(stat.ev ?? 0),
                    total: Number(stat.total ?? 0),
                };
            });

            return resultado;
        },

        hexStatsCalculados(pokemon) {
            const stats = this.statsCalculadosConAporte(pokemon);
            return [
                { label: 'PS', value: stats.hp.total },
                { label: 'ATK', value: stats.atk.total },
                { label: 'DEF', value: stats.def.total },
                { label: 'VEL', value: stats.speed.total },
                { label: 'SpD', value: stats.spDef.total },
                { label: 'SpA', value: stats.spAtk.total },
            ];
        },

        hexStatsCalculadosConAporte(pokemon) {
            const stats = this.statsCalculadosConAporte(pokemon);
            return [
                { label: 'PS', ...stats.hp },
                { label: 'ATK', ...stats.atk },
                { label: 'DEF', ...stats.def },
                { label: 'SpA', ...stats.spAtk },
                { label: 'SpD', ...stats.spDef },
                { label: 'VEL', ...stats.speed },
            ];
        },

        hexIvs(pokemon) {
            return [
                { label: 'PS', value: this.ivValue(pokemon, 'hp') },
                { label: 'ATK', value: this.ivValue(pokemon, 'atk') },
                { label: 'DEF', value: this.ivValue(pokemon, 'def') },
                { label: 'VEL', value: this.ivValue(pokemon, 'speed') },
                { label: 'SpD', value: this.ivValue(pokemon, 'spDef') },
                { label: 'SpA', value: this.ivValue(pokemon, 'spAtk') },
            ];
        },

        /**
         * Calidad global por media de los seis IV. El estado perfecto exige 15 en todos.
         * Devuelve la clave (clase CSS), el color resuelto y su rgb para Chart.js.
         * Los colores se leen de tokens porque los gráficos no aceptan `var()`.
         * `normal` en oscuro usa `--color-ink`: `--quality-color-normal` no cambia
         * con el tema y así se conserva el gris claro que ya usaba la vista.
         */
        calidadIvs(pokemon) {
            const valores = this.evStats.map(stat => Number(this.ivValue(pokemon, stat.key)) || 0);
            const suma = valores.reduce((total, valor) => total + valor, 0);
            const media = valores.length > 0 ? suma / valores.length : 0;
            const perfecto = valores.length === 6 && valores.every(valor => valor === 15);

            let clave = 'normal';
            if (perfecto) {
                clave = 'perfect';
            } else if (media >= 13) {
                clave = 'excellent';
            } else if (media >= 9) {
                clave = 'great';
            } else if (media >= 5) {
                clave = 'good';
            }

            const oscuro = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
            const token = clave === 'normal' && oscuro ? '--color-ink' : '--quality-color-' + clave;
            const color = this.tokenCss(token) || this.tokenCss('--quality-color-normal');
            const rgb = this.colorARgb(color);

            return { clave, color, rgb, media, valores, perfecto };
        },

        calidadClase(pokemon) {
            return 'pokemon-quality--' + this.calidadIvs(pokemon).clave;
        },

        ivCalidadClave(valor) {
            const iv = Number(valor) || 0;
            if (iv >= 15) return 'perfect';
            if (iv >= 13) return 'excellent';
            if (iv >= 9) return 'great';
            if (iv >= 5) return 'good';
            return 'normal';
        },

        ivCalidadClase(valor) {
            return 'pokemon-quality--' + this.ivCalidadClave(valor);
        },

        destroyDetailCharts() {
            (this.detailChartInstances || []).forEach(chart => {
                try {
                    chart.destroy();
                } catch (error) {
                    console.warn('No se pudo destruir un gráfico del detalle:', error);
                }
            });
            this.detailChartInstances = [];
        },

        crearRadarChart(canvasId, etiquetas, valores, maximo, color, rgb) {
            const canvas = document.getElementById(canvasId);
            if (!canvas || typeof window.Chart === 'undefined') return null;

            const labelColor = this.tokenCss('--color-ink-muted');
            const lineRgb = this.colorARgb(this.tokenCss('--color-line'));
            const gridColor = lineRgb ? 'rgba(' + lineRgb + ', 0.35)' : this.tokenCss('--color-line');

            return new window.Chart(canvas.getContext('2d'), {
                type: 'radar',
                data: {
                    labels: etiquetas,
                    datasets: [{
                        data: valores,
                        borderColor: color,
                        backgroundColor: 'rgba(' + rgb + ', 0.18)',
                        pointBackgroundColor: color,
                        pointBorderColor: color,
                        pointRadius: 3,
                        borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    scales: {
                        r: {
                            min: 0,
                            max: Math.max(1, maximo),
                            ticks: { display: false },
                            grid: { color: gridColor },
                            angleLines: { color: gridColor },
                            pointLabels: {
                                color: labelColor,
                                font: { size: 11, weight: '600' },
                            },
                        },
                    },
                    plugins: {
                        legend: { display: false },
                    },
                },
            });
        },

        renderDetailCharts() {
            if (!this.detailPokemon || typeof window.Chart === 'undefined') return;

            this.destroyDetailCharts();

            const base = this.hexStats(this.detailPokemon);
            const calculados = this.hexStatsCalculados(this.detailPokemon);
            const ivs = this.hexIvs(this.detailPokemon);
            const calidad = this.calidadIvs(this.detailPokemon);

            const charts = [
                this.crearRadarChart(
                    'detail-stats-chart',
                    base.map(stat => stat.label),
                    base.map(stat => stat.value),
                    this.escalaStats(this.detailPokemon),
                    this.tokenCss('--color-primary'),
                    this.colorARgb(this.tokenCss('--color-primary'))
                ),
                this.crearRadarChart(
                    'detail-calculated-chart',
                    calculados.map(stat => stat.label),
                    calculados.map(stat => stat.value),
                    this.escalaCalculada(this.detailPokemon),
                    this.tokenCss('--color-success'),
                    this.colorARgb(this.tokenCss('--color-success'))
                ),
                this.crearRadarChart(
                    'detail-iv-chart',
                    ivs.map(stat => stat.label),
                    ivs.map(stat => stat.value),
                    15,
                    calidad.color,
                    calidad.rgb
                ),
            ].filter(chart => chart !== null);

            this.detailChartInstances = charts;
        },

        escalaStats(pokemon) {
            const valores = this.hexStats(pokemon).map(stat => Number(stat.value) || 0);
            const maxStat = Math.max(1, ...valores);
            return Math.min(255, Math.max(50, Math.ceil((maxStat * 1.15) / 10) * 10));
        },

        escalaCalculada(pokemon) {
            const valores = this.hexStatsCalculados(pokemon).map(stat => Number(stat.value) || 0);
            const maxStat = Math.max(1, ...valores);
            return Math.max(10, Math.ceil((maxStat * 1.15) / 10) * 10);
        },

        get movimientosFiltrados() {
            const movimientos = this.detailPokemon?.movimientos_disponibles || [];
            const tipos = this.filtrosMovimientoTipo;
            const categorias = this.filtrosMovimientoCategoria;
            const nombre = this.normalizarTexto(this.filtroNombreMovimiento).trim();
            if (tipos.length === 0 && categorias.length === 0 && !nombre) {
                return movimientos;
            }
            return movimientos.filter(mov => {
                const tipoOk = tipos.length === 0 || tipos.includes(this.tipoSlugDeMovimiento(mov));
                const categoriaOk = categorias.length === 0 || categorias.includes(this.categoriaSlug(mov));
                const nombreOk = !nombre || this.normalizarTexto(mov.name).includes(nombre);
                return tipoOk && categoriaOk && nombreOk;
            });
        },

        get movimientosNivelFiltrados() {
            return this.movimientosFiltrados
                .filter(mov => mov.origen === 'nivel')
                .sort((a, b) => (Number(a.nivel_requerido) - Number(b.nivel_requerido)) || String(a.name).localeCompare(String(b.name)));
        },

        get movimientosSendaFiltrados() {
            return this.movimientosFiltrados.filter(mov => mov.origen === 'senda');
        },

        get tiposMovimientoDisponibles() {
            const vistos = new Set();
            const tipos = [];
            for (const mov of this.detailPokemon?.movimientos_disponibles || []) {
                const slug = this.tipoSlugDeMovimiento(mov);
                if (!vistos.has(slug)) {
                    vistos.add(slug);
                    tipos.push({ slug, nombre: this.tipoDeMovimiento(mov) || slug });
                }
            }
            return tipos;
        },

        get categoriasMovimientoDisponibles() {
            const presentes = new Set(
                (this.detailPokemon?.movimientos_disponibles || []).map(mov => this.categoriaSlug(mov))
            );
            return ['fisico', 'especial', 'estado'].filter(categoria => presentes.has(categoria));
        },

        toggleFiltroTipoMovimiento(slug) {
            this.filtrosMovimientoTipo = this.filtrosMovimientoTipo.includes(slug)
                ? this.filtrosMovimientoTipo.filter(tipo => tipo !== slug)
                : [...this.filtrosMovimientoTipo, slug];
        },

        toggleFiltroCategoriaMovimiento(categoria) {
            this.filtrosMovimientoCategoria = this.filtrosMovimientoCategoria.includes(categoria)
                ? this.filtrosMovimientoCategoria.filter(cat => cat !== categoria)
                : [...this.filtrosMovimientoCategoria, categoria];
        },

        filtroTipoMovimientoActivo(slug) {
            return this.filtrosMovimientoTipo.includes(slug);
        },

        filtroCategoriaMovimientoActiva(categoria) {
            return this.filtrosMovimientoCategoria.includes(categoria);
        },

        hayFiltrosMovimiento() {
            return this.filtrosMovimientoTipo.length > 0
                || this.filtrosMovimientoCategoria.length > 0
                || String(this.filtroNombreMovimiento || '').trim() !== '';
        },

        limpiarFiltrosMovimiento() {
            this.filtrosMovimientoTipo = [];
            this.filtrosMovimientoCategoria = [];
            this.filtroNombreMovimiento = '';
        },

        /** Habilidad activa del reclutado para el resumen en hover de la tarjeta. */
        habilidadActivaDe(pokemon) {
            const id = pokemon?.habilidad_activa_id;
            if (id === null || id === undefined || id === '') return null;
            return (pokemon?.habilidades_disponibles || []).find(hab => String(hab.id) === String(id)) || null;
        },

        movimientoDeReclutado(pokemon, slug) {
            return (pokemon?.movimientos_disponibles || []).find(mov => mov.slug === slug)
                || (pokemon?.movimientos_seleccionados_detalle || []).find(mov => mov.slug === slug)
                || null;
        },

        /** Movimientos del loadout con los datos del catálogo cargado en /equipos. */
        movimientosAsignadosDe(pokemon) {
            const slugs = Array.isArray(pokemon?.movimientos_seleccionados) ? pokemon.movimientos_seleccionados : [];
            return slugs.map(slug => this.movimientoDeReclutado(pokemon, slug)).filter(Boolean);
        },

        /** Devuelve el movimiento del catálogo a partir de su slug (para chips equipados). */
        movimientoLoadout(slug) {
            return (this.detailPokemon?.movimientos_disponibles || []).find(mov => mov.slug === slug)
                || (this.detailPokemon?.movimientos_seleccionados_detalle || []).find(mov => mov.slug === slug)
                || null;
        },

        nombreMovimientoLoadout(slug) {
            const mov = this.movimientoLoadout(slug);
            return mov ? mov.name : slug;
        },

        nombreHabilidadLoadout() {
            if (!this.loadoutHabilidadId) return 'Automática (todas las de la especie)';
            const hab = (this.detailPokemon?.habilidades_disponibles || []).find(h => String(h.id) === String(this.loadoutHabilidadId));
            return hab ? hab.name : 'Automática';
        },

        baseExperienceDe(pokemon) {
            if (!pokemon) return null;
            if (typeof pokemon.base_experience === 'number') return pokemon.base_experience;
            if (pokemon.pokemon && typeof pokemon.pokemon.base_experience === 'number') return pokemon.pokemon.base_experience;
            return null;
        },

        tipoLabelDel(t) {
            if (!t) return 'Tipo';
            if (t.tipo_nombre) return t.tipo_nombre;
            if (typeof t.type === 'number') {
                return tipoBadge(t.type).label;
            }
            return t.type || 'Tipo';
        },

        /** Slug del asset (/images/type/{slug}.webp) a partir del tipo serializado. */
        tipoSlugDel(t) {
            if (t?.tipo_slug) return t.tipo_slug;
            return this.normalizarTexto(this.tipoLabelDel(t)).replace(/\s+/g, '');
        },

        tipoIconoDeTipo(t) {
            return '/images/type/' + this.tipoSlugDel(t) + '.webp';
        },

        tipoDeMovimiento(mov) {
            if (!mov) return null;
            if (mov.tipo_nombre) return mov.tipo_nombre;
            if (mov.type_id !== null && mov.type_id !== undefined && mov.type_id !== '') {
                return tipoBadge(Number(mov.type_id)).label;
            }
            if (mov.tipo_slug) {
                return tipoBadge(mov.tipo_slug).label;
            }
            return null;
        },

        tipoSlugDeMovimiento(mov) {
            if (mov?.tipo_slug) return mov.tipo_slug;
            return String(this.tipoDeMovimiento(mov) || 'Normal')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
        },

        tipoIconoUrl(mov) {
            return '/images/type/' + this.tipoSlugDeMovimiento(mov) + '.webp';
        },

        categoriaSlug(mov) {
            const categoria = String(mov?.category || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
            return ['fisico', 'especial', 'estado'].includes(categoria) ? categoria : 'estado';
        },

        categoriaIconoUrl(mov) {
            return this.categoriaIconoDe(this.categoriaSlug(mov));
        },

        categoriaIconoDe(categoria) {
            return '/images/moves/' + categoria + '.png';
        },

        etiquetaCategoria(mov) {
            return this.etiquetaCategoriaSlug(this.categoriaSlug(mov));
        },

        etiquetaCategoriaSlug(categoria) {
            return { fisico: 'Físico', especial: 'Especial', estado: 'Estado' }[categoria] ?? categoria;
        },

        claseCategoria(categoria) {
            return {
                fisico: 'bg-primary-soft text-primary-ink',
                especial: 'bg-info-soft text-info-ink',
                estado: 'bg-warning-soft text-warning-ink',
            }[categoria] ?? 'bg-surface-sunken text-ink-muted';
        },

        riesgoClass(riesgo) {
            const clases = {
                'Bajo': 'bg-success-soft text-success-ink',
                'Medio': 'bg-warning-soft text-warning-ink',
                'Alto': 'bg-warning-soft text-warning-ink',
                'Extremo': 'bg-danger-soft text-danger-ink',
            };
            return clases[riesgo] || 'bg-surface-sunken text-ink-muted';
        },

        // ═══════════════════════════════════════════════════════════════════════
        // DETAIL MODAL (shared)
        // ═══════════════════════════════════════════════════════════════════════

        /** Sustituye el reclutado actualizado en la cuadrícula y en los equipos. */
        _actualizarReclutadoEnMemoria(actualizado) {
            const id = actualizado?.id;
            if (id === undefined || id === null) return;

            this.reclutados = this.reclutados.map(reclutado =>
                String(reclutado.id) === String(id) ? { ...reclutado, ...actualizado } : reclutado
            );

            this.teams.forEach(team => {
                (team.members || []).forEach(member => {
                    if (member.reclutado && String(member.reclutado.id) === String(id)) {
                        member.reclutado = { ...member.reclutado, ...actualizado };
                    }
                });
            });
        },

        openMemberDetail(team, slot) {
            const reclutado = this.reclutadoDeSlot(team, slot);
            if (!reclutado) return;
            this.openDetail(reclutado);
        },

        openDetail(pokemon) {
            this.detailPokemon = pokemon;
            this.detailLoading = false;
            this.detailMovimientosLoading = false;
            this.detailMovimientosError = '';
            this.showDetailModal = true;
            this.loadoutHabilidadId = pokemon?.habilidad_activa_id ?? '';
            this.loadoutMovimientos = Array.isArray(pokemon?.movimientos_seleccionados)
                ? [...pokemon.movimientos_seleccionados]
                : [];
            this.filtrosMovimientoTipo = [];
            this.filtrosMovimientoCategoria = [];
            this.filtroNombreMovimiento = '';
            this.loadoutFeedback = { tipo: '', mensaje: '' };
            this.cargarEvoluciones(pokemon);
            this.cargarMovimientosDetalle(pokemon);
            setTimeout(() => this.renderDetailCharts(), 50);
        },

        closeDetail() {
            this.showDetailModal = false;
            this.detailPokemon = null;
            this.detailLoading = false;
            this.detailEvoluciones = [];
            this.detailEvolucionesLoading = false;
            this.detailEvolucionesError = false;
            this.selectedEvolucionId = null;
            this.detailMovimientosLoading = false;
            this.detailMovimientosError = '';
            this.detailEvFeedback = { tipo: '', mensaje: '' };
            this.detailEvsFeeding = false;
            this.detailCandyFeeding = false;
            this.loadoutHabilidadId = '';
            this.loadoutMovimientos = [];
            this.filtrosMovimientoTipo = [];
            this.filtrosMovimientoCategoria = [];
            this.filtroNombreMovimiento = '';
            this.loadoutFeedback = { tipo: '', mensaje: '' };
            this.destroyDetailCharts();
        },

        toggleLoadoutMovimiento(slug) {
            const movimiento = (this.detailPokemon?.movimientos_disponibles || [])
                .find(m => m.slug === slug);
            if (movimiento?.desbloqueado === false) {
                this.loadoutFeedback = {
                    tipo: 'error',
                    mensaje: movimiento.origen === 'senda'
                        ? 'Este movimiento debe desbloquearse en Sendas.'
                        : 'Este movimiento requiere nivel ' + movimiento.nivel_requerido + ' o desbloquearse en Sendas.',
                };
                return;
            }

            if (this.loadoutMovimientos.includes(slug)) {
                this.loadoutMovimientos = this.loadoutMovimientos.filter(s => s !== slug);
                return;
            }
            if (this.loadoutMovimientos.length >= 6) {
                this.loadoutFeedback = { tipo: 'error', mensaje: 'Máximo 6 movimientos.' };
                return;
            }
            this.loadoutMovimientos.push(slug);
        },

        async guardarLoadout() {
            if (!this.detailPokemon || this.loadoutSaving) return;
            this.loadoutSaving = true;
            this.loadoutFeedback = { tipo: '', mensaje: '' };
            try {
                const response = await fetch('/reclutado/' + this.detailPokemon.id + '/loadout', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        habilidad_id: this.loadoutHabilidadId || null,
                        movimientos: this.loadoutMovimientos,
                    }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.loadoutFeedback = {
                        tipo: 'error',
                        mensaje: data.error || data.message || 'No se pudo guardar el loadout.',
                    };
                    return;
                }
                if (data.reclutado) {
                    const idx = this.reclutados.findIndex(r => String(r.id) === String(data.reclutado.id));
                    if (idx >= 0) {
                        this.reclutados[idx] = { ...this.reclutados[idx], ...data.reclutado };
                        this.detailPokemon = this.reclutados[idx];
                    } else {
                        this.detailPokemon = data.reclutado;
                    }
                    this.loadoutHabilidadId = data.reclutado.habilidad_activa_id ?? '';
                    this.loadoutMovimientos = Array.isArray(data.reclutado.movimientos_seleccionados)
                        ? [...data.reclutado.movimientos_seleccionados]
                        : [];
                }
                this.loadoutFeedback = { tipo: 'ok', mensaje: '✓ Loadout guardado' };
            } catch (err) {
                console.error('Error saving loadout:', err);
                this.loadoutFeedback = { tipo: 'error', mensaje: 'Error de conexión al guardar el loadout.' };
            } finally {
                this.loadoutSaving = false;
            }
        },

        async cargarEvoluciones(pokemon) {
            if (!pokemon) return;
            this.detailEvoluciones = [];
            this.detailEvolucionesError = false;
            this.selectedEvolucionId = null;
            this.detailEvolucionesLoading = true;
            try {
                const response = await fetch('/reclutado/' + pokemon.id + '/evoluciones', {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });
                if (response.ok) {
                    const data = await response.json();
                    this.detailEvoluciones = data.opciones || [];
                    if (this.detailEvoluciones.length === 1) {
                        this.selectedEvolucionId = this.detailEvoluciones[0].pokemon_id;
                    }
                } else {
                    this.detailEvolucionesError = true;
                }
            } catch (err) {
                console.error('Error cargando evoluciones:', err);
                this.detailEvolucionesError = true;
            } finally {
                this.detailEvolucionesLoading = false;
            }
        },

        /**
         * El listado de /equipos ya no incluye el catálogo completo de cada
         * especie. Al abrir el detalle se pide bajo demanda y se cachea en
         * memoria para el resto de la sesión de la página.
         */
        async cargarMovimientosDetalle(pokemon) {
            const id = pokemon?.id;
            if (id === undefined || id === null) return;

            const clave = String(id);
            if (this.movimientosCargadosIds.includes(clave)) return;
            if (Array.isArray(pokemon.movimientos_disponibles) && pokemon.movimientos_disponibles.length > 0) {
                this.movimientosCargadosIds.push(clave);
                return;
            }
            if (this.movimientosCargandoIds.includes(clave)) return;

            this.movimientosCargandoIds.push(clave);
            if (String(this.detailPokemon?.id) === clave) {
                this.detailMovimientosLoading = true;
                this.detailMovimientosError = '';
            }

            try {
                const response = await fetch('/reclutado/' + id + '/movimientos', {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(data.message || 'No se pudieron cargar los movimientos.');
                }

                const movimientos = Array.isArray(data.movimientos_disponibles) ? data.movimientos_disponibles : [];
                this.movimientosCargadosIds.push(clave);
                this._actualizarReclutadoEnMemoria({ ...pokemon, movimientos_disponibles: movimientos });

                if (String(this.detailPokemon?.id) === clave) {
                    this.detailPokemon = { ...this.detailPokemon, movimientos_disponibles: movimientos };
                }
            } catch (err) {
                console.error('Error cargando movimientos:', err);
                if (String(this.detailPokemon?.id) === clave) {
                    this.detailMovimientosError = 'No se pudieron cargar los movimientos.';
                }
            } finally {
                this.movimientosCargandoIds = this.movimientosCargandoIds.filter(cargando => cargando !== clave);
                if (String(this.detailPokemon?.id) === clave) {
                    this.detailMovimientosLoading = false;
                }
            }
        },

        opcionSeleccionada() {
            if (!this.selectedEvolucionId || this.detailEvoluciones.length === 0) return null;
            return this.detailEvoluciones.find(o => o.pokemon_id === this.selectedEvolucionId) || null;
        },

        // ═══════════════════════════════════════════════════════════════════════
        // DETAIL: IVs / EVs / HABILIDADES
        // ═══════════════════════════════════════════════════════════════════════

        /** Clave corta del backend (naming candy_ev: atksp/defsp/spd) para tolerar alias. */
        slugCorto(key) {
            const mapa = { hp: 'hp', atk: 'atk', def: 'def', spAtk: 'atksp', spDef: 'defsp', speed: 'spd' };
            return mapa[key] || key;
        },

        habilidadesDe(pokemon) {
            if (!pokemon) return [];
            const fuente = pokemon.habilidades || pokemon.abilities || [];
            const lista = Array.isArray(fuente) ? fuente : Object.values(fuente || {});
            return lista.map(h => {
                if (typeof h === 'string') return h;
                if (h && (h.nombre || h.name)) return h.nombre || h.name;
                return Object.values(h || {})[0] || '';
            }).filter(Boolean);
        },

        movimientosDe(pokemon) {
            if (!pokemon) return [];
            const fuente = pokemon.movimientos_pokemon || pokemon.movimientos || pokemon.moves || [];
            const lista = Array.isArray(fuente) ? fuente : Object.values(fuente || []);
            return lista.map(m => {
                if (typeof m === 'string') return m;
                if (m && (m.nombre || m.name)) return m.nombre || m.name;
                return '';
            }).filter(Boolean);
        },

        ivsDe(pokemon) {
            return pokemon?.ivs || pokemon?.iv || {};
        },

        ivValue(pokemon, key) {
            const ivs = this.ivsDe(pokemon);
            return ivs[key] ?? ivs[this.slugCorto(key)] ?? 0;
        },

        evsDe(pokemon) {
            return pokemon?.evs || pokemon?.ev || {};
        },

        evValue(pokemon, key) {
            const evs = this.evsDe(pokemon);
            return evs[key] ?? evs[this.slugCorto(key)] ?? 0;
        },

        evPct(value) {
            return Math.max(0, Math.min(100, (Number(value || 0) / 255) * 100));
        },

        evCaramelosDe(pokemon, key) {
            const inventario = (this.inventarioCaramelos?.ev || []).find(item => item.key === key);
            if (inventario) return inventario.cantidad;

            const mapa = pokemon?.caramelos_ev;
            if (mapa && typeof mapa === 'object') {
                return Number(mapa[key] ?? mapa[this.slugCorto(key)] ?? 0);
            }

            return 0;
        },

        actualizarInventarioEv(key, cantidad) {
            const item = (this.inventarioCaramelos?.ev || []).find(caramelo => caramelo.key === key);
            if (item) item.cantidad = Math.max(0, Number(cantidad) || 0);
        },

        puedeDarEv(pokemon, key) {
            if (!pokemon || this._pokemonBloqueado(pokemon)) return false;
            if (this.detailEvsFeeding) return false;
            if (this.evValue(pokemon, key) >= 255) return false;
            if (this.evCaramelosDe(pokemon, key) <= 0) return false;
            return true;
        },

        async darEv(key, cantidad = 1) {
            const pokemon = this.detailPokemon;
            if (!pokemon || !this.puedeDarEv(pokemon, key)) return;
            const cantidadEnvio = Math.max(1, Number(cantidad) || 1);
            const valorAnterior = this.evValue(pokemon, key);
            this.detailEvsFeeding = true;
            this.detailEvFeedback = { tipo: '', mensaje: '' };
            try {
                const response = await fetch('/reclutado/' + pokemon.id + '/dar-evs', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ stat: key, cantidad: cantidadEnvio }),
                });
                if (response.ok) {
                    const data = await response.json();
                    const nivelado = data.evs ?? data.ev ?? (data.actual !== undefined ? { [key]: data.actual } : null);
                    if (nivelado && typeof nivelado === 'object') {
                        const evs = { ...this.evsDe(pokemon), ...nivelado };
                        pokemon.evs = evs;
                    }
                    if (data.stats_calculados && typeof data.stats_calculados === 'object') {
                        pokemon.stats_calculados = data.stats_calculados;
                    }
                    if (data.caramelos_ev && typeof data.caramelos_ev === 'object') {
                        pokemon.caramelos_ev = data.caramelos_ev;
                    }
                    const restantes = Number(data.caramelos_disponibles);
                    if (Number.isFinite(restantes)) {
                        pokemon.caramelos_ev = { ...(pokemon.caramelos_ev || {}), [key]: restantes };
                        this.actualizarInventarioEv(key, restantes);
                    }
                    const label = (this.evStats.find(s => s.key === key) || {}).label || key;
                    const ganados = Math.max(0, Number(data.actual ?? this.evValue(pokemon, key)) - valorAnterior);
                    this.detailEvFeedback = { tipo: 'success', mensaje: '✓ ¡+' + ganados + ' EV para ' + label + '!' };
                    setTimeout(() => this.renderDetailCharts(), 0);
                } else {
                    let message = 'No se pudo dar el caramelo EV.';
                    try {
                        const data = await response.json();
                        message = data.error || data.message || message;
                    } catch (err) {}
                    this.detailEvFeedback = { tipo: 'error', mensaje: '⚠️ ' + message };
                }
            } catch (err) {
                console.error('Error dando caramelo EV:', err);
                this.detailEvFeedback = { tipo: 'error', mensaje: '⚠️ Error de conexión al dar el caramelo EV.' };
            } finally {
                this.detailEvsFeeding = false;
            }
        },

        // ═══════════════════════════════════════════════════════════════════════
        // HUEVOS TAB
        // ═══════════════════════════════════════════════════════════════════════

        cambiarSeccion(seccion) {
            if (this.activeSeccion === seccion) return;
            this.activeSeccion = seccion;
            if (seccion === 'huevos') {
                this.huevosFeedback = { tipo: '', mensaje: '' };
                this.cargarHuevos();
            }
        },

        async cargarHuevos(force = false) {
            if (this.huevosLoading) return;
            if (!force && this.huevosLoaded) return;
            this.huevosLoading = true;
            this.huevosError = '';
            try {
                const response = await fetch('/api/huevos', {
                    headers: { 'Accept': 'application/json' },
                });
                if (response.status === 404) {
                    // Módulo backend aún no publicado: avisar sin romper la página.
                    this.huevosError = 'El módulo de huevos está en preparación.';
                    this.huevos = [];
                    this.huevosDisponibles = [];
                    this.huevosLimites = null;
                    this.huevosLoaded = false;
                    return;
                }
                if (!response.ok) throw new Error('No se pudieron cargar los huevos');
                const data = await response.json();
                this.huevos = Array.isArray(data.huevos) ? data.huevos : [];
                this.huevosDisponibles = Array.isArray(data.disponibles) ? data.disponibles : [];
                this.huevosLimites = data.limites || null;
                this.huevosLoaded = true;
            } catch (e) {
                console.error('Error cargando huevos:', e);
                this.huevosError = 'No se pudieron cargar los huevos.';
                this.huevosLoaded = false;
            } finally {
                this.huevosLoading = false;
            }
        },

        gestacionActiva() {
            return this.huevos.some(h => h.estado === 'gestando');
        },

        incubacionActiva() {
            return this.huevos.some(h => h.estado === 'incubando' || h.estado === 'listo');
        },

        limiteExcedido() {
            return this.gestacionActiva() || this.incubacionActiva();
        },

        limiteMensaje() {
            const gestando = this.gestacionActiva();
            const incubando = this.incubacionActiva();
            if (gestando && incubando) return 'Ya tienes 1 huevo gestando y 1 en incubación: completa uno antes de poner otro.';
            if (gestando) return 'Ya tienes 1 huevo gestando: espera a que termine antes de poner otro.';
            if (incubando) return 'Ya tienes 1 huevo incubando: espera a que eclosione antes de poner otro.';
            return '';
        },

        puedePonerHuevo() {
            return !this.huevosActuando && !this.limiteExcedido();
        },

        async ponerHuevo(evolutionChainId) {
            if (!evolutionChainId || !this.puedePonerHuevo()) return;
            this.huevosActuando = true;
            this.huevosFeedback = { tipo: '', mensaje: '' };
            try {
                const response = await fetch('/api/huevos', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ evolution_chain_id: evolutionChainId }),
                });
                if (response.ok) {
                    const data = await response.json();
                    this.huevosFeedback = { tipo: 'success', mensaje: data.message || '🥚 El huevo ha empezado a gestarse.' };
                    await this.cargarHuevos(true);
                } else {
                    let message = 'No se pudo poner el huevo.';
                    try {
                        const data = await response.json();
                        message = data.error || data.message || message;
                    } catch (err) {}
                    this.huevosFeedback = { tipo: 'error', mensaje: '⚠️ ' + message };
                }
            } catch (err) {
                console.error('Error poniendo huevo:', err);
                this.huevosFeedback = { tipo: 'error', mensaje: '⚠️ Error de conexión al poner el huevo.' };
            } finally {
                this.huevosActuando = false;
            }
        },

        async eclosionarHuevo(huevo) {
            if (!huevo || !huevo.puede_eclosionar || this.huevosActuando) return;
            this.huevosActuando = true;
            this.huevosAccionId = huevo.id;
            this.huevosFeedback = { tipo: '', mensaje: '' };
            try {
                const response = await fetch('/api/huevos/' + huevo.id + '/eclosionar', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });
                if (response.ok) {
                    const data = await response.json();
                    const nacido = data.pokemon?.name || data.nombre || data.pokemon_name || '';
                    this.huevosFeedback = { tipo: 'success', mensaje: '🎉 ¡Eclosionó' + (nacido ? ' un ' + nacido : '') + '!' };
                    await this.cargarHuevos(true);
                } else {
                    let message = 'No se pudo eclosionar el huevo.';
                    try {
                        const data = await response.json();
                        message = data.error || data.message || message;
                    } catch (err) {}
                    this.huevosFeedback = { tipo: 'error', mensaje: '⚠️ ' + message };
                }
            } catch (err) {
                console.error('Error eclosionando huevo:', err);
                this.huevosFeedback = { tipo: 'error', mensaje: '⚠️ Error de conexión al eclosionar el huevo.' };
            } finally {
                this.huevosActuando = false;
                this.huevosAccionId = null;
            }
        },

        huevoProgreso(huevo) {
            // Contrato backend: progreso 0-100 ya calculado.
            return Math.max(0, Math.min(100, Number(huevo?.progreso ?? 0)));
        },

        huevoEtiquetaRestante(huevo) {
            if (huevo?.puede_eclosionar) return '¡Listo!';
            const horas = Number(huevo?.tiempo_restante_horas ?? huevo?.tiempo_restante ?? 0);
            return this.formatearDuracion(horas);
        },

        formatearDuracion(horas) {
            const h = Number(horas || 0);
            if (h <= 0) return 'Listo';
            if (h >= 24) {
                const d = Math.floor(h / 24);
                const rest = Math.round(h % 24);
                return d + 'd' + (rest > 0 ? ' ' + rest + 'h' : '');
            }
            const hh = Math.floor(h);
            const mm = Math.round((h - hh) * 60);
            return (hh > 0 ? hh + 'h ' : '') + mm + 'm';
        },

        estadoHuevo(huevo) {
            const mapa = {
                gestando: { texto: 'Gestando', clase: 'bg-warning-soft text-warning-ink' },
                incubando: { texto: 'Incubando', clase: 'bg-primary-soft text-primary-ink' },
                listo: { texto: '¡Listo!', clase: 'bg-success-soft text-success-ink' },
            };
            return mapa[huevo?.estado] || { texto: huevo?.estado || 'Huevo', clase: 'bg-surface-sunken text-ink-muted' };
        },

        // ═══════════════════════════════════════════════════════════════════════
        // EQUIPOS LOGIC
        // ═══════════════════════════════════════════════════════════════════════

        getMember(team, slot) {
            const member = team.members.find(m => m.slot === slot);
            return member ? member.reclutado : null;
        },

        /** Reclutado enriquecido del serializador principal para un slot concreto. */
        reclutadoDeSlot(team, slot) {
            const member = team?.members?.find(m => String(m.slot) === String(slot));
            if (!member) return null;
            return this.reclutados.find(r => String(r.id) === String(member.pokemon_id)) || member.reclutado;
        },

        getTeamName(teamId) {
            const team = this.teams.find(t => t.id === teamId);
            return team ? team.name : '';
        },

        composicionBadge(team) {
            if (!team.members || team.members.length !== 3) return null;
            const nombre = team.sinergia_nombre;
            if (typeof nombre === 'string' && nombre.trim()) {
                return {
                    texto: 'Composición: ' + nombre,
                    clase: 'bg-success-soft text-success-ink',
                };
            }
            return {
                texto: 'Composición neutra',
                clase: 'bg-surface-sunken text-ink-muted',
            };
        },

        pokemonTeam(pokemon) {
            if (!pokemon) return null;
            return this.teams.find(t => t.members.some(m => String(m.pokemon_id) === String(pokemon.id))) || null;
        },

        pokemonTeamId(pokemon) {
            return this.pokemonTeam(pokemon)?.id ?? null;
        },

        pokemonTeamNombre(pokemon) {
            return this.pokemonTeam(pokemon)?.name ?? '';
        },

        pokemonEnExploracion(pokemon) {
            if (!pokemon) return false;
            return this.enExploracion(pokemon.id);
        },

        // ─── Asignación / reemplazo en equipos completos ────────────────────

        selectTeamForPokemon(pokemon, team) {
            if (!pokemon || !team) return;
            if (team.members.length >= 3) {
                this.abrirReemplazo(pokemon, team);
                return;
            }
            this.addToTeam(pokemon, team);
        },

        abrirReemplazo(pokemon, team) {
            this.replacePokemon = pokemon;
            this.replaceTeam = team;
            this.replaceError = '';
            this.replaceSaving = false;
            this.showReplaceModal = true;
        },

        cerrarReemplazo() {
            this.showReplaceModal = false;
            this.replacePokemon = null;
            this.replaceTeam = null;
            this.replaceSaving = false;
            this.replaceError = '';
        },

        async reemplazarMiembroEquipo(slot) {
            const team = this.replaceTeam;
            const pokemon = this.replacePokemon;
            const member = team ? team.members.find(m => String(m.slot) === String(slot)) : null;
            if (!team || !pokemon || !member || this.replaceSaving) return;

            this.replaceSaving = true;
            this.replaceError = '';

            try {
                const response = await fetch('/teams/add-member', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        team_id: team.id,
                        reclutado_id: pokemon.id,
                        slot: member.slot,
                        behavior: member.behavior || 'VANGUARDIA',
                        replace: true,
                    }),
                });

                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.replaceError = data.error || data.message || 'No se pudo reemplazar el miembro del equipo.';
                    return;
                }

                if (data.member) {
                    team.members = team.members.filter(m => String(m.id) !== String(member.id));
                    team.members.push({
                        id: data.member.id,
                        team_id: data.member.team_id,
                        pokemon_id: data.member.pokemon_id,
                        slot: data.member.slot,
                        behavior: member.behavior || 'VANGUARDIA',
                        reclutado: pokemon,
                    });
                    this.teamPokemonIds = this.teamPokemonIds.filter(id => String(id) !== String(member.pokemon_id));
                    this.teamPokemonIds.push(pokemon.id);
                }

                this.cerrarReemplazo();
            } catch (err) {
                console.error('Error replacing team member:', err);
                this.replaceError = 'Error de conexión al reemplazar el miembro del equipo.';
            } finally {
                this.replaceSaving = false;
            }
        },

        async createTeam() {
            if (!this.newTeamName.trim()) return;
            try {
                const response = await fetch('/teams', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ name: this.newTeamName.trim() }),
                });
                if (response.ok) {
                    const data = await response.json();
                    if (data.team) {
                        this.teams.push({ ...data.team, members: [] });
                        this.showNewTeamForm = false;
                        this.newTeamName = '';
                    }
                } else {
                    await this.handleError(response);
                }
            } catch (err) {
                console.error('Error creating team:', err);
            }
        },

        confirmDeleteTeam(team) {
            this.teamToDelete = team;
            this.showDeleteModal = true;
        },

        async deleteTeam() {
            if (!this.teamToDelete) return;
            try {
                const response = await fetch('/teams/' + this.teamToDelete.id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });
                if (response.ok) {
                    this.teamToDelete.members.forEach(m => {
                        if (m.pokemon_id) {
                            this.teamPokemonIds = this.teamPokemonIds.filter(id => id !== m.pokemon_id);
                        }
                    });
                    this.teams = this.teams.filter(t => t.id !== this.teamToDelete.id);
                    this.showDeleteModal = false;
                    this.teamToDelete = null;
                } else {
                    await this.handleError(response);
                }
            } catch (err) {
                console.error('Error deleting team:', err);
            }
        },

        startRename(team) {
            const newName = prompt('Nuevo nombre del equipo:', team.name);
            if (newName && newName.trim() && newName.trim() !== team.name) {
                fetch('/teams/' + team.id, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ name: newName.trim() }),
                }).then(r => r.json()).then(data => {
                    if (data.team) {
                        team.name = data.team.name;
                    } else if (data.error) {
                        alert(data.error);
                    }
                }).catch(err => console.error('Error renaming:', err));
            }
        },

        async addToTeam(pokemon, team) {
            if (!pokemon || !team) return;

            const emptySlot = [1, 2, 3].find(s => !team.members.some(m => Number(m.slot) === s));
            if (!emptySlot) {
                this.abrirReemplazo(pokemon, team);
                return;
            }
            try {
                const response = await fetch('/teams/add-member', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        team_id: team.id,
                        reclutado_id: pokemon.id,
                        slot: emptySlot,
                        behavior: 'VANGUARDIA',
                    }),
                });
                if (response.ok) {
                    const data = await response.json();
                    if (data.member) {
                        const t = this.teams.find(t => String(t.id) === String(data.member.team_id));
                        if (t) {
                            t.members.push({
                                id: data.member.id,
                                team_id: data.member.team_id,
                                pokemon_id: data.member.pokemon_id,
                                slot: data.member.slot,
                                behavior: data.member.behavior || 'VANGUARDIA',
                                reclutado: pokemon,
                            });
                        }
                        if (!this.teamPokemonIds.some(id => String(id) === String(pokemon.id))) {
                            this.teamPokemonIds.push(pokemon.id);
                        }
                    }
                } else {
                    await this.handleError(response);
                }
            } catch (err) {
                console.error('Error adding member:', err);
            }
        },

        async removeMember(team, pokemon) {
            const member = team.members.find(m => String(m.pokemon_id) === String(pokemon.id));
            if (!member) return;
            try {
                const response = await fetch('/teams/remove-member', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ member_id: member.id }),
                });
                if (response.ok) {
                    team.members = team.members.filter(m => String(m.id) !== String(member.id));
                    this.teamPokemonIds = this.teamPokemonIds.filter(id => String(id) !== String(pokemon.id));
                } else {
                    await this.handleError(response);
                }
            } catch (err) {
                console.error('Error removing member:', err);
            }
        },

        removeFromTeam(pokemon) {
            const team = this.pokemonTeam(pokemon);
            if (team) {
                this.removeMember(team, pokemon);
            }
        },

        // ─── DEPRECATED: funciones de favoritos (UI eliminada; backend conservado) ─
        // toggleFavorito, openFavoritosModal, closeFavoritosModal eliminadas.

        async updateMemberRole(team, member, behavior) {
            // DEPRECATED: reemplazado por radios de formación vanguardia/retaguardia.
            // Se conserva por si código legacy lo llama; puede eliminarse en limpieza futura.
            if (!member || !behavior) return;
            const reclutadoId = member.reclutado?.id ?? member.pokemon_id;
            if (!reclutadoId) return;
            try {
                const response = await fetch('/api/reclutado/' + reclutadoId + '/rol', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ behavior }),
                });
                if (response.ok) {
                    location.reload();
                } else {
                    const body = await response.json().catch(() => ({}));
                    alert(body.message || body.error || 'No se pudo actualizar el rol.');
                }
            } catch (err) {
                console.error('Error updating reclutado role:', err);
                alert('Error de conexión al actualizar el rol.');
            }
        },

        rolInicialDe(member) {
            // DEPRECATED: reemplazado por formacionDe(team, slot).
            // Se conserva por si código legacy lo llama; puede eliminarse en limpieza futura.
            if (!member) return 'VANGUARDIA';
            return member.reclutado?.behavior || member.behavior || 'VANGUARDIA';
        },

        // ─── Formación persistente (combate 3v3) ─────────────────────────────

        /** Posición actual del slot: draft local > formación persistida > 'vanguardia'. */
        formacionDe(team, slot) {
            return this.formacionDraft[team.id]?.[slot]
                || team.formacion?.[slot]
                || 'vanguardia';
        },

        setFormacionSlot(team, slot, posicion) {
            if (!this.formacionDraft[team.id]) this.formacionDraft[team.id] = {};
            this.formacionDraft[team.id][slot] = posicion;
            this.formacionSaveError = '';
            this.formacionSaveSuccess = '';
        },

        async guardarFormacion(team) {
            const base = team.formacion || {};
            const draft = this.formacionDraft[team.id] || {};
            const formacionCompleta = {};
            for (let slot = 1; slot <= 3; slot++) {
                if (!this.getMember(team, slot)) continue;
                formacionCompleta[slot] = draft[slot] || base[slot] || 'vanguardia';
            }
            if (Object.keys(formacionCompleta).length === 0) {
                alert('No hay cambios en la formación para guardar.');
                return;
            }
            this.formacionSavingTeamId = team.id;
            this.formacionSaveError = '';
            this.formacionSaveSuccess = '';
            try {
                const response = await fetch('/teams/' + team.id + '/formacion', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ formacion: formacionCompleta }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.formacionSaveError = data.error || data.message || 'No se pudo guardar la formación.';
                    return;
                }
                if (data.team) {
                    // Contrato: { team: { id, formacion: {slot: pos} } }.
                    this.formacionDraft[team.id] = {};
                    team.formacion = data.team.formacion || {};
                    this.formacionSaveSuccess = '✓ Formación guardada';
                    setTimeout(() => {
                        this.formacionSaveSuccess = '';
                    }, 3000);
                }
            } catch (err) {
                console.error('Error saving formation:', err);
                this.formacionSaveError = 'Error de conexión al guardar la formación.';
            } finally {
                this.formacionSavingTeamId = null;
            }
        },

        // ─── Objetos equipados (combate 3v3) ─────────────────────────────────

        itemDe(team, slot) {
            const draft = this.itemsDraft[team.id]?.[slot];
            if (draft !== undefined) return draft;

            const member = this.getMember(team, slot);
            const items = member?.obj_equipados;
            const item = Array.isArray(items) ? items[0] : '';

            return typeof item === 'string' ? item : '';
        },

        async guardarItemSlot(team, slot, itemKey) {
            if (!this.itemsDraft[team.id]) this.itemsDraft[team.id] = {};
            this.itemsDraft[team.id][slot] = itemKey;
            this.itemSavingTeamId = team.id;
            this.itemSaveError = '';
            this.itemSaveSuccess = '';

            try {
                const response = await fetch('/teams/' + team.id + '/items', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ items: { [slot]: itemKey } }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.itemSaveError = data.error || data.message || 'No se pudo guardar el objeto.';
                    return;
                }

                const member = this.getMember(team, slot);
                if (member) {
                    member.obj_equipados = itemKey !== '' ? [itemKey] : [];
                }
                this.itemsDraft[team.id] = {};
                this.itemSaveSuccess = '✓ Objeto guardado';
                setTimeout(() => {
                    this.itemSaveSuccess = '';
                }, 2500);
            } catch (err) {
                console.error('Error saving item:', err);
                this.itemSaveError = 'Error de conexión al guardar el objeto.';
            } finally {
                this.itemSavingTeamId = null;
            }
        },

        // ─── Posturas (combate 3v3) ──────────────────────────────────────────

        /** Postura del slot: draft local > postura persistida > 'atacar'. */
        posturaDe(team, slot) {
            return this.posturasDraft[team.id]?.[slot]
                || team.posturas?.[slot]
                || 'atacar';
        },

        setPosturaSlot(team, slot, postura) {
            if (!this.posturasDraft[team.id]) this.posturasDraft[team.id] = {};
            this.posturasDraft[team.id][slot] = postura;
            this.posturaSaveError = '';
            this.posturaSaveSuccess = '';
        },

        async guardarPosturas(team) {
            const base = team.posturas || {};
            const draft = this.posturasDraft[team.id] || {};
            const posturasCompletas = {};
            for (let slot = 1; slot <= 3; slot++) {
                if (!this.getMember(team, slot)) continue;
                posturasCompletas[slot] = draft[slot] || base[slot] || 'atacar';
            }

            if (Object.keys(posturasCompletas).length === 0) {
                alert('No hay posturas para guardar.');
                return;
            }

            this.posturaSavingTeamId = team.id;
            this.posturaSaveError = '';
            this.posturaSaveSuccess = '';

            try {
                const response = await fetch('/teams/' + team.id + '/posturas', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ posturas: posturasCompletas }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.posturaSaveError = data.error || data.message || 'No se pudieron guardar las posturas.';
                    return;
                }
                if (data.team) {
                    // Contrato: { team: { id, posturas: {slot: postura} } }.
                    this.posturasDraft[team.id] = {};
                    team.posturas = data.team.posturas || {};
                    this.posturaSaveSuccess = '✓ Posturas guardadas';
                    setTimeout(() => {
                        this.posturaSaveSuccess = '';
                    }, 3000);
                }
            } catch (err) {
                console.error('Error saving postures:', err);
                this.posturaSaveError = 'Error de conexión al guardar las posturas.';
            } finally {
                this.posturaSavingTeamId = null;
            }
        },

        // ═══════════════════════════════════════════════════════════════════════
        // EVOLVE / RELEASE (shared — respects both individual & team exploration)
        // ═══════════════════════════════════════════════════════════════════════

        _pokemonBloqueado(pokemon) {
            if (!pokemon) return true;
            return this.enExploracion(pokemon.id) || this.pokemonEnExploracion(pokemon);
        },

        puedeAlimentar(requisito) {
            if (!requisito) return false;
            if (this._pokemonBloqueado(this.detailPokemon)) return false;
            if (requisito.actual >= requisito.necesario) return false;
            if (requisito.caramelosDisponibles <= 0) return false;
            return true;
        },

        async alimentarCaramelo(requisito, cantidad = 1) {
            const pokemon = this.detailPokemon;
            if (!pokemon || !requisito || !this.puedeAlimentar(requisito) || this.detailCandyFeeding) return;

            const cantidadEnvio = Math.max(1, Number(cantidad) || 1);
            this.detailCandyFeeding = true;
            try {
                const response = await fetch('/reclutado/' + pokemon.id + '/dar-caramelo', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        tipo: requisito.tipo,
                        evolved_species_id: this.selectedEvolucionId,
                        cantidad: cantidadEnvio,
                    }),
                });
                if (response.ok) {
                    const data = await response.json();
                    requisito.actual = data.actual;
                    requisito.caramelosDisponibles = data.caramelos_disponibles;
                    const opcion = this.opcionSeleccionada();
                    if (opcion && typeof data.puede_evolucionar === 'boolean') {
                        opcion.puede_evolucionar = data.puede_evolucionar;
                    }
                } else {
                    await this.handleError(response);
                }
            } catch (err) {
                console.error('Error dando caramelo:', err);
            } finally {
                this.detailCandyFeeding = false;
            }
        },

        async evolvePokemon() {
            const pokemon = this.detailPokemon;
            if (!pokemon || this._pokemonBloqueado(pokemon)) return;
            if (this.detailEvoluciones.length > 1 && !this.selectedEvolucionId) {
                alert('Selecciona a qué pokémon evolucionar');
                return;
            }
            this.detailLoading = true;
            try {
                const body = this.selectedEvolucionId ? { evolved_species_id: this.selectedEvolucionId } : {};
                const response = await fetch('/reclutado/' + pokemon.id + '/evolucionar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });
                if (response.ok) {
                    const data = await response.json();
                    if (data.pokemon_id) {
                        pokemon.pokemon_id = data.pokemon_id;
                    }

                    if (data.reclutado) {
                        this._actualizarReclutadoEnMemoria(data.reclutado);
                        this.detailPokemon = this.reclutados.find(r => String(r.id) === String(data.reclutado.id)) || data.reclutado;
                        this.loadoutHabilidadId = this.detailPokemon.habilidad_activa_id ?? '';
                        this.loadoutMovimientos = Array.isArray(this.detailPokemon.movimientos_seleccionados)
                            ? [...this.detailPokemon.movimientos_seleccionados]
                            : [];
                        this.filtrosMovimientoTipo = [];
                        this.filtrosMovimientoCategoria = [];
                        this.filtroNombreMovimiento = '';
                        this.loadoutFeedback = { tipo: '', mensaje: '' };
                        this.detailEvFeedback = { tipo: '', mensaje: '' };
                        this.cargarEvoluciones(this.detailPokemon);
                        setTimeout(() => this.renderDetailCharts(), 50);
                        return;
                    }

                    location.reload();
                    return;
                }
                await this.handleError(response);
            } catch (err) {
                console.error('Error evolving pokemon:', err);
            } finally {
                this.detailLoading = false;
            }
        },

        async releasePokemon() {
            const pokemon = this.detailPokemon;
            if (!pokemon || this._pokemonBloqueado(pokemon)) return;
            if (!confirm('¿Seguro que quieres liberar a ' + (pokemon.nombre || 'este Pokémon') + '? Esta acción no se puede deshacer.')) return;
            this.detailLoading = true;
            try {
                const response = await fetch('/reclutado/' + pokemon.id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });
                if (response.ok) {
                    const data = await response.json();
                    if (data && data.success !== false) {
                        this.reclutados = this.reclutados.filter(r => r.id !== pokemon.id);
                        this.teamPokemonIds = this.teamPokemonIds.filter(id => id !== pokemon.id);
                        this.teams.forEach(t => {
                            t.members = t.members.filter(m => m.pokemon_id !== pokemon.id);
                        });
                        this.closeDetail();
                        return;
                    }
                    alert('No se pudo liberar el Pokémon');
                    return;
                }
                let message = 'Error al liberar el Pokémon';
                try {
                    const data = await response.json();
                    message = data.error || data.message || message;
                } catch (err) {}
                alert(message);
            } catch (err) {
                console.error('Error releasing pokemon:', err);
            } finally {
                this.detailLoading = false;
            }
        },

        async handleError(response) {
            if (response.status === 422) {
                let message = 'Error de validación';
                try {
                    const data = await response.json();
                    message = data.error || data.message || message;
                } catch (err) {}
                alert(message);
            }
        },

        // ═══════════════════════════════════════════════════════════════════════
        // EXPLORATION MODAL
        // ═══════════════════════════════════════════════════════════════════════

        async openExploracionModal(pokemon) {
            this.exploracionPokemon = pokemon;
            this.exploracionHabitatId = null;
            this.exploracionLevel = 1;
            this.durationMode = 'hours';
            this.durationHours = 4;
            this.returnTime = '18:00';
            this.preview = null;
            this.previewLoaded = false;
            this.previewError = '';
            this.sendingExploracion = false;
            this.showExploracionModal = true;

            if (this.habitats.length === 0 && !this.habitatsLoading && !this.habitatsError) {
                await this.loadHabitats();
            }
            if (this.exploracionHabitatId && this.exploracionLevel) {
                this.loadPreview();
            }
        },

        closeExploracionModal() {
            this.showExploracionModal = false;
            this.exploracionPokemon = null;
        },

        async loadHabitats() {
            this.habitatsLoading = true;
            this.habitatsError = '';
            try {
                const response = await fetch('/datagrid/habitat?per_page=200', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('No se pudieron cargar los hábitats');
                const data = await response.json();
                this.habitats = data.data || [];
                if (this.habitats.length === 0) {
                    this.habitatsError = 'No hay hábitats disponibles.';
                }
            } catch (e) {
                console.error('Error loading habitats:', e);
                this.habitatsError = 'Función en preparación.';
                this.habitats = [];
            } finally {
                this.habitatsLoading = false;
            }
        },

        async loadPreview() {
            if (!this.exploracionPokemon || !this.exploracionHabitatId || !this.exploracionLevel) return;
            this.previewLoading = true;
            this.previewLoaded = false;
            this.previewError = '';
            this.preview = null;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const params = new URLSearchParams({
                    reclutado_id: this.exploracionPokemon.id,
                    habitat_id: this.exploracionHabitatId,
                    level: this.exploracionLevel,
                });
                const response = await fetch('/exploraciones/preview?' + params.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });
                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    throw new Error(err.message || 'No se pudo calcular la preparación de la expedición.');
                }
                this.preview = await response.json();
                this.previewLoaded = true;
            } catch (e) {
                this.previewError = e.message || 'Error al calcular la preparación de la expedición.';
            } finally {
                this.previewLoading = false;
            }
        },

        async confirmExploracion() {
            if (!this.exploracionPokemon || !this.exploracionHabitatId || this.sendingExploracion) return;
            if (this.enExploracion(this.exploracionPokemon.id)) {
                alert('Este Pokémon ya está en una exploración activa.');
                this.closeExploracionModal();
                return;
            }
            this.sendingExploracion = true;
            try {
                const body = {
                    reclutado_id: this.exploracionPokemon.id,
                    habitat_id: this.exploracionHabitatId,
                    level: this.exploracionLevel,
                };
                if (this.durationMode === 'hours') {
                    body.duracion_horas = this.durationHours;
                } else if (this.durationMode === 'return_time') {
                    body.return_time = this.returnTime;
                }
                const response = await fetch('/api/exploraciones/store-individual', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });
                if (response.ok) {
                    location.reload();
                } else {
                    const data = await response.json().catch(() => ({}));
                    alert(data.message || 'No se pudo enviar la exploración.');
                }
            } catch (err) {
                console.error('Error sending exploration:', err);
                alert('Error de conexión al enviar la exploración.');
            } finally {
                this.sendingExploracion = false;
            }
        },
    };
}

</script>
@endpush
@endsection
