@extends('layouts.app')

@section('title', 'Hábitat - ' . ($habitat['name'] ?? ''))

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900" x-data="habitatShow()" x-init="init()">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Top Section: 3 columns -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <!-- Left: Back + Name -->
            <div class="flex flex-col justify-center">
                <a href="/habitats" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium transition-colors mb-2 inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Volver a hábitats
                </a>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $habitat['name'] }}</h1>
            </div>

            <!-- Center: Habitat Image -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="aspect-[16/9] relative bg-gray-100 dark:bg-gray-900">
                    <img
                        src="{{ $habitat['image'] ?? '' }}"
                        alt="{{ $habitat['name'] }}"
                        class="w-full h-full object-cover"
                        onerror="this.style.display='none'"
                    >
                </div>
            </div>

            <!-- Right: Placeholder buttons -->
            @php
                $bloqueadoConstruccion = $exploracionesActivas && $exploracionesActivas->count() > 0;
            @endphp
            <div class="flex flex-col justify-center gap-2">
                <button
                    @click="{{ $bloqueadoConstruccion ? '' : "alert('Función próximamente')" }}"
                    {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                    @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                    class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 transition-colors flex items-center justify-center gap-2 {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                >
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1"/>
                    </svg>
                    Granjas
                </button>
                <button
                    @click="{{ $bloqueadoConstruccion ? '' : "alert('Función próximamente')" }}"
                    {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                    @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                    class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 transition-colors flex items-center justify-center gap-2 {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                >
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Entrenadores
                </button>
                <button
                    @click="{{ $bloqueadoConstruccion ? '' : "alert('Función próximamente')" }}"
                    {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                    @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                    class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 transition-colors flex items-center justify-center gap-2 {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                >
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Mazmorras
                </button>
                @if($bloqueadoConstruccion)
                <p class="text-[10px] text-center text-orange-600 dark:text-orange-400">
                    No disponible durante exploraciones activas
                </p>
                @endif
            </div>
        </div>

        <!-- Active Explorations List -->
        @if($exploracionesActivas && $exploracionesActivas->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mb-6 overflow-hidden">
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

        <!-- Bottom Section: Teams (1/3) + Pokemon Grid (2/3) -->
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left 1/3: Teams Panel -->
            <div class="space-y-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Equipos</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Selecciona un equipo para explorar</p>
                    </div>
                    <div class="p-4 space-y-3">
                        @forelse($teams as $team)
                        @php
                            $equipoEnExploracion = $equiposEnExploracion->firstWhere('equipo_id', $team->id);
                            $bloqueado = $equipoEnExploracion !== null;
                        @endphp
                        <div
                            class="rounded-lg p-3 border-2 transition-all {{ $bloqueado ? 'bg-gray-100 dark:bg-gray-900/30 border-gray-200 dark:border-gray-700 opacity-60 cursor-not-allowed' : 'bg-gray-50 dark:bg-gray-900/50 cursor-pointer hover:border-gray-300 dark:hover:border-gray-600' }}"
                            :class="{{ $bloqueado ? 'false' : "selectedTeamId === {$team->id} ? 'border-blue-500 dark:border-blue-400 ring-1 ring-blue-200 dark:ring-blue-800' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'" }}"
                            @if(!$bloqueado)
                            @click="selectTeam({{ $team->id }}, '{{ addslashes($team->name) }}')"
                            role="button"
                            tabindex="0"
                            @keydown.space.prevent="selectTeam({{ $team->id }}, '{{ addslashes($team->name) }}')"
                            @keydown.enter.prevent="selectTeam({{ $team->id }}, '{{ addslashes($team->name) }}')"
                            @endif
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
                            <div class="flex gap-2">
                                @for($i = 0; $i < 3; $i++)
                                    @if(isset($team->members[$i]))
                                        <div class="flex-1 text-center">
                                            <img
                                                src="/images/iconos/{{ $team->members[$i]->reclutado->pokemon_id }}.png"
                                                alt="{{ $team->members[$i]->reclutado->nombre ?? '' }}"
                                                title="{{ $team->members[$i]->reclutado->nombre ?? '' }}"
                                                class="w-10 h-10 object-contain mx-auto rounded bg-gray-100 dark:bg-gray-800"
                                                onerror="this.style.display='none'"
                                            >
                                            <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                                {{ $team->members[$i]->reclutado->nombre ?? '---' }}
                                            </p>
                                        </div>
                                    @else
                                        <div class="flex-1 text-center">
                                            <div class="w-10 h-10 mx-auto rounded border-2 border-dashed border-gray-300 dark:border-gray-600"></div>
                                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">Vacío</p>
                                        </div>
                                    @endif
                                @endfor
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No hay equipos creados</p>
                        </div>
                        @endforelse
                    </div>
                </div>

                <!-- Level Selection -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Nivel de exploración</h3>
                    </div>
                    <div class="p-4 space-y-2">
                        @foreach([1,2,3] as $level)
                        <button
                            @click="selectedLevel = {{ $level }}"
                            :class="selectedLevel === {{ $level }}
                                ? 'border-blue-500 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20'
                                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                            class="w-full p-3 rounded-lg border-2 text-left transition-all"
                            aria-label="Nivel {{ $level }}"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">Nivel {{ $level }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ !empty($habitat['levels'][$level]) ? count($habitat['levels'][$level]) : 0 }} pokémon
                                </span>
                            </div>
                            @if(!empty($habitat['levels'][$level]))
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach($habitat['levels'][$level] as $pokemon)
                                <img
                                    src="{{ $pokemon['icon'] }}"
                                    alt="{{ $pokemon['name'] }}"
                                    title="{{ $pokemon['name'] }}"
                                    class="w-8 h-8 object-contain rounded bg-gray-100 dark:bg-gray-800"
                                    onerror="this.style.display='none'"
                                >
                                @endforeach
                            </div>
                            @endif
                        </button>
                        @endforeach
                    </div>
                </div>

                <!-- Exploration Button -->
                <button
                    @click="canStartExploration && openExplorationModal()"
                    :disabled="!canStartExploration"
                    class="w-full px-4 py-3 bg-green-600 text-white rounded-xl text-sm font-bold transition-all hover:bg-green-700 disabled:bg-gray-300 dark:disabled:bg-gray-700 disabled:text-gray-500 dark:disabled:text-gray-500 disabled:cursor-not-allowed uppercase tracking-wide"
                >
                    Iniciar Exploración
                </button>
            </div>

            <!-- Right 2/3: Pokemon Grid -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pokémon del hábitat</h3>
                    </div>
                    <div class="p-4">
                        @php
                            $allPokemon = [];
                            if (!empty($habitat['levels'])) {
                                foreach ($habitat['levels'] as $level => $pokemonList) {
                                    foreach ($pokemonList as $pokemon) {
                                        $allPokemon[] = $pokemon;
                                    }
                                }
                            }
                        @endphp
                        @if(count($allPokemon) > 0)
                        <div class="grid grid-cols-6 sm:grid-cols-8 lg:grid-cols-10 gap-2">
                            @foreach($allPokemon as $pokemon)
                            <div
                                class="relative aspect-square flex items-center justify-center rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 group"
                                :class="{ 'grayscale opacity-40': !isSighted({{ $pokemon['id'] ?? $pokemon['species_id'] ?? 0 }}) }"
                                title="{{ $pokemon['name'] }}"
                            >
                                <img
                                    src="{{ $pokemon['icon'] ?? '/images/iconos/' . ($pokemon['id'] ?? $pokemon['species_id'] ?? 0) . '.png' }}"
                                    alt="{{ $pokemon['name'] }}"
                                    class="w-full h-full object-contain p-1"
                                    onerror="this.style.display='none'"
                                >
                                <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center">
                                    <span class="text-[10px] text-white px-1 truncate max-w-full">{{ $pokemon['name'] }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400">No hay Pokémon en este hábitat</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
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

        get canStartExploration() {
            return this.selectedTeamId !== null && this.selectedLevel !== null;
        },

        get availableTeams() {
            return @json($teams)->filter(t => !this.equiposEnExploracion.includes(t.id));
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
    };
}
</script>
@endpush
@endsection
