@extends('layouts.app')

@section('title', 'Equipos')

@section('content')
@php
    $tipos = \App\Enums\TipoEnum::options();
@endphp
<div x-data="favoritosApp()" x-init="init()">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Equipos</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestiona tus equipos de combate</p>
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
                    <template x-for="team in teams" :key="'team-'+team.id">
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border-2 overflow-hidden transition-all"
                            :class="selectedTeamId === team.id
                                ? 'border-blue-500 dark:border-blue-400'
                                : 'border-gray-200 dark:border-gray-700'"
                        >
                            <!-- Team header -->
                            <div class="flex items-center justify-between p-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <template x-if="isInExploration(team.id)">
                                        <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2C9.243 2 7 4.243 7 7v3H6a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2v-8a2 2 0 00-2-2h-1V7c0-2.757-2.243-5-5-5zm0 2c1.654 0 3 1.346 3 3v3H9V7c0-1.654 1.346-3 3-3zm-6 8h12v8H6v-8z"/>
                                        </svg>
                                    </template>
                                    <button
                                        @click="!isInExploration(team.id) && startRename(team)"
                                        class="text-sm font-medium text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                                        :class="{ 'cursor-not-allowed opacity-50': isInExploration(team.id) }"
                                        :disabled="isInExploration(team.id)"
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
                                    @click="!isInExploration(team.id) && confirmDeleteTeam(team)"
                                    :disabled="isInExploration(team.id)"
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
                                <div class="grid grid-cols-3 gap-1">
                                    <template x-for="slot in [1,2,3]" :key="'slot-'+team.id+'-'+slot">
                                        <div class="text-center min-w-0">
                                            <template x-if="getMember(team, slot)">
                                                <div class="relative">
                                                    <img
                                                        :src="iconoDe(getMember(team, slot))"
                                                        loading="lazy"
                                                        decoding="async"
                                                        :alt="nombreFormateado(getMember(team, slot))"
                                                        :title="nombreFormateado(getMember(team, slot))"
                                                        class="w-full h-16 object-contain mx-auto"
                                                        onerror="this.style.display='none'"
                                                    >
                                                    <button
                                                        @click="!isInExploration(team.id) && removeMember(team, getMember(team, slot))"
                                                        :disabled="isInExploration(team.id)"
                                                        class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white rounded-full text-[8px] flex items-center justify-center hover:bg-red-600 transition-colors disabled:opacity-30"
                                                        aria-label="Quitar miembro"
                                                    >
                                                        ✕
                                                    </button>
                                                    <!-- Nombre del Pokémon -->
                                                    <p class="text-[10px] leading-tight text-gray-500 dark:text-gray-400 mt-0.5" x-text="nombreFormateado(getMember(team, slot))"></p>
                                                    <!-- Formación: iconos seleccionables -->
                                                     <div class="flex flex-row items-center justify-center gap-1">
                                                        <button
                                                            @click="setFormacionSlot(team, slot, 'vanguardia')"
                                                            :disabled="isInExploration(team.id)"
                                                            :class="formacionDe(team, slot) === 'vanguardia' ? 'bg-blue-600/15 dark:bg-blue-500/20 border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-400 dark:text-gray-600 hover:text-gray-600 dark:hover:text-gray-400 opacity-70'"
                                                            class="rounded px-1.5 py-0.5 border transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
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
                                                            :disabled="isInExploration(team.id)"
                                                            :class="formacionDe(team, slot) === 'retaguardia' ? 'bg-red-600/15 dark:bg-red-500/20 border-red-500 text-red-600 dark:text-red-400' : 'border-transparent text-gray-400 dark:text-gray-600 hover:text-gray-600 dark:hover:text-gray-400 opacity-70'"
                                                            class="rounded px-1.5 py-0.5 border transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                                            :aria-label="'Posición retaguardia de ' + nombreFormateado(getMember(team, slot))"
                                                            :aria-pressed="formacionDe(team, slot) === 'retaguardia'"
                                                            :title="'Posición retaguardia de ' + nombreFormateado(getMember(team, slot))"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path d="M14.5 17.5L3 6V3h3l11.5 11.5M13 19l6-6M16 16l4 4M19 21l2-2"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                            <template x-if="!getMember(team, slot)">
                                                <div>
                                                    <div class="w-full h-16 mx-auto rounded border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center">
                                                        <span class="text-gray-300 dark:text-gray-600 text-lg">+</span>
                                                    </div>
                                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">Slot <span x-text="slot"></span></p>
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
                                        :disabled="isInExploration(team.id) || formacionSavingTeamId === team.id"
                                        class="flex-1 px-3 py-1.5 bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900 rounded-lg text-[10px] font-bold uppercase tracking-wide hover:bg-gray-900 dark:hover:bg-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                        x-text="formacionSavingTeamId === team.id ? 'Guardando...' : 'Guardar formación'"
                                    ></button>
                                </div>
                                <div x-show="formacionSaveError" x-cloak role="alert" class="mt-1 text-[10px] text-red-600 dark:text-red-400" x-text="formacionSaveError"></div>
                                <div x-show="formacionSaveSuccess" x-cloak role="status" class="mt-1 text-[10px] text-green-600 dark:text-green-400" x-text="formacionSaveSuccess"></div>
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

            <!-- Right 2/3: Available Pokemon (equipos) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Section switcher: Pokémon / Huevos -->
                <div class="flex rounded-xl bg-gray-100 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700 p-1" role="tablist" aria-label="Secciones del equipo">
                    <button
                        @click="cambiarSeccion('pokemon')"
                        role="tab"
                        :aria-selected="activeSeccion === 'pokemon'"
                        class="flex-1 px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                        :class="activeSeccion === 'pokemon'
                            ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-sm'
                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'"
                    >
                        ⚔️ Pokémon
                    </button>
                    <button
                        @click="cambiarSeccion('huevos')"
                        role="tab"
                        :aria-selected="activeSeccion === 'huevos'"
                        class="flex-1 px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                        :class="activeSeccion === 'huevos'
                            ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-sm'
                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'"
                    >
                        🥚 Huevos
                    </button>
                </div>

                <template x-if="activeSeccion === 'pokemon'">
                <div class="space-y-6">
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

                <!-- Available Pokemon Section (not assigned to any team) -->
                <div>
                    <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2 mb-4">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-700 dark:text-gray-300">Reclutados Disponibles</h2>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-2">
                        <template x-for="pokemon in availablePokemonsTeams" :key="'eq-avail-'+pokemon.id">
                            <div
                                class="relative bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 text-center group"
                                x-data="{ showTeamDropdown: false }"
                            >
                                <div class="w-24 h-24 mx-auto">
                                    <img
                                        :src="iconoDe(pokemon)"
                                        loading="lazy"
                                        decoding="async"
                                        :alt="pokemon.nombre || pokemon.pokemon_id || 'Pokémon'"
                                        :title="pokemon.nombre || pokemon.pokemon_id || 'Pokémon'"
                                        class="w-full h-full object-contain"
                                        onerror="this.style.display='none'"
                                    >
                                </div>
                                <p class="text-[10px] text-gray-600 dark:text-gray-400 truncate px-1 pb-1" x-text="nombreDe(pokemon)"></p>
                                <template x-if="cpDe(pokemon) != null">
                                    <p class="text-[10px] font-bold text-purple-600 dark:text-purple-400 leading-none" x-text="'CP ' + cpDe(pokemon)"></p>
                                </template>
                                <!-- Action buttons -->
                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2 rounded-lg">
                                    <div class="relative">
                                        <button
                                            @click="showTeamDropdown = !showTeamDropdown"
                                            class="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center hover:bg-green-600 transition-colors text-lg font-bold"
                                            :aria-label="'Agregar ' + nombreDe(pokemon) + ' a equipo'"
                                            :title="'Agregar ' + nombreDe(pokemon) + ' a equipo'"
                                        >
                                            +
                                        </button>
                                        <!-- Team dropdown -->
                                        <template x-if="showTeamDropdown && teams.length > 0">
                                            <div class="absolute left-1/2 -translate-x-1/2 top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg p-1 w-36 z-50">
                                                <template x-for="team in teams" :key="'dd-'+team.id">
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
                                        :aria-label="'Ver detalle de ' + nombreDe(pokemon)"
                                        :title="'Ver detalle de ' + nombreDe(pokemon)"
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
                        <template x-if="availablePokemonsTeams.length === 0">
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
                        <template x-for="pokemon in assignedPokemons" :key="'eq-assigned-'+pokemon.id">
                            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden text-center group opacity-60">
                                <div class="w-24 h-24 mx-auto">
                                    <img
                                        :src="iconoDe(pokemon)"
                                        loading="lazy"
                                        decoding="async"
                                        :alt="pokemon.nombre || pokemon.pokemon_id || 'Pokémon'"
                                        :title="(pokemon.nombre || pokemon.pokemon_id || 'Pokémon') + ' — ' + getTeamName(pokemon.team_id)"
                                        class="w-full h-full object-contain grayscale"
                                        onerror="this.style.display='none'"
                                    >
                                </div>
                                <p class="text-[10px] text-gray-500 dark:text-gray-500 truncate px-1 pb-1" x-text="nombreDe(pokemon)"></p>
                                <template x-if="cpDe(pokemon) != null">
                                    <p class="text-[10px] font-bold text-purple-500/70 leading-none" x-text="'CP ' + cpDe(pokemon)"></p>
                                </template>
                                <!-- Action buttons -->
                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                    <!-- Inspect (always available, read-only) -->
                                    <button
                                        @click="openDetail(pokemon)"
                                        class="w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center hover:bg-blue-600 transition-colors"
                                        :aria-label="'Ver detalle de ' + nombreDe(pokemon)"
                                        :title="'Ver detalle de ' + nombreDe(pokemon)"
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
                                        :title="pokemonEnExploracion(pokemon) ? 'El equipo está en exploración' : 'Quitar ' + nombreDe(pokemon) + ' de equipo'"
                                        class="w-8 h-8 bg-orange-500 text-white rounded-full flex items-center justify-center hover:bg-orange-600 transition-colors text-sm disabled:opacity-30 disabled:cursor-not-allowed"
                                        :aria-label="'Quitar ' + nombreDe(pokemon) + ' de equipo'"
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
                    </template>

                    <!-- ═══ Huevos (tab) ═══ -->
                    <template x-if="activeSeccion === 'huevos'">
                        <div class="space-y-6">
                            <!-- Feedback de acciones huevos -->
                            <template x-if="huevosFeedback.mensaje">
                                <div class="px-3 py-2 rounded-lg text-xs" role="status"
                                    :class="huevosFeedback.tipo === 'error'
                                        ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'
                                        : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'"
                                    x-text="huevosFeedback.mensaje">
                                </div>
                            </template>

                            <!-- Límite de huevos activo -->
                            <template x-if="limiteExcedido()">
                                <div class="px-3 py-2 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-xs" role="status">
                                    ⏳ <span x-text="limiteMensaje()"></span>
                                </div>
                            </template>

                            <!-- Loading -->
                            <template x-if="huevosLoading">
                                <div class="flex items-center gap-2 text-sm text-gray-400 dark:text-gray-500 py-6 justify-center" role="status">
                                    <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Cargando huevos...
                                </div>
                            </template>

                            <!-- Error / módulo no disponible -->
                            <template x-if="!huevosLoading && huevosError">
                                <div class="text-center py-8">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-3" x-text="huevosError"></p>
                                    <button
                                        @click="cargarHuevos(true)"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
                                    >
                                        Reintentar
                                    </button>
                                </div>
                            </template>

                            <template x-if="!huevosLoading && !huevosError && huevosLoaded">
                                <div class="space-y-6">
                                <!-- Mis huevos -->
                                <div>
                                    <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2 mb-4">
                                        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-700 dark:text-gray-300">Mis Huevos</h2>
                                    </div>
                                    <template x-if="huevos.length === 0">
                                        <div class="text-center py-6 text-sm text-gray-400 dark:text-gray-500">No tienes huevos en este momento.</div>
                                    </template>
                                    <template x-if="huevos.length > 0">
                                        <div class="space-y-3">
                                            <template x-for="huevo in huevos" :key="'huevo-'+huevo.id">
                                                <div class="flex items-center gap-3 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-3">
                                                    <div class="w-12 h-12 shrink-0 flex items-center justify-center">
                                                        <img
                                                            :src="huevo.pokemon?.icon ? huevo.pokemon.icon : '/images/iconos_webp/' + (huevo.pokemon?.id ?? 0) + '.webp'"
                                                            :alt="huevo.pokemon?.name ?? 'Huevo'"
                                                            class="w-full h-full object-contain"
                                                            loading="lazy"
                                                            onerror="this.style.display='none'"
                                                        >
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2 flex-wrap">
                                                            <span class="text-sm font-medium text-gray-900 dark:text-white capitalize truncate" x-text="huevo.pokemon?.name ?? 'Pokémon por descubrir'"></span>
                                                            <span class="px-1.5 py-0.5 text-[10px] font-bold rounded uppercase" :class="estadoHuevo(huevo).clase" x-text="estadoHuevo(huevo).texto"></span>
                                                        </div>
                                                        <div class="flex items-center gap-2 mt-1.5">
                                                            <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                                                <div class="h-full bg-purple-500 rounded-full" :style="'width: ' + huevoProgreso(huevo) + '%'"></div>
                                                            </div>
                                                            <span class="text-[10px] text-gray-500 dark:text-gray-400 flex-shrink-0" x-text="huevoEtiquetaRestante(huevo)"></span>
                                                        </div>
                                                    </div>
                                                    <template x-if="huevo.puede_eclosionar">
                                                        <button
                                                            @click="eclosionarHuevo(huevo)"
                                                            :disabled="huevosActuando"
                                                            class="shrink-0 px-3 py-1.5 bg-purple-600 text-white rounded-lg text-xs font-bold hover:bg-purple-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
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
                                    <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2 mb-4">
                                        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-700 dark:text-gray-300">Familias Disponibles</h2>
                                    </div>
                                    <template x-if="huevosDisponibles.length === 0">
                                        <div class="text-center py-6 text-sm text-gray-400 dark:text-gray-500">No hay huevos disponibles para incubar.</div>
                                    </template>
                                    <template x-if="huevosDisponibles.length > 0">
                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                            <template x-for="op in huevosDisponibles" :key="'disp-'+op.evolution_chain_id">
                                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 text-center p-3">
                                                    <div class="w-16 h-16 mx-auto">
                                                        <img
                                                            :src="op.pokemon?.icon ? op.pokemon.icon : '/images/iconos_webp/' + (op.pokemon?.id ?? 0) + '.webp'"
                                                            :alt="op.pokemon?.name ?? 'Pokémon'"
                                                            class="w-full h-full object-contain"
                                                            loading="lazy"
                                                            onerror="this.style.display='none'"
                                                        >
                                                    </div>
                                                    <p class="text-[10px] text-gray-600 dark:text-gray-400 truncate mt-1 capitalize" x-text="op.pokemon?.name ?? 'Pokémon'"></p>
                                                    <p class="text-[10px] text-gray-400 dark:text-gray-500" x-text="'Captura ' + (op.capture_rate ?? '—')"></p>
                                                    <button
                                                        @click="ponerHuevo(op.evolution_chain_id)"
                                                        :disabled="huevosActuando || !puedePonerHuevo()"
                                                        :title="!puedePonerHuevo() ? limiteMensaje() : 'Poner a gestar este huevo'"
                                                        class="mt-2 w-full px-2 py-1.5 bg-green-600 text-white rounded-lg text-xs font-bold hover:bg-green-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
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
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeExploracionModal()">
            <div class="absolute inset-0 bg-black/60" @click="closeExploracionModal()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Enviar a explorar</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                    Enviar a <strong class="text-gray-900 dark:text-white capitalize" x-text="exploracionPokemon ? nombreDe(exploracionPokemon) : ''"></strong> (Nv <span x-text="exploracionPokemon ? nivelDe(exploracionPokemon) : ''"></span>)
                </p>

                <!-- Habitat selector -->
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Hábitat de destino</label>
                    <template x-if="habitatsLoading">
                        <p class="text-sm text-gray-400 dark:text-gray-500 flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Cargando hábitats...
                        </p>
                    </template>
                    <template x-if="habitatsError">
                        <p class="text-sm text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg px-3 py-2" role="alert">
                            ⚠️ <span x-text="habitatsError"></span>
                        </p>
                    </template>
                    <template x-if="!habitatsLoading && !habitatsError && habitats.length > 0">
                        <select
                            x-model="exploracionHabitatId"
                            class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nivel de exploración</label>
                    <div class="flex gap-2">
                        <template x-for="lvl in [1, 2, 3]" :key="lvl">
                            <button
                                @click="exploracionLevel = lvl"
                                class="flex-1 px-3 py-2 rounded-lg border-2 text-sm font-medium transition-all"
                                :class="exploracionLevel === lvl
                                    ? 'border-blue-500 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400'
                                    : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'"
                                x-text="'Nv ' + lvl"
                            ></button>
                        </template>
                    </div>
                </div>

                <!-- Duration options -->
                <div class="space-y-3 mb-4">
                    <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                        :class="{ 'border-blue-500 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20': durationMode === 'hours' }"
                    >
                        <input type="radio" x-model="durationMode" value="hours" class="w-4 h-4 text-blue-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Duración: </span>
                        <input
                            type="number"
                            x-model.number="durationHours"
                            min="1"
                            max="72"
                            :disabled="durationMode !== 'hours'"
                            class="w-16 px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-900 text-gray-900 dark:text-white disabled:opacity-50"
                            @click.stop
                        >
                        <span class="text-sm text-gray-500 dark:text-gray-400">horas</span>
                    </label>

                    <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                        :class="{ 'border-blue-500 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20': durationMode === 'return_time' }"
                    >
                        <input type="radio" x-model="durationMode" value="return_time" class="w-4 h-4 text-blue-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Regresar antes de las </span>
                        <input
                            type="time"
                            x-model="returnTime"
                            :disabled="durationMode !== 'return_time'"
                            class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-900 text-gray-900 dark:text-white disabled:opacity-50"
                            @click.stop
                        >
                    </label>
                </div>

                <!-- Preview (capacidades individuales) -->
                <div class="mb-4">
                    <div x-show="previewLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Calculando preparación de la expedición...
                    </div>
                    <div x-show="previewError" x-cloak role="alert" class="text-sm text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg px-3 py-2">
                        <span x-text="previewError"></span>
                    </div>
                    <template x-if="previewLoaded && preview">
                        <div class="space-y-3 bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Riesgo</span>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full uppercase" :class="riesgoClass(preview.riesgo)" x-text="preview.riesgo"></span>
                            </div>
                            <template x-if="preview.capacidades">
                                <div>
                                    <p class="text-xs font-medium text-gray-600 dark:text-gray-300 mb-1.5">Capacidades del Pokémon</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <template x-for="(valor, key) in preview.capacidades" :key="key">
                                            <div class="flex items-center justify-between px-2 py-1 bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700">
                                                <span class="text-[10px] text-gray-500 dark:text-gray-400 capitalize" x-text="key"></span>
                                                <span class="text-[10px] font-medium text-gray-700 dark:text-gray-300" x-text="Math.round(valor * 100) / 100"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <template x-if="preview.min_lvl">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Nivel mínimo requerido</span>
                                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Nv <span x-text="preview.min_lvl"></span></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3">
                    <button
                        @click="closeExploracionModal()"
                        class="flex-1 px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="confirmExploracion()"
                        :disabled="!exploracionHabitatId || sendingExploracion"
                        class="flex-1 px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-bold hover:bg-green-700 transition-colors disabled:bg-gray-300 dark:disabled:bg-gray-700 disabled:text-gray-500 dark:disabled:text-gray-500 disabled:cursor-not-allowed"
                    >
                        <span x-text="sendingExploracion ? 'Enviando…' : 'Enviar expedición'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

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

    <!-- Pokemon Detail Modal (shared) -->
    <template x-if="showDetailModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeDetail()" role="dialog" aria-modal="true" aria-labelledby="detail-modal-title">
            <div class="absolute inset-0 bg-black/60" @click="closeDetail()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="flex items-start gap-4 p-6 border-b border-gray-100 dark:border-gray-700">
                    <div class="w-24 h-24 shrink-0 rounded-lg bg-gray-50 dark:bg-gray-900/50 flex items-center justify-center overflow-hidden">
                        <img
                            :src="iconoPrincipalDe(detailPokemon)"
                            loading="lazy"
                            decoding="async"
                            :alt="nombreDe(detailPokemon)"
                            class="w-full h-full object-contain"
                            :data-pid="detailPokemon.pokemon_id"
                            :data-intento="detailPokemon.es_shiny ? 'shiny' : 'normal'"
                            onerror="this.onerror=null; var p=this.getAttribute('data-pid'); var i=this.getAttribute('data-intento'); if (!p) { this.style.display='none'; return; } if (i === 'shiny') { this.src='/images/iconos_webp/shiny/'+p+'_s.webp'; this.setAttribute('data-intento','shiny_s'); } else if (i === 'shiny_s') { this.src='/images/iconos_webp/'+p+'.webp'; this.setAttribute('data-intento','normal'); } else { this.style.display='none'; }"
                        >
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 id="detail-modal-title" class="text-lg font-bold text-gray-900 dark:text-white capitalize truncate" x-text="nombreDe(detailPokemon)"></h3>
                            <template x-if="detailPokemon.es_shiny">
                                <span class="px-1.5 py-0.5 bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-400 text-[10px] font-bold rounded uppercase" title="Shiny">★ Shiny</span>
                            </template>
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500" x-text="detailPokemon.pokemon_id ? '#' + detailPokemon.pokemon_id : ''"></p>
                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                            <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-400 text-xs font-bold rounded-full">Nv <span x-text="nivelDe(detailPokemon)"></span></span>
                            <template x-if="cpDe(detailPokemon) != null">
                                <span class="px-2 py-0.5 bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 text-xs font-bold rounded-full" title="Poder de combate">CP <span x-text="cpDe(detailPokemon)"></span></span>
                            </template>
                            <span class="text-xs text-gray-500 dark:text-gray-400" x-text="formatExp(expTotalDe(detailPokemon)) + ' exp'"></span>
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

                    <!-- Habilidades -->
                    <template x-if="habilidadesDe(detailPokemon).length > 0">
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Habilidades</h4>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="(hab, index) in habilidadesDe(detailPokemon)" :key="index">
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 capitalize" x-text="hab"></span>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- IVs -->
                    <template x-if="detailPokemon && (detailPokemon.ivs || detailPokemon.iv)">
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Valores Individuales (IV)</h4>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1.5">
                                <template x-for="stat in evStats" :key="stat.key">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] text-gray-500 dark:text-gray-400" x-text="stat.label"></span>
                                        <span class="text-xs font-bold" :class="ivValue(detailPokemon, stat.key) >= 15 ? 'text-green-600 dark:text-green-400' : 'text-gray-700 dark:text-gray-300'" x-text="ivValue(detailPokemon, stat.key) + '/15'"></span>
                                    </div>
                                </template>
                            </div>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Los IV perfectos (15) se muestran en verde.</p>
                        </div>
                    </template>

                    <!-- EVs -->
                    <template x-if="detailPokemon && (detailPokemon.evs || detailPokemon.ev)">
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Esfuerzo (EV)</h4>
                            <template x-if="detailEvFeedback.mensaje">
                                <div class="px-3 py-2 rounded-lg text-xs mb-2" role="status"
                                    :class="detailEvFeedback.tipo === 'error'
                                        ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'
                                        : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'"
                                    x-text="detailEvFeedback.mensaje">
                                </div>
                            </template>
                            <div class="space-y-2">
                                <template x-for="stat in evStats" :key="'ev-'+stat.key">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] text-gray-500 dark:text-gray-400 w-20 text-right truncate" x-text="stat.label"></span>
                                        <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div class="h-full bg-amber-500 rounded-full" :style="'width: ' + evPct(evValue(detailPokemon, stat.key)) + '%'"></div>
                                        </div>
                                        <span class="text-[10px] font-medium text-gray-700 dark:text-gray-300 w-8 flex-shrink-0" x-text="evValue(detailPokemon, stat.key) + '/255'"></span>
                                        <button
                                            @click="darEv(stat.key)"
                                            :disabled="!puedeDarEv(detailPokemon, stat.key)"
                                            :title="!puedeDarEv(detailPokemon, stat.key) ? 'No puedes dar caramelos EV ahora mismo' : 'Dar caramelo EV (+10 a ' + stat.label + ')'"
                                            class="shrink-0 px-2 py-1 bg-amber-500 text-white rounded-md text-[10px] font-bold hover:bg-amber-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                        >
                                            🍬 <span x-text="evCandyLabel(detailPokemon, stat.key)"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Locked while exploring (individual or team) -->
                    <template x-if="detailPokemon && (enExploracion(detailPokemon.id) || pokemonEnExploracion(detailPokemon))">
                        <div class="px-3 py-2 rounded-lg bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 text-xs" role="status">
                            Este Pokémon está en exploración activa: no puedes evolucionarlo ni liberarlo hasta que vuelva.
                        </div>
                    </template>

                    <!-- Evolution / release actions -->
                    <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Evolución</h4>

                        <!-- Loading -->
                        <template x-if="detailEvolucionesLoading">
                            <div class="text-sm text-gray-400 dark:text-gray-500 py-2" role="status">Cargando opciones de evolución…</div>
                        </template>

                        <!-- Error sin información -->
                        <template x-if="!detailEvolucionesLoading && detailEvolucionesError">
                            <div class="text-sm text-gray-400 dark:text-gray-500 py-2">No hay información de evolución disponible.</div>
                        </template>

                        <!-- Sin evolución -->
                        <template x-if="!detailEvolucionesLoading && !detailEvolucionesError && detailEvoluciones.length === 0">
                            <div class="text-sm text-gray-400 dark:text-gray-500 py-2">Este Pokémon no tiene evolución.</div>
                        </template>

                        <template x-if="!detailEvolucionesLoading && !detailEvolucionesError && detailEvoluciones.length > 0">
                            <div class="space-y-4">
                                <!-- Target selector (solo si hay varias opciones) -->
                                <template x-if="detailEvoluciones.length > 1">
                                    <div>
                                        <p class="text-[10px] text-gray-500 dark:text-gray-400 mb-2">Selecciona a qué Pokémon evolucionar</p>
                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                            <template x-for="opcion in detailEvoluciones" :key="opcion.pokemon_id">
                                                <button
                                                    @click="selectedEvolucionId = opcion.pokemon_id"
                                                    class="flex flex-col items-center gap-1 p-2 rounded-lg border-2 transition-colors"
                                                    :class="selectedEvolucionId === opcion.pokemon_id
                                                        ? 'border-blue-500 dark:border-blue-400 ring-2 ring-blue-500/30'
                                                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                                    :aria-pressed="selectedEvolucionId === opcion.pokemon_id"
                                                    :aria-label="'Evolucionar a ' + opcion.nombre"
                                                >
                                                    <img
                                                        :src="opcion.imagen || '/images/iconos_webp/' + opcion.pokemon_id + '.webp'"
                                                        loading="lazy"
                                                        decoding="async"
                                                        :alt="opcion.nombre"
                                                        class="w-12 h-12 object-contain"
                                                        onerror="this.style.display='none'"
                                                    >
                                                    <span class="text-[10px] text-gray-700 dark:text-gray-300 capitalize truncate" x-text="opcion.nombre"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <!-- Exp bars toward evolution for selected option -->
                                <template x-if="opcionSeleccionada()">
                                    <div class="space-y-2.5">
                                        <template x-for="req in opcionSeleccionada().requisitos" :key="req.slug">
                                            <div>
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="text-[10px] text-gray-500 dark:text-gray-400" x-text="req.tipo"></span>
                                                    <span class="text-[10px] text-gray-400 dark:text-gray-500" x-text="formatExp(req.actual) + ' / ' + formatExp(req.necesario)"></span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden" role="progressbar" :aria-valuenow="porcentajeExpRequisito(req)" aria-valuemin="0" aria-valuemax="100">
                                                        <div class="h-full bg-blue-500 rounded-full transition-all duration-500" :style="'width: ' + porcentajeExpRequisito(req) + '%'"></div>
                                                    </div>
                                                    <button
                                                        @click="alimentarCaramelo(req)"
                                                        :disabled="!puedeAlimentar(req)"
                                                        :title="'Usar caramelo de ' + req.tipo + ' (+100 exp)'"
                                                        :aria-label="'Usar caramelo de ' + req.tipo + ' (+100 exp)'"
                                                        class="shrink-0 flex items-center gap-1 px-1.5 py-0.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                                    >
                                                        <img
                                                            :src="'/images/candy_type/' + req.slug + '.webp'"
                                                            loading="lazy"
                                                            decoding="async"
                                                            :alt="'Caramelo de ' + req.tipo"
                                                            class="w-4 h-4 object-contain"
                                                            onerror="this.onerror=null; this.src='/images/candy_pokemon/0.webp';"
                                                        >
                                                        <span class="text-[10px] font-medium text-gray-600 dark:text-gray-300" x-text="'× ' + req.caramelosDisponibles"></span>
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <!-- Actions -->
                                <div class="flex flex-col sm:flex-row gap-2 border-t border-gray-100 dark:border-gray-700 pt-3">
                                    <button
                                        @click="evolvePokemon()"
                                        :disabled="detailLoading || detailPokemon && (enExploracion(detailPokemon.id) || pokemonEnExploracion(detailPokemon)) || !opcionSeleccionada()?.puede_evolucionar"
                                        :title="(detailPokemon && (enExploracion(detailPokemon.id) || pokemonEnExploracion(detailPokemon)))
                                            ? 'El Pokémon está en exploración'
                                            : (opcionSeleccionada()?.puede_evolucionar ? 'Evolucionar Pokémon' : 'Completa la exp de tipo para evolucionar')"
                                        class="flex-1 px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-bold hover:bg-green-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                    >
                                        <span x-text="detailLoading ? 'Evolucionando…' : 'Evolucionar'"></span>
                                    </button>
                                    <button
                                        @click="releasePokemon()"
                                        :disabled="detailLoading || detailPokemon && (enExploracion(detailPokemon.id) || pokemonEnExploracion(detailPokemon))"
                                        :title="detailPokemon && (enExploracion(detailPokemon.id) || pokemonEnExploracion(detailPokemon)) ? 'El Pokémon está en exploración' : 'Liberar Pokémon'"
                                        class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-bold hover:bg-red-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                    >
                                        Liberar
                                    </button>
                                </div>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">
                                    Usa caramelos de tipo para llenar la barra y poder evolucionar.
                                </p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

