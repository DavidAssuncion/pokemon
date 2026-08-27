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
                            <div class="flex items-center justify-between p-3 border-b border-gray-100 dark:border-gray-700">
                                <div class="flex items-center gap-2">
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
                                                        :src="'/images/iconos/' + getMember(team, slot).pokemon_id + '.png'"
                                                        :alt="getMember(team, slot).nombre"
                                                        :title="getMember(team, slot).nombre"
                                                        class="w-32 h-32 object-contain mx-auto"
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
                                                </div>
                                            </template>
                                            <template x-if="!getMember(team, slot)">
                                                <div>
                                                    <div class="w-32 h-32 mx-auto rounded border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center">
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
                    <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Reclutados Disponibles</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-2">
                        <template x-for="pokemon in availablePokemons" :key="pokemon.id">
                            <div
                                class="relative bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden text-center group"
                                x-data="{ showTeamDropdown: false }"
                            >
                                <div class="w-32 h-32 mx-auto">
                                    <img
                                        :src="'/images/iconos/' + pokemon.pokemon_id + '.png'"
                                        :alt="pokemon.nombre"
                                        :title="pokemon.nombre"
                                        class="w-full h-full object-contain"
                                        onerror="this.style.display='none'"
                                    >
                                </div>
                                <p class="text-[10px] text-gray-600 dark:text-gray-400 truncate px-1 pb-1" x-text="pokemon.nombre"></p>
                                <!-- Add button -->
                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <div class="relative">
                                        <button
                                            @click="showTeamDropdown = !showTeamDropdown"
                                            class="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center hover:bg-green-600 transition-colors text-lg font-bold"
                                            :aria-label="'Agregar ' + pokemon.nombre + ' a equipo'"
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
                    <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Asignados</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-2">
                        <template x-for="pokemon in assignedPokemons" :key="pokemon.id">
                            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden text-center group opacity-60">
                                <div class="w-32 h-32 mx-auto">
                                    <img
                                        :src="'/images/iconos/' + pokemon.pokemon_id + '.png'"
                                        :alt="pokemon.nombre"
                                        :title="pokemon.nombre + ' — ' + getTeamName(pokemon.team_id)"
                                        class="w-full h-full object-contain grayscale"
                                        onerror="this.style.display='none'"
                                    >
                                </div>
                                <p class="text-[10px] text-gray-500 dark:text-gray-500 truncate px-1 pb-1" x-text="pokemon.nombre"></p>
                                <!-- Remove button -->
                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <button
                                        @click="removeFromTeam(pokemon)"
                                        class="w-8 h-8 bg-orange-500 text-white rounded-full flex items-center justify-center hover:bg-orange-600 transition-colors text-sm"
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

    <!-- Pokemon Tooltip (hover stats) -->
    <template x-if="hoveredPokemon">
        <div
            class="fixed z-50 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg p-4 w-56 pointer-events-none"
            :style="'left: ' + tooltipX + 'px; top: ' + tooltipY + 'px'"
        >
            <div class="text-center mb-2">
                <img
                    :src="'/images/iconos/' + hoveredPokemon.pokemon_id + '.png'"
                    :alt="hoveredPokemon.nombre"
                    class="w-32 h-32 object-contain mx-auto"
                >
                <p class="text-sm font-medium text-gray-900 dark:text-white mt-1" x-text="hoveredPokemon.nombre"></p>
                <p class="text-xs text-gray-400 dark:text-gray-500" x-text="'#' + hoveredPokemon.pokemon_id"></p>
            </div>
            <template x-if="hoveredPokemon.stats && hoveredPokemon.stats.length > 0">
                <div class="space-y-1">
                    <template x-for="stat in hoveredPokemon.stats" :key="stat.name">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] text-gray-500 dark:text-gray-400 w-14 text-right" x-text="stat.name"></span>
                            <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-500 rounded-full" :style="'width: ' + Math.min((stat.value / 255) * 100, 100) + '%'"></div>
                            </div>
                            <span class="text-[10px] font-medium text-gray-700 dark:text-gray-300 w-6" x-text="stat.value"></span>
                        </div>
                    </template>
                </div>
            </template>
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
        hoveredPokemon: null,
        tooltipX: 0,
        tooltipY: 0,

        init() {
            // Close type filter on outside click
            document.addEventListener('click', (e) => {
                if (!e.target.closest('[x-data]')?.contains(e.target)) {
                    this.showTypeFilter = false;
                }
            });
        },

        isInExploration(teamId) {
            return this.equiposEnExploracion.some(e => e.equipo_id === teamId);
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
                    window.location.reload();
                }
            } catch (err) {
                console.error('Error creating team:', err);
            }

            this.newTeamName = '';
            this.showNewTeamForm = false;
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
                    window.location.reload();
                }
            } catch (err) {
                console.error('Error deleting team:', err);
            }

            this.showDeleteModal = false;
            this.teamToDelete = null;
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
                    window.location.reload();
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
                    window.location.reload();
                }
            } catch (err) {
                console.error('Error removing member:', err);
            }
        },

        removeFromTeam(pokemon) {
            const team = this.teams.find(t => t.id === pokemon.team_id);
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
                }).then(r => {
                    if (r.ok) window.location.reload();
                }).catch(err => console.error('Error renaming:', err));
            }
        },
    };
}
</script>
@endpush
@endsection
