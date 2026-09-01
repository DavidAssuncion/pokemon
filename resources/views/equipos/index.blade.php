@extends('layouts.app')

@section('title', 'Equipos')

@section('content')
<div x-data="equiposApp()" x-init="init()">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Gestión de Equipos</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Organiza tus Pokémon en equipos de 3 para exploraciones</p>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left 1/3: Teams Panel -->
            <div class="space-y-4">
                <!-- New Team Button -->
                <button
                    @click="showNewTeamForm = !showNewTeamForm"
                    class="w-full px-4 py-3 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700 transition-colors flex items-center justify-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Nuevo Equipo
                </button>

                <!-- New Team Form -->
                <template x-if="showNewTeamForm">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <input
                            type="text"
                            x-model="newTeamName"
                            placeholder="Nombre del equipo"
                            class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 mb-3"
                            @keydown.enter="createTeam()"
                        >
                        <div class="flex gap-2">
                            <button
                                @click="createTeam()"
                                :disabled="!newTeamName.trim()"
                                class="flex-1 px-3 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:bg-gray-300 dark:disabled:bg-gray-700 disabled:cursor-not-allowed transition-colors"
                            >
                                Crear
                            </button>
                            <button
                                @click="showNewTeamForm = false; newTeamName = ''"
                                class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Teams List -->
                <div class="space-y-3">
                    <template x-for="team in teams" :key="team.id">
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border-2 overflow-hidden transition-all"
                            :class="selectedTeamId === team.id
                                ? 'border-blue-500 dark:border-blue-400'
                                : 'border-gray-200 dark:border-gray-700'"
                        >
                            <!-- Team header -->
                            <div class="flex items-center justify-between p-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <template x-if="team.is_locked || isInExploration(team.id)">
                                        <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2C9.243 2 7 4.243 7 7v3H6a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2v-8a2 2 0 00-2-2h-1V7c0-2.757-2.243-5-5-5zm0 2c1.654 0 3 1.346 3 3v3H9V7c0-1.654 1.346-3 3-3zm-6 8h12v8H6v-8z"/>
                                        </svg>
                                    </template>
                                    <button
                                        @click="!team.is_locked && !isInExploration(team.id) && startRename(team)"
                                        class="text-sm font-medium text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                                        :class="{ 'cursor-not-allowed opacity-50': team.is_locked || isInExploration(team.id) }"
                                        :disabled="team.is_locked || isInExploration(team.id)"
                                        x-text="team.name"
                                    ></button>
                                    <template x-if="team.members.length < 3">
                                        <span class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300 text-[10px] font-bold rounded">
                                            INVÁLIDO
                                        </span>
                                    </template>
                                    <template x-if="isInExploration(team.id)">
                                        <span class="px-1.5 py-0.5 bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 text-[10px] font-bold rounded uppercase">
                                            En exploración
                                        </span>
                                    </template>
                                    <template x-if="composicionBadge(team)">
                                        <span
                                            class="px-1.5 py-0.5 text-[10px] font-bold rounded uppercase"
                                            :class="composicionBadge(team).clase"
                                            x-text="composicionBadge(team).texto"
                                        ></span>
                                    </template>
                                </div>
                                <button
                                    @click="!team.is_locked && !isInExploration(team.id) && confirmDeleteTeam(team)"
                                    :disabled="team.is_locked || isInExploration(team.id)"
                                    class="w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                    :aria-label="'Eliminar equipo ' + team.name"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <!-- Team members -->
                            <div class="p-3">
                                <div class="flex gap-2">
                                    <template x-for="slot in [1,2,3]" :key="slot">
                                        <div class="flex-1 text-center">
                                            <template x-if="getMember(team, slot)">
                                                <div class="relative">
                                                    <img
                                                        :src="'/images/iconos_webp/' + getMember(team, slot).pokemon_id + '.webp'"
                                                        loading="lazy"
                                                        decoding="async"
                                                        :alt="getMember(team, slot).nombre"
                                                        :title="getMember(team, slot).nombre"
                                                        class="w-24 h-24 object-contain mx-auto"
                                                        onerror="this.style.display='none'"
                                                    >
                                                    <button
                                                        @click="!team.is_locked && !isInExploration(team.id) && removeMember(team, getMember(team, slot))"
                                                        :disabled="team.is_locked || isInExploration(team.id)"
                                                        class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white rounded-full text-[8px] flex items-center justify-center hover:bg-red-600 transition-colors disabled:opacity-30"
                                                        aria-label="Quitar miembro"
                                                    >
                                                        ✕
                                                    </button>
                                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate mt-0.5" x-text="getMember(team, slot).nombre"></p>
                                                    <!-- Role selector -->
                                                    <select
                                                        :value="getTeamMember(team, slot)?.behavior || 'VANGUARDIA'"
                                                        @change="updateMemberRole(team, getTeamMember(team, slot), $event.target.value)"
                                                        :disabled="team.is_locked || isInExploration(team.id)"
                                                        class="mt-1 w-full max-w-[6.5rem] mx-auto block px-1 py-0.5 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded text-[10px] text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:opacity-40 disabled:cursor-not-allowed"
                                                        :aria-label="'Rol de ' + getMember(team, slot).nombre"
                                                    >
                                                        <option value="VANGUARDIA">Vanguardia</option>
                                                        <option value="COMBATIENTE">Combatiente</option>
                                                        <option value="RECOLECTOR">Recolector</option>
                                                        <option value="RASTREADOR">Rastreador</option>
                                                    </select>
                                                </div>
                                            </template>
                                            <template x-if="!getMember(team, slot)">
                                                <div>
                                                    <div class="w-24 h-24 mx-auto rounded border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center">
                                                        <span class="text-gray-300 dark:text-gray-600 text-lg">+</span>
                                                    </div>
                                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">Slot <span x-text="slot"></span></p>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Empty teams -->
                    <template x-if="teams.length === 0">
                        <div class="text-center py-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                            <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No hay equipos creados</p>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Right 2/3: Available Pokemon -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Search and Filter -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <input
                            type="text"
                            x-model="searchQuery"
                            placeholder="Buscar por nombre o ID..."
                            class="w-full px-4 py-2 pl-10 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
                        >
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <div class="relative">
                        <button
                            @click="showTypeFilter = !showTypeFilter"
                            class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                            </svg>
                            Tipo
                        </button>
                        <template x-if="showTypeFilter">
                            <div class="absolute right-0 top-full mt-1 z-40 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg p-3 w-48 max-h-64 overflow-y-auto">
                                <button
                                    @click="typeFilter = null; showTypeFilter = false"
                                    class="w-full text-left px-3 py-1.5 text-sm rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                    :class="typeFilter === null ? 'text-blue-600 dark:text-blue-400 font-medium' : 'text-gray-700 dark:text-gray-300'"
                                >
                                    Todos
                                </button>
                                @php
                                    $tipos = \App\Enums\TipoEnum::options();
                                @endphp
                                @foreach($tipos as $id => $nombre)
                                <button
                                    @click="typeFilter = '{{ $nombre }}'; showTypeFilter = false"
                                    class="w-full text-left px-3 py-1.5 text-sm rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                    :class="typeFilter === '{{ $nombre }}' ? 'text-blue-600 dark:text-blue-400 font-medium' : 'text-gray-700 dark:text-gray-300'"
                                >
                                    {{ $nombre }}
                                </button>
                                @endforeach
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Available Pokemon Section -->
                <div>
                    <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2 mb-4">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-700 dark:text-gray-300">Reclutados Disponibles</h2>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-2">
                        <template x-for="pokemon in availablePokemons" :key="pokemon.id">
                            <div
                                class="relative bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 text-center group"
                                x-data="{ showTeamDropdown: false }"
                            >
                                <div class="w-24 h-24 mx-auto">
                                    <img
                                        :src="'/images/iconos_webp/' + pokemon.pokemon_id + '.webp'"
                                        loading="lazy"
                                        decoding="async"
                                        :alt="pokemon.nombre"
                                        :title="pokemon.nombre"
                                        class="w-full h-full object-contain"
                                        onerror="this.style.display='none'"
                                    >
                                </div>
                                <p class="text-[10px] text-gray-600 dark:text-gray-400 truncate px-1 pb-1" x-text="pokemon.nombre"></p>
                                <!-- Action buttons -->
                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2 rounded-lg">
                                    <div class="relative">
                                        <button
                                            @click="showTeamDropdown = !showTeamDropdown"
                                            class="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center hover:bg-green-600 transition-colors text-lg font-bold"
                                            :aria-label="'Agregar ' + pokemon.nombre + ' a equipo'"
                                            :title="'Agregar ' + pokemon.nombre + ' a equipo'"
                                        >
                                            +
                                        </button>
                                        <!-- Team dropdown -->
                                        <template x-if="showTeamDropdown && teams.length > 0">
                                            <div class="absolute left-1/2 -translate-x-1/2 top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg p-1 w-36 z-50">
                                                <template x-for="team in teams" :key="team.id">
                                                    <button
                                                        @click="addToTeam(pokemon, team); showTeamDropdown = false"
                                                        class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                                        x-text="team.name"
                                                    ></button>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                    <!-- Inspect (always available, read-only) -->
                                    <button
                                        @click="openDetail(pokemon)"
                                        class="w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center hover:bg-blue-600 transition-colors"
                                        :aria-label="'Ver detalle de ' + pokemon.nombre"
                                        :title="'Ver detalle de ' + pokemon.nombre"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Empty available -->
                        <template x-if="availablePokemons.length === 0">
                            <div class="col-span-full text-center py-8">
                                <p class="text-sm text-gray-400 dark:text-gray-500" x-text="searchQuery || typeFilter ? 'No se encontraron Pokémon' : 'No hay Pokémon disponibles para asignar'"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Assigned Pokemon Section -->
                <div>
                    <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2 mb-4">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-700 dark:text-gray-300">Asignados</h2>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-2">
                        <template x-for="pokemon in assignedPokemons" :key="pokemon.id">
                            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden text-center group opacity-60">
                                <div class="w-24 h-24 mx-auto">
                                    <img
                                        :src="'/images/iconos_webp/' + pokemon.pokemon_id + '.webp'"
                                        loading="lazy"
                                        decoding="async"
                                        :alt="pokemon.nombre"
                                        :title="pokemon.nombre + ' — ' + getTeamName(pokemon.team_id)"
                                        class="w-full h-full object-contain grayscale"
                                        onerror="this.style.display='none'"
                                    >
                                </div>
                                <p class="text-[10px] text-gray-500 dark:text-gray-500 truncate px-1 pb-1" x-text="pokemon.nombre"></p>
                                <!-- Action buttons -->
                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                    <!-- Inspect (always available, read-only) -->
                                    <button
                                        @click="openDetail(pokemon)"
                                        class="w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center hover:bg-blue-600 transition-colors"
                                        :aria-label="'Ver detalle de ' + pokemon.nombre"
                                        :title="'Ver detalle de ' + pokemon.nombre"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                    <!-- Remove from team (blocked while the team is exploring) -->
                                    <button
                                        @click="!pokemonEnExploracion(pokemon) && removeFromTeam(pokemon)"
                                        :disabled="pokemonEnExploracion(pokemon)"
                                        :title="pokemonEnExploracion(pokemon) ? 'El equipo está en exploración' : 'Quitar ' + pokemon.nombre + ' de equipo'"
                                        class="w-8 h-8 bg-orange-500 text-white rounded-full flex items-center justify-center hover:bg-orange-600 transition-colors text-sm disabled:opacity-30 disabled:cursor-not-allowed"
                                        :aria-label="'Quitar ' + pokemon.nombre + ' de equipo'"
                                    >
                                        →
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Empty assigned -->
                        <template x-if="assignedPokemons.length === 0">
                            <div class="col-span-full text-center py-8">
                                <p class="text-sm text-gray-400 dark:text-gray-500">No hay Pokémon asignados a equipos</p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

    <!-- Confirm Delete Team Modal -->
    <template x-if="showDeleteModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="showDeleteModal = false">
            <div class="absolute inset-0 bg-black/60" @click="showDeleteModal = false"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm p-6">
                <div class="text-center mb-4">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Eliminar equipo</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        ¿Seguro que quieres eliminar <strong class="text-gray-900 dark:text-white" x-text="teamToDelete?.name"></strong>? Los miembros serán liberados.
                    </p>
                </div>
                <div class="flex gap-3">
                    <button
                        @click="showDeleteModal = false; teamToDelete = null"
                        class="flex-1 px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="deleteTeam()"
                        class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-bold hover:bg-red-700 transition-colors"
                    >
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- Pokemon Detail Modal -->
    <template x-if="showDetailModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeDetail()" role="dialog" aria-modal="true" aria-labelledby="detail-modal-title">
            <div class="absolute inset-0 bg-black/60" @click="closeDetail()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="flex items-start gap-4 p-6 border-b border-gray-100 dark:border-gray-700">
                    <div class="w-24 h-24 shrink-0 rounded-lg bg-gray-50 dark:bg-gray-900/50 flex items-center justify-center overflow-hidden">
                        <img
                            :src="'/images/iconos_webp/' + detailPokemon.pokemon_id + '.webp'"
                            loading="lazy"
                            decoding="async"
                            :alt="detailPokemon.nombre"
                            class="w-full h-full object-contain"
                            onerror="this.style.display='none'"
                        >
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 id="detail-modal-title" class="text-lg font-bold text-gray-900 dark:text-white capitalize truncate" x-text="detailPokemon.nombre"></h3>
                            <template x-if="detailPokemon.es_shiny">
                                <span class="px-1.5 py-0.5 bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-400 text-[10px] font-bold rounded uppercase" title="Shiny">★ Shiny</span>
                            </template>
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500" x-text="'#' + detailPokemon.pokemon_id"></p>
                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                            <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-400 text-xs font-bold rounded-full">Nv <span x-text="nivelDe(detailPokemon)"></span></span>
                            <span class="text-xs text-gray-500 dark:text-gray-400" x-text="formatExp(expTotalDe(detailPokemon)) + ' exp'"></span>
                        </div>
                        <!-- Progress to next level -->
                        <div class="mt-2">
                            <div class="flex items-center justify-between text-[10px] text-gray-400 dark:text-gray-500 mb-1">
                                <span>Progreso al siguiente nivel</span>
                                <span x-text="progresoNivelDe(detailPokemon) + '%'"></span>
                            </div>
                            <div class="h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-500 rounded-full transition-all duration-500" :style="'width: ' + progresoNivelDe(detailPokemon) + '%'"></div>
                            </div>
                        </div>
                    </div>
                    <button
                        @click="closeDetail()"
                        class="shrink-0 w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        aria-label="Cerrar"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-5">
                    <!-- Types -->
                    <template x-if="detailPokemon.pokemon?.types?.length">
                        <div class="flex items-center gap-2 flex-wrap">
                            <template x-for="t in detailPokemon.pokemon.types" :key="t.id ?? t.slot ?? t.type">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full inline-flex items-center" :class="tipoClase(tipoLabelDel(t))" x-text="tipoLabelDel(t)"></span>
                            </template>
                        </div>
                    </template>

                    <!-- Base experience -->
                    <template x-if="baseExperienceDe(detailPokemon) != null">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Exp. base</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200" x-text="formatExp(baseExperienceDe(detailPokemon))"></span>
                        </div>
                    </template>

                    <!-- Base stats -->
                    <template x-if="statsDe(detailPokemon).length > 0">
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Stats base</h4>
                            <div class="space-y-1.5">
                                <template x-for="stat in statsDe(detailPokemon)" :key="stat.name">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] text-gray-500 dark:text-gray-400 w-20 text-right truncate" x-text="stat.name"></span>
                                        <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div class="h-full bg-blue-500 rounded-full" :style="'width: ' + Math.min((stat.value / 255) * 100, 100) + '%'"></div>
                                        </div>
                                        <span class="text-[10px] font-medium text-gray-700 dark:text-gray-300 w-6" x-text="stat.value"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Locked while exploring -->
                    <template x-if="pokemonEnExploracion(detailPokemon)">
                        <div class="px-3 py-2 rounded-lg bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 text-xs" role="status">
                            Este Pokémon está en un equipo en exploración: no puedes evolucionarlo ni liberarlo hasta que vuelva.
                        </div>
                    </template>

                    <!-- Evolution / release actions -->
                    <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Evolución</h4>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <button
                                @click="evolvePokemon()"
                                :disabled="detailLoading || pokemonEnExploracion(detailPokemon)"
                                :title="pokemonEnExploracion(detailPokemon) ? 'El equipo está en exploración' : 'Evolucionar Pokémon'"
                                class="flex-1 px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-bold hover:bg-green-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                <span x-text="detailLoading ? 'Evolucionando…' : 'Evolucionar'"></span>
                            </button>
                            <button
                                @click="releasePokemon()"
                                :disabled="detailLoading || pokemonEnExploracion(detailPokemon)"
                                :title="pokemonEnExploracion(detailPokemon) ? 'El equipo está en exploración' : 'Liberar Pokémon'"
                                class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-bold hover:bg-red-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                Liberar
                            </button>
                        </div>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-2">
                            La evolución consume caramelos y exp de tipo; los requisitos se validan al intentar evolucionar.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