@push('scripts')
<script>
    window.addEventListener('error', (e) => console.error('[equipos:trace] window error:', e.message, e.filename + ':' + e.lineno, e.error || ''));
    window.addEventListener('unhandledrejection', (e) => console.error('[equipos:trace] unhandled rejection:', e.reason));
    document.addEventListener('alpine:init', () => console.log('[equipos:trace] alpine:init'));
    document.addEventListener('alpine:initialized', () => console.log('[equipos:trace] alpine:initialized'));
    window.addEventListener('load', () => {
        console.log('[equipos:trace] load — Alpine:', typeof window.Alpine, '| Livewire:', typeof window.Livewire, '| x-data presente:', !!document.querySelector('[x-data]'));
        setTimeout(() => {
            try {
                const gridAudit = (titulo) => {
                    const h = [...document.querySelectorAll('h2')].find(x => x.textContent.includes(titulo));
                    if (!h) return { encontrado: false };
                    const grid = h.closest('div').nextElementSibling;
                    const imgs = [...grid.querySelectorAll('img')];
                    return {
                        encontrado: true,
                        hijos: grid.children.length,
                        texto: (grid.textContent || '').trim().slice(0, 140),
                        imgs: imgs.length,
                        imgsCargadas: imgs.filter(i => i.naturalWidth > 0).length,
                        imgsOcultas: imgs.filter(i => getComputedStyle(i).display === 'none').length,
                        alto: grid.offsetHeight,
                        display: getComputedStyle(grid).display,
                    };
                };
                console.log('[equipos:trace] DOM audit', {
                    disponibles: gridAudit('Reclutados Disponibles'),
                    asignados: gridAudit('Asignados'),
                    imgsEquipos: document.querySelectorAll('.grid-cols-3 img').length,
                    imgsPagina: document.images.length,
                });
            } catch (e) {
                console.error('[equipos:trace] DOM audit error', e);
            }
        }, 2000);
    });
