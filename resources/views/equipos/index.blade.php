@extends('layouts.app')

@section('title', 'Favoritos')

@section('content')
@php
    $tipos = \App\Enums\TipoEnum::options();
@endphp
<div x-data="favoritosApp()" x-init="init()">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Pokémon Favoritos</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Marca tus Pokémon favoritos y envíalos a explorar</p>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Left 1/3: Favoritos List -->
        <div class="space-y-4">
            <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2">
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-700 dark:text-gray-300">Mis Favoritos</h2>
                <span class="px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-xs font-bold rounded-full" x-text="favoritos.length"></span>
            </div>

            <div class="space-y-3">
                <template x-for="pokemon in favoritos" :key="pokemon.id">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden group relative">
                        <button @click="openDetail(pokemon)" class="w-full p-3 flex items-center gap-3 text-left">
                            <div class="w-16 h-16 shrink-0 bg-gray-50 dark:bg-gray-900/50 rounded-lg flex items-center justify-center overflow-hidden">
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
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white capitalize truncate" x-text="pokemon.nombre"></p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-400 text-[10px] font-bold rounded-full">Nv <span x-text="nivelDe(pokemon)"></span></span>
                                    <template x-if="enExploracion(pokemon.id)">
                                        <span class="px-1.5 py-0.5 bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 text-[10px] font-bold rounded uppercase">En exploración</span>
                                    </template>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-yellow-500 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                        </button>
                        <!-- Hover action: send to explore -->
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                            <button
                                @click="openExploracionModal(pokemon)"
                                :disabled="enExploracion(pokemon.id)"
                                class="w-full px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs font-bold hover:bg-green-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed pointer-events-auto"
                                :title="enExploracion(pokemon.id) ? 'Este Pokémon está en exploración activa' : 'Enviar a explorar'"
                                x-text="enExploracion(pokemon.id) ? 'En exploración' : 'Enviar a explorar'"
                            ></button>
                        </div>
                    </div>
                </template>

                <!-- Empty favorites -->
                <template x-if="favoritos.length === 0">
                    <div class="text-center py-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                        <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">No tienes Pokémon favoritos</p>
                        <button
                            @click="openFavoritosModal()"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
                        >
                            Gestionar favoritos
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <!-- Right 2/3: Available Pokemon (not favorites) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Manage button -->
            <div class="flex justify-end">
                <button
                    @click="openFavoritosModal()"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                    Gestionar favoritos
                </button>
            </div>

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

            <!-- Available Pokemon Grid -->
            <div>
                <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2 mb-4">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-gray-700 dark:text-gray-300">Reclutados Disponibles</h2>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-2">
                    <template x-for="pokemon in availablePokemons" :key="pokemon.id">
                        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 text-center group">
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
                                <button
                                    @click="toggleFavorito(pokemon)"
                                    :disabled="togglingFavoritoId === pokemon.id"
                                    class="w-8 h-8 bg-yellow-500 text-white rounded-full flex items-center justify-center hover:bg-yellow-600 transition-colors text-lg font-bold"
                                    :aria-label="'Marcar ' + pokemon.nombre + ' como favorito'"
                                    :title="'Marcar ' + pokemon.nombre + ' como favorito'"
                                >
                                    ★
                                </button>
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
                            <p class="text-sm text-gray-400 dark:text-gray-500" x-text="searchQuery || typeFilter ? 'No se encontraron Pokémon' : 'No hay Pokémon disponibles para marcar como favoritos'"></p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Favorites Management Modal -->
    <template x-if="showFavoritosModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeFavoritosModal()">
            <div class="absolute inset-0 bg-black/60" @click="closeFavoritosModal()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Gestionar Favoritos</h3>
                    <button @click="closeFavoritosModal()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400" aria-label="Cerrar">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <!-- Content: scrollable grid of all reclutados (not in exploration), each with star toggle -->
                <div class="flex-1 overflow-y-auto p-6">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        <template x-for="pokemon in allGestionables" :key="pokemon.id">
                            <div
                                class="relative bg-white dark:bg-gray-800 rounded-lg shadow-sm border text-center p-2 transition-all cursor-pointer"
                                :class="pokemon.favorito
                                    ? 'border-yellow-500 dark:border-yellow-400 ring-1 ring-yellow-500/30'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                @click="toggleFavorito(pokemon)"
                            >
                                <div class="w-16 h-16 mx-auto">
                                    <img
                                        :src="'/images/iconos_webp/' + pokemon.pokemon_id + '.webp'"
                                        loading="lazy"
                                        decoding="async"
                                        :alt="pokemon.nombre"
                                        class="w-full h-full object-contain"
                                        onerror="this.style.display='none'"
                                    >
                                </div>
                                <p class="text-[10px] text-gray-600 dark:text-gray-400 truncate mt-1" x-text="pokemon.nombre"></p>
                                <div class="mt-1">
                                    <template x-if="pokemon.favorito">
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-[10px] font-bold rounded-full">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                            Favorito
                                        </span>
                                    </template>
                                    <template x-if="!pokemon.favorito">
                                        <span class="text-[10px] text-gray-400 dark:text-gray-500">Click para marcar</span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                    <template x-if="allGestionables.length === 0">
                        <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                            <p>No hay Pokémon disponibles para gestionar</p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- Exploration Modal (individual) -->
    <template x-if="showExploracionModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeExploracionModal()">
            <div class="absolute inset-0 bg-black/60" @click="closeExploracionModal()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Enviar a explorar</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                    Enviar a <strong class="text-gray-900 dark:text-white capitalize" x-text="exploracionPokemon?.nombre"></strong> (Nv <span x-text="exploracionPokemon ? nivelDe(exploracionPokemon) : ''"></span>)
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

                    <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                        :class="{ 'border-blue-500 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20': durationMode === 'indefinite' }"
                    >
                        <input type="radio" x-model="durationMode" value="indefinite" class="w-4 h-4 text-blue-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Indefinido</span>
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

    <!-- Pokemon Detail Modal (reused from equipos) -->
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
                    <template x-if="detailPokemon && enExploracion(detailPokemon.id)">
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
                                        :disabled="detailLoading || (detailPokemon && enExploracion(detailPokemon.id)) || !opcionSeleccionada()?.puede_evolucionar"
                                        :title="detailPokemon && enExploracion(detailPokemon.id)
                                            ? 'El Pokémon está en exploración'
                                            : (opcionSeleccionada()?.puede_evolucionar ? 'Evolucionar Pokémon' : 'Completa la exp de tipo para evolucionar')"
                                        class="flex-1 px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-bold hover:bg-green-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                    >
                                        <span x-text="detailLoading ? 'Evolucionando…' : 'Evolucionar'"></span>
                                    </button>
                                    <button
                                        @click="releasePokemon()"
                                        :disabled="detailLoading || (detailPokemon && enExploracion(detailPokemon.id))"
                                        :title="detailPokemon && enExploracion(detailPokemon.id) ? 'El Pokémon está en exploración' : 'Liberar Pokémon'"
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
function favoritosApp() {
    return {
        // ─── Datos del servidor ─────────────────────────────────────────────
        reclutados: @json($reclutados ?? []),
        reclutadosEnExploracion: @json($reclutadosEnExploracion ?? []),
        // helpers para el modal de detalle (evolution/release)
        teams: @json($teams ?? []),
        teamPokemonIds: @json($teamIds ?? []),
        equiposEnExploracion: @json($equiposEnExploracion ?? []),

        // ─── UI ─────────────────────────────────────────────────────────────
        searchQuery: '',
        typeFilter: null,
        showTypeFilter: false,
        togglingFavoritoId: null,

        // ─── Detail Modal ───────────────────────────────────────────────────
        showDetailModal: false,
        detailPokemon: null,
        detailLoading: false,
        detailEvoluciones: [],
        detailEvolucionesLoading: false,
        detailEvolucionesError: false,
        selectedEvolucionId: null,

        // ─── Favorites Management Modal ─────────────────────────────────────
        showFavoritosModal: false,

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

        // ─── Computed getters ───────────────────────────────────────────────

        get favoritos() {
            return this.reclutados.filter(r => r.favorito === true);
        },

        get noFavoritos() {
            return this.reclutados.filter(r => !r.favorito);
        },

        get allGestionables() {
            return this.reclutados.filter(r => !this.enExploracion(r.id));
        },

        get availablePokemons() {
            let result = this.noFavoritos;

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

        // ─── Init ───────────────────────────────────────────────────────────

        init() {
            // Normalizar favorito (tolerante: si el backend no lo envía, false)
            this.reclutados.forEach(r => {
                if (typeof r.favorito !== 'boolean') r.favorito = false;
            });

            // Close type filter on outside click
            document.addEventListener('click', (e) => {
                if (!e.target.closest('[x-data]')?.contains(e.target)) {
                    this.showTypeFilter = false;
                }
            });
        },

        // ─── Favorito ───────────────────────────────────────────────────────

        enExploracion(reclutadoId) {
            return this.reclutadosEnExploracion.includes(reclutadoId);
        },

        async toggleFavorito(pokemon) {
            if (this.togglingFavoritoId === pokemon.id) return;
            this.togglingFavoritoId = pokemon.id;
            try {
                const response = await fetch('/api/reclutados/' + pokemon.id + '/toggle-favorito', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });
                if (response.ok) {
                    const data = await response.json();
                    pokemon.favorito = !!data.favorito;
                } else {
                    const data = await response.json().catch(() => ({}));
                    console.warn('toggle favorito falló:', data.message || response.status);
                    alert(data.message || 'No se pudo actualizar el favorito.');
                }
            } catch (err) {
                console.error('Error toggling favorito:', err);
                alert('Error de conexión al actualizar el favorito.');
            } finally {
                this.togglingFavoritoId = null;
            }
        },

        openFavoritosModal() {
            this.showFavoritosModal = true;
        },

        closeFavoritosModal() {
            this.showFavoritosModal = false;
        },

        // ─── Detail Modal (reused) ──────────────────────────────────────────

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

        // ─── Acciones: evolucionar / liberar ───────────────────────────────

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

        puedeAlimentar(requisito) {
            if (!requisito) return false;
            if (this.detailPokemon && this.enExploracion(this.detailPokemon.id)) return false;
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
            if (!pokemon || this.enExploracion(pokemon.id)) return;
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
            if (!pokemon || this.enExploracion(pokemon.id)) return;
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

        // ─── Exploration Modal ──────────────────────────────────────────────

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

            // Cargar hábitats si no están cargados
            if (this.habitats.length === 0 && !this.habitatsLoading && !this.habitatsError) {
                await this.loadHabitats();
            }

            // Cargar preview si hay habitat y nivel
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
                if (!response.ok) {
                    throw new Error('No se pudieron cargar los hábitats');
                }
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
            if (!this.exploracionPokemon || !this.exploracionHabitatId || !this.exploracionLevel) {
                return;
            }
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

        riesgoClass(riesgo) {
            const clases = {
                'Bajo': 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                'Medio': 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400',
                'Alto': 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400',
                'Extremo': 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
            };
            return clases[riesgo] || 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400';
        },

        async confirmExploracion() {
            if (!this.exploracionPokemon || !this.exploracionHabitatId || this.sendingExploracion) return;

            // Client-side validation: no enviar si ya está en exploración
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
                } else {
                    body.indefinido = true;
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