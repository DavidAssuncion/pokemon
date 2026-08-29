@extends('layouts.app')

@section('title', 'Hábitat - ' . ($habitat['name'] ?? ''))

@section('content')
@php
    // Clases shell compartidas (dedup de patrones repetidos en paneles/modal).
    $cardPanelClass = 'bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden';
    $constructionButtonClass = 'w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 transition-colors flex items-center justify-center gap-2';
    $familyCardClass = 'bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 text-center';
@endphp
<div x-data="habitatShow()" x-init="init()">
        <!-- Main: Left 1/4 (back/title/image/construction) + Right 3/4 (explorations/teams/levels) -->
        <div class="grid lg:grid-cols-4 gap-6">
            <!-- Left 1/4: Back, Title, Image (auto size), stacked construction buttons -->
            <div class="lg:col-span-1 space-y-4">
                <a href="/habitats" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium transition-colors inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Volver a hábitats
                </a>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $habitat['name'] }}</h1>
                <!-- Image: auto size (natural), NOT full width -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-3 overflow-hidden w-fit">
                    <img
                        src="{{ $habitat['image'] ?? '' }}"
                        alt="{{ $habitat['name'] }}"
                        class="w-auto h-auto max-w-full rounded-lg"
                        onerror="this.style.display='none'"
                    >
                </div>

                <!-- Construction buttons (stacked, full width) -->
                @php
                    $bloqueadoConstruccion = $exploracionesActivas && $exploracionesActivas->count() > 0;
                @endphp
                <div class="space-y-3">
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : "alert('Función próximamente')" }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="{{ $constructionButtonClass }} {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                    >
                        <img src="/images/misc/item/farm.webp" loading="lazy" decoding="async" class="w-8 h-8 object-contain" alt="Granjas">
                        Granjas
                    </button>
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : "alert('Función próximamente')" }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="{{ $constructionButtonClass }} {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                    >
                        <img src="/images/misc/item/trainer.webp" loading="lazy" decoding="async" class="w-8 h-8 object-contain" alt="Entrenadores">
                        Entrenadores
                    </button>
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : "alert('Función próximamente')" }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="{{ $constructionButtonClass }} {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                    >
                        <img src="/images/misc/raid.png" loading="lazy" decoding="async" class="w-8 h-8 object-contain" alt="Mazmorras">
                        Mazmorras
                    </button>
                    <button
                        @click="openGestionModal()"
                        class="w-full px-4 py-3 bg-blue-600 text-white rounded-xl text-sm font-bold transition-colors hover:bg-blue-700 flex items-center justify-center gap-2 uppercase tracking-wide"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Admin - Gestion
                    </button>
                </div>
                @if($bloqueadoConstruccion)
                <p class="text-xs text-center text-orange-600 dark:text-orange-400">
                    No disponible durante exploraciones activas
                </p>
                @endif
            </div>

            <!-- Right 3/4: Explorations, Teams, Levels -->
            <div class="lg:col-span-3 space-y-6">
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
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $exp->team->name ?? 'Equipo eliminado' }}</p>
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

                <!-- Teams Panel: 3-column grid of team cards -->
                <div class="{{ $cardPanelClass }}">
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
                        @foreach([1,2,3] as $level)
                        <button
                            @click="selectLevel({{ $level }})"
                            :class="selectedLevel === {{ $level }}
                                ? 'border-blue-500 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20'
                                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                            class="w-full p-4 rounded-xl border-2 text-left transition-all"
                            aria-label="Nivel {{ $level }}"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">Nivel {{ $level }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ count($habitat['levels'][$level] ?? []) }} pokémon</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @forelse($habitat['levels'][$level] ?? [] as $pokemon)
                                <template x-if="!isSighted({{ $pokemon['id'] ?? $pokemon['species_id'] ?? 0 }})">
                                    <div class="relative w-24 h-24">
                                        <img src="/images/reward/pokemon_encounter/0.png" alt="?" class="w-full h-full object-contain">
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
                </div>

                <!-- Exploration Button (fallback; modal auto-opens when team + level are selected) -->
                <button
                    @click="checkAndOpenModal()"
                    :disabled="!canStartExploration"
                    class="w-full px-4 py-3 bg-green-600 text-white rounded-xl text-sm font-bold transition-all hover:bg-green-700 disabled:bg-gray-300 dark:disabled:bg-gray-700 disabled:text-gray-500 dark:disabled:text-gray-500 disabled:cursor-not-allowed uppercase tracking-wide"
                >
                    Iniciar Exploración
                </button>
            </div>
        </div>

    <!-- Exploration Modal -->
    <template x-if="showExplorationModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeExplorationModal()">
            <div class="absolute inset-0 bg-black/60" @click="closeExplorationModal()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Confirmar Exploración</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                    ¿Quieres mandar a explorar <strong class="text-gray-900 dark:text-white" x-text="selectedTeamName"></strong> a la zona <strong class="text-gray-900 dark:text-white">{{ $habitat['name'] }}</strong>?
                </p>

                <!-- Duration options -->
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
                        class="flex-1 px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-bold hover:bg-green-700 transition-colors"
                    >
                        Explorar
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
        sightedPokemonIds: @json($sightedPokemonIds ?? []),
        equiposEnExploracion: @json($equiposEnExploracion->pluck('equipo_id')->toArray()),
        teams: @json($teams),

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
            return this.selectedTeamId !== null && this.selectedLevel !== null;
        },

        get availableTeams() {
            return (this.teams || []).filter(t => !this.equiposEnExploracion.includes(t.id));
        },

        init() {
            // No auto-selection for existing explorations
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
            this.selectedLevel = level;
            this.checkAndOpenModal();
        },

        checkAndOpenModal() {
            if (this.selectedTeamId !== null && this.selectedLevel !== null) {
                this.openExplorationModal();
            }
        },

        isSighted(pokemonId) {
            return this.sightedPokemonIds.includes(pokemonId);
        },

        openExplorationModal() {
            this.showExplorationModal = true;
        },

        closeExplorationModal() {
            this.showExplorationModal = false;
        },

        confirmExploration() {
            // Client-side validation: ensure team is not already exploring
            if (this.equiposEnExploracion.includes(this.selectedTeamId)) {
                alert('Este equipo ya está en una exploración activa.');
                this.closeExplorationModal();
                return;
            }

            const data = {
                team_id: this.selectedTeamId,
                level: this.selectedLevel,
                habitat_id: {{ $habitat['id'] }},
                duration_mode: this.durationMode,
            };

            if (this.durationMode === 'hours') {
                data.duration_hours = this.durationHours;
            } else if (this.durationMode === 'return_time') {
                data.return_time = this.returnTime;
            }

            // POST to exploration endpoint
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/exploraciones';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);

            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'POST';
            form.appendChild(methodInput);

            for (const [key, value] of Object.entries(data)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
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