@push('scripts')
<script>
function equiposApp() {
    return {
        teams: @json($teams ?? []),
        reclutados: @json($reclutados ?? []),
        teamPokemonIds: @json($teamIds ?? []),
        equiposEnExploracion: @json($equiposEnExploracion ?? collect()),
        selectedTeamId: null,
        showNewTeamForm: false,
        newTeamName: '',
        searchQuery: '',
        typeFilter: null,
        showTypeFilter: false,
        showDeleteModal: false,
        teamToDelete: null,
        showDetailModal: false,
        detailPokemon: null,
        detailLoading: false,

        init() {
            // Close type filter on outside click
            document.addEventListener('click', (e) => {
                if (!e.target.closest('[x-data]')?.contains(e.target)) {
                    this.showTypeFilter = false;
                }
            });
        },

        isInExploration(teamId) {
            // Defensivo con ambos contratos: el backend hoy envía un array plano de
            // equipo_id (pluck), y la vista asumía objetos {equipo_id}.
            return this.equiposEnExploracion.some(e =>
                typeof e === 'object' && e !== null ? e.equipo_id === teamId : e === teamId
            );
        },

        get availablePokemons() {
            let result = this.reclutados.filter(r => !this.teamPokemonIds.includes(r.id));

            if (this.searchQuery.trim()) {
                const q = this.searchQuery.toLowerCase().trim();
                result = result.filter(r =>
                    r.nombre.toLowerCase().includes(q) ||
                    String(r.pokemon_id).includes(q)
                );
            }

            if (this.typeFilter) {
                result = result.filter(r =>
                    r.pokemon && r.pokemon.types &&
                    r.pokemon.types.some(t => t.tipo_nombre === this.typeFilter)
                );
            }

            return result;
        },

        get assignedPokemons() {
            let result = this.reclutados.filter(r => this.teamPokemonIds.includes(r.id));

            if (this.searchQuery.trim()) {
                const q = this.searchQuery.toLowerCase().trim();
                result = result.filter(r =>
                    r.nombre.toLowerCase().includes(q) ||
                    String(r.pokemon_id).includes(q)
                );
            }

            return result;
        },

        getMember(team, slot) {
            const member = team.members.find(m => m.slot === slot);
            return member ? member.reclutado : null;
        },

        getTeamMember(team, slot) {
            return team.members.find(m => m.slot === slot) || null;
        },

        composicionBadge(team) {
            if (!team.members || team.members.length !== 3) return null;

            const nombre = team.sinergia_nombre;
            if (typeof nombre === 'string' && nombre.trim()) {
                return {
                    texto: 'Composición: ' + nombre,
                    clase: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                };
            }

            return {
                texto: 'Composición neutra',
                clase: 'bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300',
            };
        },

        async updateMemberRole(team, member, behavior) {
            if (!member || !member.id || !behavior) return;

            // DECISIÓN endpoint: no existe PATCH /teams/member/{member}/role en el backend;
            // el contrato documentado es POST /teams/update-member-role con member_id.
            try {
                const response = await fetch('/teams/update-member-role', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ member_id: member.id, behavior }),
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.member) {
                        member.behavior = data.member.behavior || behavior;
                    }
                    // DECISIÓN sinergia: el PATCH no devuelve sinergia_nombre recalculado; el
                    // payload inicial de equipos sí la expone. Para que el badge de composición
                    // quede coherente con el nuevo rol, recargamos la página (simple y correcto).
                    location.reload();
                } else {
                    await this.handleError(response);
                }
            } catch (err) {
                console.error('Error updating member role:', err);
            }
        },

        getTeamName(teamId) {
            const team = this.teams.find(t => t.id === teamId);
            return team ? team.name : '';
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
                    this.teams = this.teams.filter(t => t.id !== this.teamToDelete.id);
                    // Liberar miembros: quitar del teamPokemonIds los pokemon de ese team
                    this.teamToDelete.members.forEach(m => {
                        if (m.pokemon_id) {
                            this.teamPokemonIds = this.teamPokemonIds.filter(id => id !== m.pokemon_id);
                        }
                    });
                    this.showDeleteModal = false;
                    this.teamToDelete = null;
                } else {
                    await this.handleError(response);
                }
            } catch (err) {
                console.error('Error deleting team:', err);
            }
        },

        async addToTeam(pokemon, team) {
            const emptySlot = [1,2,3].find(s => !team.members.some(m => m.slot === s));
            if (!emptySlot) {
                alert('Este equipo está lleno');
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
                        // Añadir miembro al team
                        const team = this.teams.find(t => t.id === data.member.team_id);
                        if (team) {
                            team.members.push({
                                id: data.member.id,
                                team_id: data.member.team_id,
                                pokemon_id: data.member.pokemon_id,
                                slot: data.member.slot,
                                behavior: data.member.behavior || 'VANGUARDIA',
                                reclutado: pokemon,
                            });
                        }
                        // Marcar como asignado
                        this.teamPokemonIds.push(pokemon.id);
                    }
                } else {
                    await this.handleError(response);
                }
            } catch (err) {
                console.error('Error adding member:', err);
            }
        },

        async removeMember(team, pokemon) {
            const member = team.members.find(m => m.pokemon_id === pokemon.id);
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
                    // Quitar miembro del team
                    team.members = team.members.filter(m => m.id !== member.id);
                    // Liberar pokemon
                    this.teamPokemonIds = this.teamPokemonIds.filter(id => id !== pokemon.id);
                } else {
                    await this.handleError(response);
                }
            } catch (err) {
                console.error('Error removing member:', err);
            }
        },

        removeFromTeam(pokemon) {
            const team = this.teams.find(t => t.members.some(m => m.pokemon_id === pokemon.id));
            if (team) {
                this.removeMember(team, pokemon);
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

        async handleError(response) {
            if (response.status === 422) {
                let message = 'Error de validación';
                try {
                    const data = await response.json();
                    message = data.error || message;
                } catch (err) {
                    // Ignorar: el cuerpo no es JSON
                }
                alert(message);
            }
        },

        // ─── Modal de detalle ──────────────────────────────────────────────

        openDetail(pokemon) {
            this.detailPokemon = pokemon;
            this.detailLoading = false;
            this.showDetailModal = true;
        },

        closeDetail() {
            this.showDetailModal = false;
            this.detailPokemon = null;
            this.detailLoading = false;
        },

        // ─── Nivel/exp (replica NivelHelper: curva media ×10) ──────────────

        expTotalDe(pokemon) {
            if (!pokemon) return 0;
            if (typeof pokemon.exp_total === 'number') return pokemon.exp_total;
            return pokemon.exp && typeof pokemon.exp.total === 'number' ? pokemon.exp.total : 0;
        },

        expParaNivel(nivel) {
            return 10 * Math.pow(nivel, 3);
        },

        nivelDe(pokemon) {
            if (pokemon && typeof pokemon.nivel === 'number' && pokemon.nivel >= 1) return pokemon.nivel;

            const exp = this.expTotalDe(pokemon);
            if (exp <= 0) return 1;

            const base = exp / 10;
            let nivel = Math.floor(Math.cbrt(base));
            while (Math.pow(nivel + 1, 3) <= base) nivel++;
            while (Math.pow(nivel, 3) > base) nivel--;

            return Math.max(1, nivel);
        },

        progresoNivelDe(pokemon) {
            const exp = this.expTotalDe(pokemon);
            const nivel = this.nivelDe(pokemon);
            const inicio = this.expParaNivel(nivel);
            const fin = this.expParaNivel(nivel + 1);
            const rango = fin - inicio;
            if (rango <= 0) return 100;

            return Math.max(0, Math.min(100, Math.round(((exp - inicio) / rango) * 100)));
        },

        formatExp(exp) {
            return Number(exp || 0).toLocaleString('es-ES');
        },

        statsDe(pokemon) {
            if (!pokemon) return [];
            if (Array.isArray(pokemon.stats) && pokemon.stats.length > 0) return pokemon.stats;
            if (pokemon.pokemon && Array.isArray(pokemon.pokemon.stats) && pokemon.pokemon.stats.length > 0) return pokemon.pokemon.stats;
            return [];
        },

        baseExperienceDe(pokemon) {
            if (!pokemon) return null;
            if (typeof pokemon.base_experience === 'number') return pokemon.base_experience;
            if (pokemon.pokemon && typeof pokemon.pokemon.base_experience === 'number') return pokemon.pokemon.base_experience;
            return null;
        },

        // ─── Tipos (defensivo: tipo_nombre si el backend lo expone; si no, mapa TipoEnum int → español) ───

        tipoLabelDel(t) {
            if (!t) return 'Tipo';
            if (t.tipo_nombre) return t.tipo_nombre;
            if (typeof t.type === 'number') {
                const nombres = {
                    1: 'Normal', 2: 'Lucha', 3: 'Volador', 4: 'Veneno', 5: 'Tierra', 6: 'Roca',
                    7: 'Bicho', 8: 'Fantasma', 9: 'Acero', 10: 'Fuego', 11: 'Agua', 12: 'Planta',
                    13: 'Eléctrico', 14: 'Psíquico', 15: 'Hielo', 16: 'Dragón', 17: 'Siniestro', 18: 'Hada',
                };
                return nombres[t.type] || ('#' + t.type);
            }
            return t.type || 'Tipo';
        },

        tipoClase(nombre) {
            const mapa = {
                'normal': 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                'lucha': 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
                'volador': 'bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300',
                'veneno': 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300',
                'tierra': 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300',
                'roca': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300',
                'bicho': 'bg-lime-100 text-lime-700 dark:bg-lime-900/50 dark:text-lime-300',
                'fantasma': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300',
                'acero': 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
                'fuego': 'bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-300',
                'agua': 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
                'planta': 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300',
                'eléctrico': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300',
                'psíquico': 'bg-pink-100 text-pink-700 dark:bg-pink-900/50 dark:text-pink-300',
                'hielo': 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/50 dark:text-cyan-300',
                'dragón': 'bg-violet-100 text-violet-700 dark:bg-violet-900/50 dark:text-violet-300',
                'siniestro': 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
                'hada': 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300',
            };
            return mapa[String(nombre || '').toLowerCase()] || 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
        },

        // ─── Guardas: equipo del reclutado / equipo en exploración ─────────

        pokemonTeamId(pokemon) {
            if (!pokemon) return null;
            const team = this.teams.find(t => t.members.some(m => m.pokemon_id === pokemon.id));
            return team ? team.id : null;
        },

        pokemonEnExploracion(pokemon) {
            const teamId = this.pokemonTeamId(pokemon);
            return teamId !== null && this.isInExploration(teamId);
        },

        // ─── Acciones: evolucionar / liberar ───────────────────────────────

        async evolvePokemon() {
            const pokemon = this.detailPokemon;
            if (!pokemon || this.pokemonEnExploracion(pokemon)) return;

            this.detailLoading = true;
            try {
                const response = await fetch('/reclutado/' + pokemon.id + '/evolucionar', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.pokemon_id) {
                        pokemon.pokemon_id = data.pokemon_id;
                    }
                    // El payload no expone el pokémon destino completo (nombre/tipos/stats);
                    // recargamos la página para mantener listas/badges coherentes (criterio de updateMemberRole).
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
            if (!pokemon || this.pokemonEnExploracion(pokemon)) return;

            if (!confirm('¿Seguro que quieres liberar a ' + (pokemon.nombre || 'este Pokémon') + '? Esta acción no se puede deshacer.')) {
                return;
            }

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
                        // Quitar de la lista de reclutados, de asignados y de los miembros de equipos
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

                // Robustez: si el endpoint aún no está desplegado (404/405) o rechaza (422),
                // mostramos el error sin mutar estado.
                let message = 'Error al liberar el Pokémon';
                try {
                    const data = await response.json();
                    message = data.error || data.message || message;
                } catch (err) {
                    // Ignorar: el cuerpo no es JSON
                }
                alert(message);
            } catch (err) {
                console.error('Error releasing pokemon:', err);
            } finally {
                this.detailLoading = false;
            }
        },
    };
}
</script>
@endpush
@endsection
