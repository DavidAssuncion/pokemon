@extends('layouts.app')

@section('title', 'Hábitat - ' . ($habitat['name'] ?? ''))

@section('content')
@php
    // Clases shell compartidas (dedup de patrones repetidos en paneles/modal).
    $cardPanelClass = 'bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden';
    $constructionButtonClass = 'w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 transition-colors flex items-center gap-3';
    $familyCardClass = 'bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 text-center';
@endphp
<div x-data="habitatShow()" x-init="init()">
        <!-- Main: Left 1/5 (back/image/construction) + Right 4/5 (explorations/teams/levels) -->
        <div class="grid lg:grid-cols-5 gap-6">
            <!-- Left 1/5: Back, Image (auto size) with title overlay, stacked construction buttons -->
            <div class="lg:col-span-1 space-y-4">
                <!-- Back card (visual) -->
                <a href="/habitats" aria-label="Volver a hábitats" title="Volver a hábitats"
                   class="{{ $cardPanelClass }} p-3 flex items-center gap-3 cursor-pointer transition-all hover:shadow-md hover:border-blue-300 dark:hover:border-blue-600 group">
                    <span class="w-8 shrink-0 flex justify-center">
                        <svg class="w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </span>
                    <span class="flex-1 text-sm font-medium text-gray-700 dark:text-gray-300 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">Volver a hábitats</span>
                </a>

                <!-- Image card with title overlay -->
                <div class="{{ $cardPanelClass }} relative p-3 overflow-hidden w-fit min-h-[6rem] flex items-center justify-center">
                    @if(!empty($habitat['image']))
                    <img
                        src="{{ $habitat['image'] }}"
                        alt="{{ $habitat['name'] }}"
                        class="w-auto h-auto max-w-full rounded-lg"
                        onerror="this.style.display='none'"
                    >
                    @else
                    <div class="w-48 h-32 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                        <span class="text-4xl">🏔️</span>
                    </div>
                    @endif
                    <h1 class="absolute bottom-0 inset-x-0 px-3 py-2 bg-gradient-to-t from-black/70 to-transparent text-white text-sm font-bold truncate rounded-b-lg"
                        title="{{ $habitat['name'] }}">{{ $habitat['name'] }}</h1>
                </div>

                <!-- Construction buttons (stacked, full width) -->
                @php
                    $bloqueadoConstruccion = $exploracionesActivas && $exploracionesActivas->count() > 0;
                @endphp
                <div class="space-y-3">
                    <!-- Favoritos (acceso rápido a la vista /equipos) -->
                    <a
                        href="/equipos"
                        class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 transition-colors flex items-center gap-3 hover:bg-yellow-50 dark:hover:bg-yellow-900/20 hover:border-yellow-300 dark:hover:border-yellow-700"
                    >
                        <span class="w-8 shrink-0 flex justify-center">
                            <svg class="w-6 h-6 text-yellow-500" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                        </span>
                        <span class="flex-1 text-sm text-left font-medium text-gray-700 dark:text-gray-300">Favoritos</span>
                    </a>
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : "alert('Función próximamente')" }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="{{ $constructionButtonClass }} {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                    >
                        <span class="w-8 shrink-0 flex justify-center">
                            <img src="/images/misc/farm.webp" loading="lazy" decoding="async" class="w-8 h-8 object-contain" alt="Granjas">
                        </span>
                        <span class="flex-1 text-sm text-left font-medium text-gray-700 dark:text-gray-300">Granjas</span>
                    </button>
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : "toggleEntrenadores()" }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="{{ $constructionButtonClass }} {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                    >
                        <span class="w-8 shrink-0 flex justify-center">
                            <img src="/images/misc/trainer.webp" loading="lazy" decoding="async" class="w-8 h-8 object-contain" alt="Entrenadores">
                        </span>
                        <span class="flex-1 text-sm text-left font-medium text-gray-700 dark:text-gray-300">Entrenadores</span>
                    </button>
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : "alert('Función próximamente')" }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="{{ $constructionButtonClass }} {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                    >
                        <span class="w-8 shrink-0 flex justify-center">
                            <img src="/images/misc/raid.webp" loading="lazy" decoding="async" class="w-8 h-8 object-contain" alt="Mazmorras">
                        </span>
                        <span class="flex-1 text-sm text-left font-medium text-gray-700 dark:text-gray-300">Mazmorras</span>
                    </button>
                    <button
                        @click="openGestionModal()"
                        class="w-full px-4 py-3 bg-blue-600 text-white rounded-xl text-sm font-bold transition-colors hover:bg-blue-700 flex items-center gap-3 uppercase tracking-wide"
                    >
                        <span class="w-8 shrink-0 flex justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </span>
                        <span class="flex-1 text-sm text-center font-bold uppercase tracking-wide">Admin - Gestion</span>
                    </button>
                </div>
                @if($bloqueadoConstruccion)
                <p class="text-xs text-center text-orange-600 dark:text-orange-400">
                    No disponible durante exploraciones activas
                </p>
                @endif
            </div>

            <!-- Right 4/5: Explorations, Teams, Levels -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Active Explorations List -->
                @if($exploracionesActivas && $exploracionesActivas->count() > 0)
                <div class="{{ $cardPanelClass }}">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6z"/>
                            </svg>
                            Exploraciones activas
                        </h3>
                        <span class="px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-xs font-bold rounded-full">
                            {{ $exploracionesActivas->count() }}
                        </span>
                    </div>
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($exploracionesActivas as $exp)
                        <div class="px-4 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $exp->reclutado->nombre ?? 'Reclutado eliminado' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Nivel {{ $exp->nivel }}</p>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full uppercase
                                {{ $exp->inicio_exploracion
                                    ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400' }}">
                                {{ $exp->inicio_exploracion ? 'En curso' : 'Preparando' }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Favoritos Panel (exploración individual; modo pokémon) -->
                <div class="{{ $cardPanelClass }}" x-show="modo === 'pokemon'" x-cloak>
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between flex-wrap gap-2">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Tus favoritos para este hábitat</h3>
                        <span class="px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-[10px] font-bold rounded-full" x-text="favoritos.length + '/6'"></span>
                    </div>
                    <div class="p-4">
                        <div x-show="favoritosLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Cargando favoritos...
                        </div>
                        <div x-show="favoritosError" x-cloak role="alert" class="text-sm text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg px-3 py-2">
                            <span x-text="favoritosError"></span>
                        </div>
                        <div x-show="!favoritosLoading && !favoritosError && favoritos.length === 0" x-cloak class="text-center py-6">
                            <p class="text-sm text-gray-500 dark:text-gray-400">No tienes Pokémon favoritos para este hábitat</p>
                            <button
                                @click="openGestionFavoritosHabitat()"
                                class="mt-3 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
                            >
                                + Añadir favorito
                            </button>
                        </div>

                        <!-- Aviso preventivo: máx. 6 favoritos por hábitat -->
                        <div x-show="!favoritosLoading && favoritos.length >= 6" x-cloak role="status"
                             class="mb-3 px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs text-amber-700 dark:text-amber-300">
                            Has alcanzado el máximo de 6 favoritos para este hábitat.
                        </div>

                        <div x-show="!favoritosLoading && favoritos.length > 0" x-cloak class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <template x-for="favorito in favoritos" :key="favorito.id">
                                <div
                                    class="rounded-lg p-2 border-2 transition-all text-center select-none"
                                    :class="esFavoritoEnExploracion(favorito.id)
                                        ? 'bg-gray-100 dark:bg-gray-900/30 border-gray-200 dark:border-gray-700 opacity-60'
                                        : (selectedFavoritoId === favorito.id
                                            ? 'border-blue-500 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20 ring-1 ring-blue-200 dark:ring-blue-800'
                                            : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50')"
                                >
                                    <div class="flex items-center justify-end mb-1" x-show="esFavoritoEnExploracion(favorito.id)">
                                        <span class="px-1.5 py-0.5 bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 text-[10px] font-bold rounded-full uppercase">En exploración</span>
                                    </div>
                                    <button
                                        type="button"
                                        @click="!esFavoritoEnExploracion(favorito.id) && selectFavorito(favorito.id, favorito.nombre)"
                                        :disabled="esFavoritoEnExploracion(favorito.id)"
                                        class="w-full text-center disabled:cursor-not-allowed"
                                        :aria-label="esFavoritoEnExploracion(favorito.id) ? 'Pokémon en exploración' : 'Seleccionar ' + favorito.nombre"
                                    >
                                        <img
                                            :src="'/images/iconos_webp/' + favorito.pokemon_id + '.webp'"
                                            loading="lazy"
                                            decoding="async"
                                            :alt="favorito.nombre"
                                            class="w-16 h-16 object-contain mx-auto"
                                            onerror="this.style.display='none'"
                                        >
                                        <p class="text-[10px] text-gray-700 dark:text-gray-300 truncate mt-1" x-text="favorito.nombre"></p>
                                        <p class="text-[10px] text-gray-400 dark:text-gray-500" x-text="'Nv ' + (favorito.nivel || 1)"></p>
                                    </button>
                                    <button
                                        :disabled="esFavoritoEnExploracion(favorito.id)"
                                        @click="!esFavoritoEnExploracion(favorito.id) && openEnviarHabitat(favorito)"
                                        class="mt-2 w-full px-2 py-1 bg-green-600 text-white rounded-lg text-[10px] font-bold hover:bg-green-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed uppercase"
                                        x-text="esFavoritoEnExploracion(favorito.id) ? 'En exploración' : 'Enviar a explorar'"
                                    ></button>
                                </div>
                            </template>
                        </div>

                        <!-- Botón añadir favorito (visible siempre que no esté al máximo) -->
                        <div x-show="!favoritosLoading && !favoritosError && favoritos.length > 0 && favoritos.length < 6" class="mt-3">
                            <button
                                @click="openGestionFavoritosHabitat()"
                                class="w-full px-4 py-2.5 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:border-blue-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                            >
                                + Añadir favorito
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Teams Panel: 3-column grid of team cards (modo entrenadores) -->
                <div class="{{ $cardPanelClass }}" x-show="modo === 'entrenadores'" x-cloak>
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Equipos</h3>
                    </div>
                    <div class="p-4">
                        <div class="grid sm:grid-cols-3 gap-3">
                            @forelse($teams as $team)
                            @php
                                $equipoEnExploracion = $equiposEnExploracion->firstWhere('equipo_id', $team->id);
                                $bloqueado = $equipoEnExploracion !== null;
                            @endphp
                            <div
                                class="rounded-lg p-3 border-2 transition-all {{ $bloqueado ? 'bg-gray-100 dark:bg-gray-900/30 border-gray-200 dark:border-gray-700 opacity-60 cursor-not-allowed' : 'bg-gray-50 dark:bg-gray-900/50 cursor-pointer hover:border-gray-300 dark:hover:border-gray-600' }}"
                                :class="{{ $bloqueado ? 'false' : "selectedTeamId === {$team->id} ? 'border-blue-500 dark:border-blue-400 ring-1 ring-blue-200 dark:ring-blue-800' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'" }}"
                                @if(!$bloqueado)
                                @click="selectTeam({{ $team->id }}, $el.dataset.teamName)"
                                role="button"
                                tabindex="0"
                                @keydown.space.prevent="selectTeam({{ $team->id }}, $el.dataset.teamName)"
                                @keydown.enter.prevent="selectTeam({{ $team->id }}, $el.dataset.teamName)"
                                @endif
                                data-team-name="{{ $team->name }}"
                                aria-label="{{ $bloqueado ? 'Equipo en exploración' : 'Seleccionar equipo ' . $team->name }}"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-1.5">
                                        @if($bloqueado)
                                            <svg class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                            </svg>
                                        @endif
                                        {{ $team->name }}
                                    </span>
                                    @if($bloqueado)
                                        <span class="px-2 py-0.5 bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 text-[10px] font-bold rounded-full uppercase">
                                            En exploración
                                        </span>
                                    @endif
                                </div>
                                @if($bloqueado)
                                <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-1">
                                    🔒 En exploración ({{ $equipoEnExploracion['habitat_name'] }})
                                </p>
                                @endif
                                <div class="flex flex-wrap gap-2">
                                    @for($i = 0; $i < 3; $i++)
                                        @if(isset($team->members[$i]))
                                            <div class="flex-1 text-center">
                                                <img
                                                    src="/images/iconos_webp/{{ $team->members[$i]->reclutado->pokemon_id }}.webp"
                                                    loading="lazy"
                                                    decoding="async"
                                                    alt="{{ $team->members[$i]->reclutado->nombre ?? '' }}"
                                                    title="{{ $team->members[$i]->reclutado->nombre ?? '' }}"
                                                    class="w-24 h-24 object-contain mx-auto"
                                                    onerror="this.style.display='none'"
                                                >
                                                <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                                    {{ $team->members[$i]->reclutado->nombre ?? '---' }}
                                                </p>
                                            </div>
                                        @else
                                            <div class="flex-1 text-center">
                                                <div class="w-24 h-24 mx-auto rounded border-2 border-dashed border-gray-300 dark:border-gray-600"></div>
                                                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">Vacío</p>
                                            </div>
                                        @endif
                                    @endfor
                                </div>
                            </div>
                            @empty
                            <div class="sm:col-span-3 text-center py-8">
                                <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400">No hay equipos creados</p>
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Niveles Panel: 3 clickable level rows -->
                <div class="{{ $cardPanelClass }}">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Niveles</h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <!-- MODO POKÉMON -->
                        <div x-show="modo === 'pokemon'">
                            <p x-show="avisoNivel" x-cloak x-text="avisoNivel" role="status"
                               class="text-xs text-orange-700 dark:text-orange-300 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg px-3 py-2"></p>
                            @foreach([1,2,3] as $level)
                            @php
                                $minLvl = $habitat['min_lvl_' . $level] ?? null;
                                $bloqueadoNivel = $minLvl !== null && ($nivelJugador ?? 1) < $minLvl;
                            @endphp
                            <button
                                @click="selectLevel({{ $level }})"
                                {{ $bloqueadoNivel ? 'disabled' : '' }}
                                @if($bloqueadoNivel) title="Requiere Nv {{ $minLvl }}" aria-disabled="true" @endif
                                :class="selectedLevel === {{ $level }}
                                    ? 'border-blue-500 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                class="w-full p-4 rounded-xl border-2 text-left transition-all {{ $bloqueadoNivel ? 'opacity-60 cursor-not-allowed border-dashed' : '' }}"
                                aria-label="Nivel {{ $level }}"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-1.5">
                                        @if($bloqueadoNivel)
                                            <svg class="w-3.5 h-3.5 text-orange-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                            </svg>
                                        @endif
                                        Nivel {{ $level }}
                                    </span>
                                    <span class="flex items-center gap-2">
                                        @if($bloqueadoNivel)
                                            <span class="px-2 py-0.5 bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 text-[10px] font-bold rounded-full">
                                                Requiere Nv {{ $minLvl }}
                                            </span>
                                        @endif
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ count($habitat['levels'][$level] ?? []) }} pokémon</span>
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @forelse($habitat['levels'][$level] ?? [] as $pokemon)
                                    <template x-if="!isSighted({{ $pokemon['id'] ?? $pokemon['species_id'] ?? 0 }})">
                                        <div class="relative w-24 h-24">
                                            <img src="/images/misc/unknown.webp" alt="?" class="w-full h-full object-contain">
                                        </div>
                                    </template>
                                    <template x-if="isSighted({{ $pokemon['id'] ?? $pokemon['species_id'] ?? 0 }})">
                                        <div class="relative w-24 h-24">
                                            <img src="{{ $pokemon['icon'] }}" loading="lazy" decoding="async" alt="{{ $pokemon['name'] }}" title="{{ $pokemon['name'] }}" class="w-full h-full object-contain" onerror="this.style.display='none'">
                                        </div>
                                    </template>
                                    @empty
                                    <span class="text-xs text-gray-400 dark:text-gray-500">Sin Pokémon en este nivel</span>
                                    @endforelse
                                </div>
                            </button>
                            @endforeach
                        </div>

                        <!-- MODO ENTRENADORES -->
                        <div x-show="modo === 'entrenadores'" x-cloak>
                            <template x-if="!trainers && !trainersLoading">
                                <p class="text-xs text-gray-500 dark:text-gray-400">No hay entrenadores disponibles</p>
                            </template>
                            <template x-if="trainersLoading">
                                <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                    <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Cargando entrenadores...
                                </p>
                            </template>
                            <template x-for="(levelData, levelIdx) in (trainers || [])" :key="'level-' + levelIdx">
                                <div class="mb-4">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2" x-text="'Nivel ' + levelIdx"></h4>
                                    <div class="grid grid-cols-3 gap-2">
                                        <template x-for="(entrenador, eIdx) in levelData" :key="eIdx">
                                            <button
                                                @click="selectTrainer(levelIdx, entrenador.indice)"
                                                :disabled="!entrenador.desbloqueado || !selectedTeamId"
                                                :class="{
                                                    'border-green-500 dark:border-green-400 bg-green-50 dark:bg-green-900/20': entrenador.desbloqueado && selectedTeamId,
                                                    'border-gray-200 dark:border-gray-700 opacity-50 cursor-not-allowed': !entrenador.desbloqueado || !selectedTeamId,
                                                    'hover:border-green-300 dark:hover:border-green-600': entrenador.desbloqueado && selectedTeamId
                                                }"
                                                class="w-full p-2 rounded-xl border-2 text-left transition-all"
                                            >
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="text-xs font-semibold text-gray-900 dark:text-white" x-text="'Entrenador ' + entrenador.indice"></span>
                                                    <span x-show="!entrenador.desbloqueado" class="px-1.5 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-[10px] font-bold rounded-full">Derrotado</span>
                                                </div>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Exploration Button (fallback; modal auto-opens when team + level are selected) -->
                <button
                    @click="checkAndOpenModal()"
                    :disabled="!canStartExploration"
                    class="w-full px-4 py-3 bg-green-600 text-white rounded-xl text-sm font-bold transition-all hover:bg-green-700 disabled:bg-gray-300 dark:disabled:bg-gray-700 disabled:text-gray-500 dark:disabled:text-gray-500 disabled:cursor-not-allowed uppercase tracking-wide"
                >
                    Iniciar Exploración
                </button>
                <button
                    x-show="modo === 'entrenadores'"
                    @click="openFormacionPopup()"
                    :disabled="!selectedTeamId || !selectedTrainer"
                    class="w-full px-4 py-3 bg-red-600 text-white rounded-xl text-sm font-bold transition-all hover:bg-red-700 disabled:bg-gray-300 dark:disabled:bg-gray-700 disabled:text-gray-500 dark:disabled:text-gray-500 disabled:cursor-not-allowed uppercase tracking-wide"
                >
                    Iniciar Combate contra Entrenador
                </button>
            </div>
        </div>

    <!-- Exploration Modal (individual) -->
    <template x-if="showExplorationModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeExplorationModal()">
            <div class="absolute inset-0 bg-black/60" @click="closeExplorationModal()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Confirmar Exploración</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                    ¿Quieres enviar a explorar a <strong class="text-gray-900 dark:text-white capitalize" x-text="selectedFavoritoName"></strong> a la zona <strong class="text-gray-900 dark:text-white">{{ $habitat['name'] }}</strong>?
                </p>

                <!-- Preparación de la expedición (preview individual) -->
                <div class="mb-4">
                    <div x-show="previewLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Calculando la preparación de la expedición...
                    </div>
                    <div x-show="previewError" x-cloak role="alert" class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg px-3 py-2">
                        <span x-text="previewError"></span>
                    </div>
                    <template x-if="previewLoaded && preview">
                        <div class="space-y-3 bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Peligro de la zona</span>
                                <span class="text-sm text-amber-500 tracking-tight" x-html="starRating(preview.peligro)"
                                      :title="'Peligro ' + preview.peligro + ' de 5 estrellas'" aria-hidden="true"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Nivel del Pokémon</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="'Nv ' + preview.nivel_pokemon"></span>
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
                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Nivel mínimo requerido</span>
                                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Nv <span x-text="preview.min_lvl"></span></span>
                                </div>
                            </template>
                            <div class="flex items-center justify-between gap-2 pt-1 border-t border-gray-200 dark:border-gray-700">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full uppercase" :class="riesgoClass(preview.riesgo)" x-text="'Riesgo ' + preview.riesgo"></span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Duration options (copied from existing) -->
                <div class="space-y-3 mb-6">
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

                <!-- Buttons -->
                <div class="flex gap-3">
                    <button
                        @click="closeExplorationModal()"
                        class="flex-1 px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="confirmExploration()"
                        :disabled="!previewLoaded"
                        class="flex-1 px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-bold hover:bg-green-700 transition-colors disabled:bg-gray-300 dark:disabled:bg-gray-700 disabled:text-gray-500 dark:disabled:text-gray-500 disabled:cursor-not-allowed"
                    >
                        Enviar expedición
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- Gestion Favoritos Habitat Modal -->
    <template x-if="showGestionFavoritosModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeGestionFavoritosModal()">
            <div class="absolute inset-0 bg-black/60" @click="closeGestionFavoritosModal()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Favoritos de esta hábitat</h3>
                    <button @click="closeGestionFavoritosModal()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400" aria-label="Cerrar">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-6">
                    <div x-show="gestionFavoritosLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Cargando...
                    </div>
                    <div x-show="gestionFavoritosError" x-cloak role="alert" class="text-sm text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg px-3 py-2">
                        <span x-text="gestionFavoritosError"></span>
                    </div>
                    <div x-show="!gestionFavoritosLoading && !gestionFavoritosError">
                        <!-- Máximo alcanzado -->
                        <div x-show="favoritos.length >= 6" class="mb-3 px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs text-amber-700 dark:text-amber-300">
                            Ya tienes 6 favoritos en este hábitat (máximo). Quita uno para poder añadir otro.
                        </div>
                        <!-- Grid de reclutados del usuario -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                            <template x-for="pokemon in allReclutados" :key="pokemon.id">
                                <div
                                    class="relative bg-white dark:bg-gray-800 rounded-lg shadow-sm border text-center p-2 transition-all cursor-pointer select-none"
                                    :class="esFavoritoHabitat(pokemon.id)
                                        ? 'border-yellow-500 dark:border-yellow-400 ring-1 ring-yellow-500/30'
                                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                    @click="toggleFavoritoHabitat(pokemon)"
                                    :title="esFavoritoHabitat(pokemon.id) ? 'Quitar de favoritos de este hábitat' : 'Añadir a favoritos de este hábitat'"
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
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500" x-text="'Nv ' + (pokemon.nivel || 1)"></p>
                                    <div class="mt-1">
                                        <template x-if="esFavoritoHabitat(pokemon.id)">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-[10px] font-bold rounded-full">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                Favorito
                                            </span>
                                        </template>
                                        <template x-if="!esFavoritoHabitat(pokemon.id)">
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500">Click para marcar</span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div x-show="allReclutados.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
                            <p>No hay Pokémon disponibles</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Formacion Modal (Entrenadores) -->
    <template x-if="showFormacionPopup">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeFormacionPopup()">
            <div class="absolute inset-0 bg-black/60" @click="closeFormacionPopup()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Configurar Formación</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                    Ajusta la posición de cada pokémon antes del combate.
                </p>

                <!-- Miembros del equipo con toggle -->
                <div class="space-y-3 mb-6">
                    <template x-for="miembro in (selectedTeamMembers || [])" :key="miembro.id">
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <div class="flex items-center gap-3">
                                <img :src="'/images/iconos_webp/' + miembro.reclutado.pokemon_id + '.webp'" loading="lazy" decoding="async" :alt="miembro.reclutado.nombre" class="w-16 h-16 object-contain" onerror="this.style.display='none'">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="miembro.reclutado.nombre || miembro.reclutado?.pokemon?.name"></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="'Posición ' + miembro.slot"></p>
                                </div>
                            </div>
                            <button
                                @click="toggleFormacionSlot(miembro.slot)"
                                :class="formacion[miembro.slot] === 'vanguardia'
                                    ? 'bg-blue-600 text-white border-blue-600'
                                    : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600'"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors"
                                x-text="(formacion[miembro.slot] === 'vanguardia' ? '🛡️ Vanguardia' : '⚔️ Retaguardia')"
                            ></button>
                        </div>
                    </template>
                    <template x-if="!selectedTeamMembers || selectedTeamMembers.length === 0">
                        <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">Selecciona un equipo primero</p>
                    </template>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3">
                    <button
                        @click="closeFormacionPopup()"
                        class="flex-1 px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="confirmarCombate()"
                        :disabled="!selectedTeamId || !selectedTrainer"
                        class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-bold hover:bg-red-700 transition-colors disabled:bg-gray-300 dark:disabled:bg-gray-700 disabled:text-gray-500 dark:disabled:text-gray-500 disabled:cursor-not-allowed"
                    >
                        ¡Combatir!
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- Admin Gestion Modal -->
    <template x-if="showGestionModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeGestionModal()">
            <div class="absolute inset-0 bg-black/60" @click="closeGestionModal()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Admin - Gestión de Familias</h3>
                    <button @click="closeGestionModal()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400" aria-label="Cerrar modal">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200 dark:border-gray-700 px-6">
                    <nav class="flex gap-6" role="tablist" aria-label="Gestión de familias">
                        <button
                            role="tab"
                            :aria-selected="gestionTab === 'assign'"
                            :class="['py-3 text-sm font-medium border-b-2 transition-colors', gestionTab === 'assign' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']"
                            @click="gestionTab = 'assign'"
                        >
                            Asignar
                        </button>
                        <button
                            role="tab"
                            :aria-selected="gestionTab === 'unassign'"
                            :class="['py-3 text-sm font-medium border-b-2 transition-colors', gestionTab === 'unassign' ? 'border-red-500 text-red-600 dark:text-red-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']"
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
                                    class="flex-1 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                                >
                                <select
                                    x-model="assignTypeFilter"
                                    class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                                >
                                    <option value="">Todos los tipos</option>
                                    <template x-for="tipo in allTypes" :key="tipo.id">
                                        <option :value="tipo.id" x-text="tipo.name"></option>
                                    </template>
                                </select>
                            </div>

                            <!-- Unassigned families grid -->
                            <div x-show="filteredUnassigned.length === 0 && !gestionLoading" class="text-center py-12 text-gray-500 dark:text-gray-400">
                                <p>No hay familias sin hábitat que coincidan con la búsqueda</p>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                <template x-for="family in filteredUnassigned" :key="family.evolution_chain_id">
                                    <button
                                        type="button"
                                        :disabled="gestionLoading"
                                        @click="assignFamily(family)"
                                        class="group {{ $familyCardClass }} hover:border-blue-400 dark:hover:border-blue-500 hover:shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                        :title="family.base.name"
                                    >
                                        <div class="w-full aspect-square bg-gray-100 dark:bg-gray-900 rounded-lg flex items-center justify-center overflow-hidden mb-2">
                                            <img
                                                :src="'/images/iconos_webp/' + family.base.id + '.webp'"
                                                loading="lazy"
                                                decoding="async"
                                                :alt="family.base.name"
                                                class="w-full h-full object-contain group-hover:scale-105 transition-transform"
                                                onerror="this.style.display='none'"
                                            >
                                        </div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="family.base.name"></p>
                                        <div class="flex flex-wrap justify-center gap-1 mt-1">
                                            <template x-for="tipo in (family.types || [])" :key="tipo.id">
                                                <span class="px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-[10px] font-medium rounded">
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
                            <div x-show="assignedFamilies.length === 0 && !gestionLoading" class="text-center py-12 text-gray-500 dark:text-gray-400">
                                <p>No hay familias asignadas a este hábitat</p>
                            </div>

                            <template x-for="level in [1, 2, 3]" :key="'level-' + level">
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Nivel <span x-text="level"></span></h4>
                                        <span class="text-xs text-gray-500 dark:text-gray-400" x-text="assignedByLevel[level].length + ' pokémon'"></span>
                                    </div>

                                    <div x-show="assignedByLevel[level].length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                        <template x-for="pokemon in assignedByLevel[level]" :key="pokemon.evolution_chain_id + '-' + pokemon.id">
                                            <div class="relative {{ $familyCardClass }} group">
                                                <template x-if="pokemon.is_base">
                                                    <button
                                                        type="button"
                                                        :disabled="gestionLoading"
                                                        @click="removeFamily(pokemon.evolution_chain_id)"
                                                        class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600 transition-colors disabled:opacity-30"
                                                        :aria-label="'Quitar la familia completa de ' + pokemon.name"
                                                        title="Quitar familia completa"
                                                    >
                                                        ✕
                                                    </button>
                                                </template>
                                                <div class="w-full aspect-square bg-gray-100 dark:bg-gray-900 rounded-lg flex items-center justify-center overflow-hidden mb-2">
                                                    <img
                                                        :src="'/images/iconos_webp/' + pokemon.id + '.webp'"
                                                        loading="lazy"
                                                        decoding="async"
                                                        :alt="pokemon.name"
                                                        class="w-full h-full object-contain"
                                                        onerror="this.style.display='none'"
                                                    >
                                                </div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="pokemon.name"></p>
                                                <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5" x-text="pokemon.is_base ? 'Base de la familia' : 'Evolución'"></p>
                                                <div class="flex justify-center gap-1 mt-2">
                                                    <button
                                                        type="button"
                                                        :disabled="gestionLoading"
                                                        @click="updatePokemonLevel(pokemon.evolution_chain_id, pokemon.id, 1)"
                                                        :class="pokemon.level === 1 ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:border-blue-400'"
                                                        class="w-8 h-7 text-xs font-bold rounded border transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                        :aria-label="'Mover ' + pokemon.name + ' al nivel 1'"
                                                        title="Nivel 1"
                                                    >1</button>
                                                    <button
                                                        type="button"
                                                        :disabled="gestionLoading"
                                                        @click="updatePokemonLevel(pokemon.evolution_chain_id, pokemon.id, 2)"
                                                        :class="pokemon.level === 2 ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:border-blue-400'"
                                                        class="w-8 h-7 text-xs font-bold rounded border transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                        :aria-label="'Mover ' + pokemon.name + ' al nivel 2'"
                                                        title="Nivel 2"
                                                    >2</button>
                                                    <button
                                                        type="button"
                                                        :disabled="gestionLoading"
                                                        @click="updatePokemonLevel(pokemon.evolution_chain_id, pokemon.id, 3)"
                                                        :class="pokemon.level === 3 ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:border-blue-400'"
                                                        class="w-8 h-7 text-xs font-bold rounded border transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                        :aria-label="'Mover ' + pokemon.name + ' al nivel 3'"
                                                        title="Nivel 3"
                                                    >3</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <div x-show="assignedByLevel[level].length === 0 && assignedFamilies.length > 0 && !gestionLoading" class="text-center py-6 text-gray-400 dark:text-gray-500 border border-dashed border-gray-200 dark:border-gray-700 rounded-xl">
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
</div>

@push('scripts')
<script>
function habitatShow() {
    return {
        selectedTeamId: null,
        selectedTeamName: '',
        selectedLevel: null,
        showExplorationModal: false,
        durationMode: 'hours',
        durationHours: 4,
        returnTime: '18:00',
        // Preview de la expedición (riesgo)
        preview: null,
        previewLoading: false,
        previewLoaded: false,
        previewError: '',
        sightedPokemonIds: @json($sightedPokemonIds ?? []),
        equiposEnExploracion: @json($equiposEnExploracion->pluck('equipo_id')->toArray()),
        teams: @json($teams),
        // Favoritos (exploración individual): se cargan desde /api/reclutados/favoritos?habitat_id={id}.
        favoritos: [],
        favoritosLoading: false,
        favoritosError: '',
        selectedFavoritoId: null,
        selectedFavoritoName: '',
        // Gestión de favoritos del hábitat
        showGestionFavoritosModal: false,
        gestionFavoritosLoading: false,
        gestionFavoritosError: '',
        allReclutados: [],
        reclutadosEnExploracion: @json($reclutadosEnExploracion ?? ($exploracionesActivas ? $exploracionesActivas->pluck('reclutado_id')->all() : [])),
        minLvls: {
            1: {{ $habitat['min_lvl_1'] ?? 'null' }},
            2: {{ $habitat['min_lvl_2'] ?? 'null' }},
            3: {{ $habitat['min_lvl_3'] ?? 'null' }},
        },
        nivelJugador: {{ $nivelJugador ?? 1 }},
        avisoNivel: '',

        // ─── Entrenadores mode ────────────────────────────
        modo: 'pokemon',
        trainers: null,
        trainersLoading: false,
        selectedTrainer: null,
        showFormacionPopup: false,
        formacion: {},

        get selectedTeamMembers() {
            if (!this.selectedTeamId || !this.teams) return [];
            const team = this.teams.find(t => t.id === this.selectedTeamId);
            if (!team || !team.members) return [];
            return team.members.sort((a, b) => a.slot - b.slot).filter(m => m.reclutado && m.reclutado.pokemon);
        },

        async toggleEntrenadores() {
            if (this.modo === 'entrenadores') {
                this.modo = 'pokemon';
                this.selectedTrainer = null;
                this.selectedLevel = null;
                return;
            }
            this.modo = 'entrenadores';
            this.selectedLevel = null;
            this.selectedTrainer = null;
            await this.loadTrainers();
        },

        async loadTrainers() {
            this.trainersLoading = true;
            this.trainers = null;
            try {
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/entrenadores', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('Error al cargar entrenadores');
                this.trainers = await response.json();
            } catch (e) {
                console.error('Error loading trainers:', e);
                this.trainers = null;
            } finally {
                this.trainersLoading = false;
            }
        },

        selectTrainer(level, trainerIndex) {
            if (!this.selectedTeamId) return;
            const trainer = this.trainers?.[level]?.[trainerIndex - 1];
            if (!trainer || !trainer.desbloqueado) return;
            this.selectedLevel = level;
            this.selectedTrainer = trainer;
            // Inicializar toggles (todos vanguardia por defecto)
            this.formacion = {};
            const team = this.teams.find(t => t.id === this.selectedTeamId);
            if (team && team.members) {
                team.members.forEach(m => {
                    this.formacion[m.slot] = 'vanguardia';
                });
            }
            this.showFormacionPopup = true;
        },

        openFormacionPopup() {
            if (!this.selectedTeamId || !this.selectedTrainer) {
                return;
            }
            if (Object.keys(this.formacion).length === 0) {
                this.formacion = {};
                const team = this.teams.find(t => t.id === this.selectedTeamId);
                if (team && team.members) {
                    team.members.forEach(m => {
                        this.formacion[m.slot] = 'vanguardia';
                    });
                }
            }
            this.showFormacionPopup = true;
        },

        toggleFormacionSlot(slot) {
            this.formacion[slot] = this.formacion[slot] === 'vanguardia' ? 'retaguardia' : 'vanguardia';
        },

        closeFormacionPopup() {
            this.showFormacionPopup = false;
            this.selectedTrainer = null;
        },

        async confirmarCombate() {
            if (!this.selectedTeamId || !this.selectedLevel || !this.selectedTrainer) return;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/entrenadores/' + this.selectedLevel + '/' + this.selectedTrainer.indice + '/combatir', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        team_id: this.selectedTeamId,
                        formacion: this.formacion,
                    }),
                });
                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    alert(err.message || 'Error al iniciar combate');
                    return;
                }
                const data = await response.json();
                this.showFormacionPopup = false;
                window.location.href = data.redirect || '/combate?battle_id=' + data.battle_id;
            } catch (e) {
                alert('Error al iniciar combate: ' + e.message);
            }
        },

        // Admin Gestion state
        showGestionModal: false,
        gestionTab: 'assign',
        gestionLoading: false,
        unassignedFamilies: [],
        assignedFamilies: [],
        assignSearch: '',
        assignTypeFilter: '',
        allTypes: @json(collect(\App\Enums\TipoEnum::options())->map(fn ($name, $id) => ['id' => (int) $id, 'name' => $name])->values()->all()),

        get canStartExploration() {
            return this.selectedFavoritoId !== null
                && this.selectedLevel !== null
                && !this.levelBlocked(this.selectedLevel);
        },

        get availableTeams() {
            return (this.teams || []).filter(t => !this.equiposEnExploracion.includes(t.id));
        },

        init() {
            // Cargar favoritos para la exploración individual. Tolerante: si el
            // endpoint aún no está disponible, se muestra el estado vacío/error.
            this.cargarFavoritos();
        },

        async cargarFavoritos() {
            this.favoritosLoading = true;
            this.favoritosError = '';
            try {
                const response = await fetch('/api/reclutados/favoritos?habitat_id={{ $habitat['id'] }}', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) {
                    throw new Error('No se pudieron cargar tus favoritos');
                }
                // Tolerante: si el backend aún devuelve un objeto envuelto, normaliza a array.
                const data = await response.json();
                this.favoritos = Array.isArray(data) ? data : (data.data || []);
            } catch (e) {
                console.error('Error loading favoritos:', e);
                this.favoritosError = 'Función en preparación.';
                this.favoritos = [];
            } finally {
                this.favoritosLoading = false;
            }
        },

        esFavoritoEnExploracion(id) {
            return this.reclutadosEnExploracion.includes(id);
        },

        selectFavorito(id, name) {
            if (this.reclutadosEnExploracion.includes(id)) {
                alert('Este Pokémon ya está en una exploración activa.');
                return;
            }
            this.selectedFavoritoId = id;
            this.selectedFavoritoName = name;
            this.checkAndOpenModal();
        },

        // ─── Gestión de favoritos del hábitat ───────────────────────────────

        esFavoritoHabitat(reclutadoId) {
            return this.favoritos.some(f => Number(f.id) === Number(reclutadoId));
        },

        // El usuario quiere enviar este favorito a explorar directamente (ya con
        // el hábitat preseleccionado). Inicializa el nivel y abre el modal.
        openEnviarHabitat(favorito) {
            if (this.esFavoritoEnExploracion(favorito.id)) {
                return;
            }
            // Resetear estado de envío.
            this.durationMode = 'hours';
            this.durationHours = 4;
            this.returnTime = '18:00';
            this.preview = null;
            this.previewLoaded = false;
            this.previewError = '';
            this.selectedFavoritoId = favorito.id;
            this.selectedFavoritoName = favorito.nombre;
            // Nivel por defecto: el primero disponible (no bloqueado). Si ninguno
            // está bloqueado usa el nivel 1; en caso contrario lo deja para que la
            // preview indique el error y el usuario ajuste desde el panel.
            this.avisoNivel = '';
            const nivelDefecto = [1, 2, 3].find(level => !this.levelBlocked(level)) || null;
            this.selectedLevel = nivelDefecto;
            if (nivelDefecto !== null) {
                this.openExplorationModal();
            } else {
                alert('Ningún nivel está disponible para tu nivel de jugador.');
            }
        },

        openGestionFavoritosHabitat() {
            this.showGestionFavoritosModal = true;
            this.gestionFavoritosError = '';
            this.cargarReclutadosParaGestion();
        },

        closeGestionFavoritosModal() {
            this.showGestionFavoritosModal = false;
        },

        async cargarReclutadosParaGestion() {
            this.gestionFavoritosLoading = true;
            this.gestionFavoritosError = '';
            try {
                const response = await fetch('/api/reclutados', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) {
                    throw new Error('No se pudieron cargar tus Pokémon');
                }
                const data = await response.json();
                this.allReclutados = Array.isArray(data) ? data : (data.data || []);
            } catch (e) {
                console.error('Error loading reclutados:', e);
                this.gestionFavoritosError = 'Función en preparación.';
                this.allReclutados = [];
                this.favoritos = [];
            } finally {
                this.gestionFavoritosLoading = false;
            }
        },

        async toggleFavoritoHabitat(pokemon) {
            // Máximo 6 favoritos por hábitat (aviso preventivo; el backend valida).
            if (!this.esFavoritoHabitat(pokemon.id) && this.favoritos.length >= 6) {
                alert('Máximo 6 favoritos por hábitat.');
                return;
            }
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            try {
                const response = await fetch('/api/reclutados/' + pokemon.id + '/toggle-favorito', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ habitat_id: {{ $habitat['id'] }} }),
                });
                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    alert(data.message || 'No se pudo actualizar el favorito.');
                    return;
                }
                const data = await response.json();
                // Mutación local sin recargar.
                if (data.favorito) {
                    if (!this.esFavoritoHabitat(pokemon.id) && this.favoritos.length < 6) {
                        this.favoritos.push({
                            ...pokemon,
                            nivel: pokemon.nivel || 1,
                        });
                    }
                } else {
                    this.favoritos = this.favoritos.filter(f => Number(f.id) !== Number(pokemon.id));
                }
            } catch (err) {
                console.error('Error toggling habitat favorito:', err);
                alert('Error de conexión al actualizar el favorito.');
            }
        },

        levelBlocked(level) {
            const min = this.minLvls ? this.minLvls[level] : null;
            return min !== null && min !== undefined && this.nivelJugador < min;
        },

        selectTeam(id, name) {
            if (this.equiposEnExploracion.includes(id)) {
                return;
            }
            this.selectedTeamId = id;
            this.selectedTeamName = name;
            this.checkAndOpenModal();
        },

        selectLevel(level) {
            if (this.levelBlocked(level)) {
                this.avisoNivel = 'Requiere Nv ' + this.minLvls[level] + ' de jugador para explorar este nivel.';
                return;
            }
            this.selectedLevel = level;
            this.avisoNivel = '';
            this.checkAndOpenModal();
        },

        checkAndOpenModal() {
            if (this.selectedLevel !== null && this.levelBlocked(this.selectedLevel)) {
                this.avisoNivel = 'Requiere Nv ' + this.minLvls[this.selectedLevel] + ' de jugador para explorar este nivel.';
                this.selectedLevel = null;
                return;
            }
            if (this.selectedFavoritoId !== null && this.selectedLevel !== null) {
                this.openExplorationModal();
            }
        },

        isSighted(pokemonId) {
            return this.sightedPokemonIds.includes(pokemonId);
        },

        openExplorationModal() {
            this.showExplorationModal = true;
            this.loadPreview();
        },

        async loadPreview() {
            if (!this.selectedFavoritoId || !this.selectedLevel) {
                return;
            }
            this.previewLoading = true;
            this.previewLoaded = false;
            this.previewError = '';
            this.preview = null;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const params = new URLSearchParams({
                    reclutado_id: this.selectedFavoritoId,
                    habitat_id: {{ $habitat['id'] }},
                    level: this.selectedLevel,
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

        starRating(n) {
            const valor = Math.max(0, Math.min(5, Number(n) || 0));
            return '★'.repeat(valor) + '☆'.repeat(5 - valor);
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

        closeExplorationModal() {
            this.showExplorationModal = false;
        },

        confirmExploration() {
            // Client-side validation: ensure favorite is not already exploring
            if (this.selectedFavoritoId === null) {
                alert('Selecciona un Pokémon favorito para enviar.');
                return;
            }
            if (this.reclutadosEnExploracion.includes(this.selectedFavoritoId)) {
                alert('Este Pokémon ya está en una exploración activa.');
                this.closeExplorationModal();
                return;
            }

            if (!this.previewLoaded || !this.preview) {
                alert('Espera a que se calcule la preparación de la expedición.');
                return;
            }

            // Riesgo extremo: confirmación reforzada antes de enviar.
            if (this.preview.riesgo === 'Extremo') {
                const confirmacion = window.confirm(
                    '¡Riesgo EXTREMO! ¿Seguro que quieres enviar esta expedición?'
                );
                if (!confirmacion) {
                    return;
                }
            }

            const data = {
                reclutado_id: this.selectedFavoritoId,
                level: this.selectedLevel,
                habitat_id: {{ $habitat['id'] }},
            };

            if (this.durationMode === 'hours') {
                data.duracion_horas = this.durationHours;
            } else if (this.durationMode === 'return_time') {
                data.return_time = this.returnTime;
            } else {
                data.indefinido = true;
            }

            // POST to individual exploration API
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            fetch('/api/exploraciones/store-individual', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(data),
            })
                .then(async (response) => {
                    if (response.ok) {
                        location.reload();
                        return;
                    }
                    const body = await response.json().catch(() => ({}));
                    alert(body.message || 'No se pudo enviar la exploración.');
                })
                .catch((err) => {
                    console.error('Error sending exploration:', err);
                    alert('Error de conexión al enviar la exploración.');
                });
        },

        get filteredUnassigned() {
            const query = (this.assignSearch || '').trim().toLowerCase();
            const typeId = this.assignTypeFilter ? Number(this.assignTypeFilter) : null;

            return (this.unassignedFamilies || []).filter(family => {
                const nameMatch = !query || (family.base.name || '').toLowerCase().includes(query);
                let typeMatch = true;
                if (typeId) {
                    typeMatch = (family.types || []).some(t => Number(t.id) === typeId);
                }
                return nameMatch && typeMatch;
            });
        },

        openGestionModal() {
            this.showGestionModal = true;
            this.gestionTab = 'assign';
            this.assignSearch = '';
            this.assignTypeFilter = '';
            this.loadUnassignedFamilies();
            this.loadAssignedFamilies();
        },

        closeGestionModal() {
            this.showGestionModal = false;
        },

        async loadUnassignedFamilies() {
            this.gestionLoading = true;
            try {
                const response = await fetch('/api/habitats/unassigned-families', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('Error al cargar familias');
                const data = await response.json();
                this.unassignedFamilies = (data || []).map(f => ({
                    ...f,
                    total_stages: 1 + (f.evolutions?.length || 0),
                }));
            } catch (e) {
                alert('Error al cargar familias: ' + e.message);
            } finally {
                this.gestionLoading = false;
            }
        },

        async loadAssignedFamilies() {
            this.gestionLoading = true;
            try {
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/families', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('Error al cargar familias asignadas');
                const data = await response.json();
                this.assignedFamilies = (data || []).map(f => ({
                    ...f,
                    total_stages: 1 + (f.evolutions?.length || 0),
                }));
            } catch (e) {
                alert('Error al cargar familias asignadas: ' + e.message);
            } finally {
                this.gestionLoading = false;
            }
        },

        get assignedByLevel() {
            const grouped = { 1: [], 2: [], 3: [] };
            (this.assignedFamilies || []).forEach((family) => {
                if (Number.isFinite(Number(family.base?.level))) {
                    grouped[Number(family.base.level)]?.push({
                        ...family.base,
                        evolution_chain_id: family.evolution_chain_id,
                        is_base: true,
                    });
                }
                (family.evolutions || []).forEach((evo) => {
                    if (Number.isFinite(Number(evo.level))) {
                        grouped[Number(evo.level)]?.push({
                            ...evo,
                            evolution_chain_id: family.evolution_chain_id,
                            is_base: false,
                        });
                    }
                });
            });
            return grouped;
        },

        async assignFamily(family) {
            if (this.gestionLoading) return;
            this.gestionLoading = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/families', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ evolution_chain_id: family.evolution_chain_id }),
                });
                if (!response.ok) {
                    const err = await response.json();
                    throw new Error(err.message || 'Error al asignar la familia');
                }

                // Refresco local SIN recargar listados: quito de no asignadas siempre.
                this.unassignedFamilies = (this.unassignedFamilies || []).filter(f => f.evolution_chain_id !== family.evolution_chain_id);

                // El POST devuelve (201) la familia COMPLETA con los niveles reales por miembro
                // (base.level / evolutions[].level). La añadimos decorada con total_stages, igual
                // que hacen los loaders, para que assignedByLevel agrupe con datos reales y no
                // con la inferencia client-side del antiguo helper.
                const data = await response.json().catch(() => null);
                if (data && data.evolution_chain_id && data.base) {
                    this.assignedFamilies = [
                        { ...data, total_stages: 1 + (data.evolutions?.length || 0) },
                        ...(this.assignedFamilies || []),
                    ];
                }
                // Fallback defensivo: si el body NO trae la estructura completa (p. ej. el backend
                // aún no entrega el nuevo shape), NO inferimos niveles y no añadimos la familia a
                // assignedFamilies (un objeto mínimo sin level rompería la pestaña Ya Asignados y
                // assignedByLevel). Como ya la quitamos de unassignedFamilies, no hay duplicados:
                // se auto-corrige al reabrir el modal, que sí recarga los listados.
            } catch (e) {
                alert('Error al asignar: ' + e.message);
            } finally {
                this.gestionLoading = false;
            }
        },

        async updatePokemonLevel(chainId, pokemonId, level) {
            if (this.gestionLoading) return;
            this.gestionLoading = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/pokemon/' + pokemonId, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ level: level }),
                });
                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    throw new Error(err.message || 'Error al mover el pokémon de nivel');
                }

                // Refresco local SIN recargar: muto el nivel de ese pokémon para que re-agrupe solo.
                const family = (this.assignedFamilies || []).find(f => f.evolution_chain_id === chainId);
                if (!family) return;

                if (Number(family.base?.id) === pokemonId) {
                    family.base.level = level;
                    return;
                }

                const evo = (family.evolutions || []).find(e => Number(e.id) === pokemonId);
                if (evo) {
                    evo.level = level;
                }
            } catch (e) {
                alert('Error al mover de nivel: ' + e.message);
            } finally {
                this.gestionLoading = false;
            }
        },

        async removeFamily(chainId) {
            if (this.gestionLoading) return;
            this.gestionLoading = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/families/' + chainId, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });
                if (!response.ok) {
                    const err = await response.json();
                    throw new Error(err.message || 'Error al desasignar la familia');
                }

                // Refresco local SIN recargar: quito de asignadas y devuelvo a no asignadas (sin levels, con types).
                const family = (this.assignedFamilies || []).find(f => f.evolution_chain_id === chainId);
                this.assignedFamilies = (this.assignedFamilies || []).filter(f => f.evolution_chain_id !== chainId);

                if (family) {
                    this.unassignedFamilies = [
                        {
                            evolution_chain_id: family.evolution_chain_id,
                            base: { id: family.base?.id, name: family.base?.name, icon: family.base?.icon },
                            evolutions: (family.evolutions || []).map(evo => ({ id: evo.id, name: evo.name, icon: evo.icon })),
                            types: family.types || [],
                        },
                        ...(this.unassignedFamilies || []),
                    ];
                }
            } catch (e) {
                alert('Error al desasignar: ' + e.message);
            } finally {
                this.gestionLoading = false;
            }
        },
    };
}
</script>
@endpush
@endsection