</script>
<script>
function favoritosApp() {
    return {
        // ─── Datos del servidor ─────────────────────────────────────────────
        reclutados: @json($reclutados ?? []),
        reclutadosEnExploracion: @json($reclutadosEnExploracion ?? []),
        teams: @json($teams ?? []),
        teamPokemonIds: @json($teamIds ?? []),
        equiposEnExploracion: @json($equiposEnExploracion ?? []),

        // ─── Formación persistente (combate 3v3) ───────────────────────────
        // Draft local por equipo: {teamId: {slot: 'vanguardia'|'retaguardia'}}.
        // La formación persistida del backend (team.formacion) recién se conoce
        // tras el primer PATCH; el draft tiene prioridad sobre ella.
        formacionDraft: {},
        formacionSavingTeamId: null,
        formacionSaveError: '',
        formacionSaveSuccess: '',

        // ─── UI: Equipos ───────────────────────────────────────────────────
        // ─── UI: Buscar/filtro ─────────────────────────────────────────────
        searchQuery: '',
        typeFilter: null,
        showTypeFilter: false,

        selectedTeamId: null,
        showNewTeamForm: false,
        newTeamName: '',
        showDeleteModal: false,
        teamToDelete: null,

        // ─── Detail Modal (shared) ─────────────────────────────────────────
        showDetailModal: false,
        detailPokemon: null,
        detailLoading: false,
        detailEvoluciones: [],
        detailEvolucionesLoading: false,
        detailEvolucionesError: false,
        selectedEvolucionId: null,

        // ─── Detail: EV feeding (caramelos EV / IVs) ─────────────────────────
        evStats: [
            { key: 'hp', label: 'PS' },
            { key: 'atk', label: 'Ataque' },
            { key: 'def', label: 'Defensa' },
            { key: 'spAtk', label: 'Ataque Esp.' },
            { key: 'spDef', label: 'Defensa Esp.' },
            { key: 'speed', label: 'Velocidad' },
        ],
        detailEvsFeeding: false,
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

        /** Equipos tab: reclutados not assigned to any team */
        get availablePokemonsTeams() {
            let result = this.reclutados.filter(r => !this.teamPokemonIds.includes(r.id));
            if (this.searchQuery.trim()) {
                const q = this.searchQuery.toLowerCase().trim();
                result = result.filter(r =>
                    String(r.nombre || '').toLowerCase().includes(q) ||
                    String(r.pokemon_id || '').includes(q)
                );
            }
            if (this.typeFilter) {
                result = result.filter(r =>
                    r.pokemon && r.pokemon.types &&
                    Array.isArray(r.pokemon.types) &&
                    r.pokemon.types.some(t => t.tipo_nombre === this.typeFilter)
                );
            }
            return result;
        },

        /** Equipos tab: reclutados already assigned to a team */
        get assignedPokemons() {
            let result = this.reclutados.filter(r => this.teamPokemonIds.includes(r.id));
            if (this.searchQuery.trim()) {
                const q = this.searchQuery.toLowerCase().trim();
                result = result.filter(r =>
                    String(r.nombre || '').toLowerCase().includes(q) ||
                    String(r.pokemon_id || '').includes(q)
                );
            }
            return result;
        },

        // ═══════════════════════════════════════════════════════════════════════
        // INIT
        // ═══════════════════════════════════════════════════════════════════════

        init() {
            try {
                const baseSinAsignar = Array.isArray(this.reclutados)
                    ? this.reclutados.filter(r => !this.teamPokemonIds.includes(r.id))
                    : null;
                console.log('[equipos:trace] init()', {
                    reclutadosTipo: Array.isArray(this.reclutados) ? 'array' : typeof this.reclutados,
                    reclutadosTotal: Array.isArray(this.reclutados) ? this.reclutados.length : null,
                    reclutadosIds: Array.isArray(this.reclutados) ? this.reclutados.map(r => r.id) : null,
                    teamPokemonIds: this.teamPokemonIds,
                    teams: this.teams.map(t => ({
                        id: t.id,
                        name: t.name,
                        members: t.members ? t.members.length : null,
                        slots: t.members ? t.members.map(m => m.slot) : null,
                        pokemonIds: t.members ? t.members.map(m => m.pokemon_id) : null,
                        formacion: t.formacion,
                    })),
                    searchQuery: this.searchQuery,
                    typeFilter: this.typeFilter,
                    disponiblesSinFiltros: baseSinAsignar ? baseSinAsignar.length : null,
                    disponibles: this.availablePokemonsTeams.length,
                    asignados: this.assignedPokemons.length,
                });
            } catch (traceError) {
                console.error('[equipos:trace] init() error', traceError);
            }
            document.addEventListener('click', (e) => {
                if (!e.target.closest('[x-data]')?.contains(e.target)) {
                    this.showTypeFilter = false;
                }
            });
        },

        // ═══════════════════════════════════════════════════════════════════════
        // SHARED HELPERS
        // ═══════════════════════════════════════════════════════════════════════

        enExploracion(reclutadoId) {
            return this.reclutadosEnExploracion.includes(reclutadoId);
        },

        isInExploration(teamId) {
            return this.equiposEnExploracion.some(e =>
                typeof e === 'object' && e !== null ? e.equipo_id === teamId : e === teamId
            );
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

        /** URL del icono WebP; devuelve string vacío si pokemon_id ausente para no romper la UI. */
        iconoDe(pokemon) {
            if (!pokemon || !pokemon.pokemon_id) return '';
            return '/images/iconos_webp/' + pokemon.pokemon_id + '.webp';
        },

        /** URL del icono WebP (shiny first, normal fallback handled by onerror in template). */
        iconoPrincipalDe(pokemon) {
            if (!pokemon || !pokemon.pokemon_id) return '';
            if (pokemon.es_shiny) {
                return '/images/iconos_webp/shiny/' + pokemon.pokemon_id + '.webp';
            }
            return '/images/iconos_webp/' + pokemon.pokemon_id + '.webp';
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

        riesgoClass(riesgo) {
            const clases = {
                'Bajo': 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                'Medio': 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400',
                'Alto': 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400',
                'Extremo': 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
            };
            return clases[riesgo] || 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400';
        },

        // ═══════════════════════════════════════════════════════════════════════
        // DETAIL MODAL (shared)
        // ═══════════════════════════════════════════════════════════════════════

        openDetail(pokemon) {
            this.detailPokemon = pokemon;
            this.detailLoading = false;
            this.showDetailModal = true;
            this.cargarEvoluciones(pokemon);
        },

        closeDetail() {
            this.showDetailModal = false;
            this.detailPokemon = null;
            this.detailLoading = false;
            this.detailEvoluciones = [];
            this.detailEvolucionesLoading = false;
            this.detailEvolucionesError = false;
            this.selectedEvolucionId = null;
            this.detailEvFeedback = { tipo: '', mensaje: '' };
            this.detailEvsFeeding = false;
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
            const mapa = pokemon?.caramelos_ev;
            if (!mapa) return null;
            return mapa[key] ?? mapa[this.slugCorto(key)] ?? 0;
        },

        evCandyLabel(pokemon, key) {
            const n = this.evCaramelosDe(pokemon, key);
            return n === null ? '—' : 'x' + n;
        },

        puedeDarEv(pokemon, key) {
            if (!pokemon || this._pokemonBloqueado(pokemon)) return false;
            if (this.detailEvsFeeding) return false;
            if (this.evValue(pokemon, key) >= 255) return false;
            const caramelos = this.evCaramelosDe(pokemon, key);
            if (caramelos !== null && caramelos <= 0) return false;
            return true;
        },

        async darEv(key) {
            const pokemon = this.detailPokemon;
            if (!pokemon || !this.puedeDarEv(pokemon, key)) return;
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
                    body: JSON.stringify({ stat: key }),
                });
                if (response.ok) {
                    const data = await response.json();
                    const nivelado = data.evs ?? data.ev ?? (data.actual !== undefined ? { [key]: data.actual } : null);
                    if (nivelado && typeof nivelado === 'object') {
                        const evs = { ...this.evsDe(pokemon), ...nivelado };
                        pokemon.evs = evs;
                    }
                    if (data.caramelos_ev && typeof data.caramelos_ev === 'object') {
                        pokemon.caramelos_ev = data.caramelos_ev;
                    }
                    const label = (this.evStats.find(s => s.key === key) || {}).label || key;
                    this.detailEvFeedback = { tipo: 'success', mensaje: '✓ ¡+10 EV para ' + label + '!' };
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
            // Contrato nuevo: si el backend expone progreso 0-100, usarlo directamente.
            if (typeof huevo?.progreso === 'number') {
                return Math.max(0, Math.min(100, huevo.progreso));
            }
            // Fallback: ambos campos en horas (tiempo_total_horas + restante en horas).
            const total = Number(huevo?.tiempo_total_horas || 0);
            const restante = Number(huevo?.tiempo_restante_horas ?? huevo?.tiempo_restante ?? total);
            if (total <= 0) return 0;
            return Math.max(0, Math.min(100, ((total - restante) / total) * 100));
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
                gestando: { texto: 'Gestando', clase: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400' },
                incubando: { texto: 'Incubando', clase: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-400' },
                listo: { texto: '¡Listo!', clase: 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400' },
            };
            return mapa[huevo?.estado] || { texto: huevo?.estado || 'Huevo', clase: 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' };
        },

        // ═══════════════════════════════════════════════════════════════════════
        // EQUIPOS LOGIC
        // ═══════════════════════════════════════════════════════════════════════

        getMember(team, slot) {
            const member = team.members.find(m => m.slot === slot);
            return member ? member.reclutado : null;
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
                    clase: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                };
            }
            return {
                texto: 'Composición neutra',
                clase: 'bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300',
            };
        },

        pokemonTeamId(pokemon) {
            if (!pokemon) return null;
            const team = this.teams.find(t => t.members.some(m => m.pokemon_id === pokemon.id));
            return team ? team.id : null;
        },

        pokemonEnExploracion(pokemon) {
            const teamId = this.pokemonTeamId(pokemon);
            return teamId !== null && this.isInExploration(teamId);
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
                        const t = this.teams.find(t => t.id === data.member.team_id);
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
                    team.members = team.members.filter(m => m.id !== member.id);
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
            if (this.isInExploration(team.id)) return;
            if (!this.formacionDraft[team.id]) this.formacionDraft[team.id] = {};
            this.formacionDraft[team.id][slot] = posicion;
            this.formacionSaveError = '';
            this.formacionSaveSuccess = '';
        },

        async guardarFormacion(team) {
            if (this.isInExploration(team.id)) {
                alert('No se puede modificar un equipo con exploraciones activas');
                return;
            }
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

        async alimentarCaramelo(requisito) {
            const pokemon = this.detailPokemon;
            if (!pokemon || !requisito || !this.puedeAlimentar(requisito)) return;
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
