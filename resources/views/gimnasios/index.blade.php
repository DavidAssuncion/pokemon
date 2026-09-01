@extends('layouts.app')

@section('title', 'Gimnasios')

@section('content')
@php
    // Mapeo tipo (int de TipoPokemon) → nombre en español + clases de badge (colores Tailwind).
    $tipoBadges = [
        1  => ['Normal',  'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'],
        2  => ['Lucha',   'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400'],
        3  => ['Volador', 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400'],
        4  => ['Veneno',  'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400'],
        5  => ['Tierra',  'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'],
        6  => ['Roca',    'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400'],
        7  => ['Bicho',   'bg-lime-100 dark:bg-lime-900/30 text-lime-700 dark:text-lime-400'],
        8  => ['Fantasma','bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400'],
        9  => ['Acero',   'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300'],
        10 => ['Fuego',   'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'],
        11 => ['Agua',    'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'],
        12 => ['Planta',  'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'],
        13 => ['Eléctrico','bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400'],
        14 => ['Psíquico','bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-400'],
        15 => ['Hielo',   'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-400'],
        16 => ['Dragón',  'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-400'],
        17 => ['Siniestro','bg-zinc-800 dark:bg-gray-600 text-white'],
        18 => ['Hada',    'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400'],
    ];
    $defaultBadge = ['Desconocido', 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'];
@endphp

<div x-data="gimnasiosIndex()" x-init="init()">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Gimnasios</h1>
        <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-xs font-bold rounded-full">
            Nv {{ $nivelJugador ?? 1 }}
        </span>
    </div>

    {{-- Loading --}}
    <div x-show="loading" x-cloak role="status" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-4">
        <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Cargando gimnasios...
    </div>

    {{-- Error --}}
    <div x-show="error" x-cloak role="alert" class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg px-3 py-2 mb-4">
        <span x-text="error"></span>
    </div>

    {{-- Grid --}}
    <div x-show="!loading && !error && gyms.length > 0" x-cloak class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <template x-for="gym in gyms" :key="gym.slug">
            <div
                class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col
                       {{-- estados clickables --}}
                       transition-all"
                :class="gym.estado === 'disponible'
                    ? 'hover:shadow-md hover:border-blue-300 dark:hover:border-blue-600 cursor-pointer'
                    : 'opacity-70'"
            >
                {{-- Cabecera: medalla + tipo --}}
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-2xl shrink-0" aria-hidden="true">🏅</span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate" x-text="gym.medalla"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Nivel mínimo: <strong x-text="'Nv ' + gym.nivel_minimo"></strong>
                            </p>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full uppercase shrink-0"
                          :class="tipoBadge(gym.tipo)[1]"
                          x-text="tipoBadge(gym.tipo)[0]"></span>
                </div>

                {{-- Cuerpo: estado --}}
                <div class="px-4 py-3 flex-1 flex flex-col gap-2">
                    {{-- Progreso --}}
                    <template x-if="gym.estado === 'disponible'">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-300">
                                Etapa actual <strong x-text="gym.etapa_actual"></strong>/4
                            </span>
                            <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-500 transition-all"
                                     :style="'width: ' + Math.min(100, (gym.etapa_actual / 4) * 100) + '%'"></div>
                            </div>
                        </div>
                    </template>

                    {{-- Bloqueado --}}
                    <template x-if="gym.estado === 'bloqueado'">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-orange-500 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                            <p class="text-xs text-orange-700 dark:text-orange-400 font-medium" x-text="'Requiere Nv ' + gym.nivel_minimo + ' de jugador'"></p>
                        </div>
                    </template>

                    {{-- Completado --}}
                    <template x-if="gym.estado === 'completado'">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-600 dark:text-green-400 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <p class="text-xs text-green-700 dark:text-green-400 font-medium">✔ Medalla ganada</p>
                        </div>
                    </template>
                </div>

                {{-- Acción --}}
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                    <template x-if="gym.estado === 'disponible'">
                        <a :href="'/gimnasios/' + gym.slug"
                           class="block w-full px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-bold text-center uppercase tracking-wide transition-colors hover:bg-blue-700"
                           :aria-label="'Entrar en ' + gym.medalla">
                            Entrar
                        </a>
                    </template>
                    <template x-if="gym.estado === 'bloqueado'">
                        <button disabled
                                :title="'Requiere Nv ' + gym.nivel_minimo + ' de jugador'"
                                aria-disabled="true"
                                class="w-full px-4 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-lg text-sm font-bold text-center uppercase tracking-wide cursor-not-allowed">
                            Bloqueado
                        </button>
                    </template>
                    <template x-if="gym.estado === 'completado'">
                        <button disabled
                                title="Gimnasio completado"
                                aria-disabled="true"
                                class="w-full px-4 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-lg text-sm font-bold text-center uppercase tracking-wide cursor-not-allowed">
                            Completado
                        </button>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- Empty state (defensivo) --}}
    <div x-show="!loading && !error && gyms.length === 0" x-cloak
         class="text-center py-12 text-gray-500 dark:text-gray-400 border border-dashed border-gray-200 dark:border-gray-700 rounded-xl">
        <p class="text-sm">No hay gimnasios disponibles.</p>
    </div>
</div>

@push('scripts')
<script>
function gimnasiosIndex() {
    return {
        gyms: [],
        loading: false,
        error: '',

        // Mapeo tipo int (TipoPokemon) → [nombre español, clases badge] (compartido con show).
        tipoBadges: @json($tipoBadges),

        tipoBadge(tipo) {
            return this.tipoBadges[tipo] || @json($defaultBadge);
        },

        async init() {
            this.loading = true;
            this.error = '';
            try {
                const response = await fetch('/api/gimnasios', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('Error al cargar gimnasios');
                this.gyms = await response.json();
            } catch (e) {
                console.error('Error loading gyms:', e);
                this.error = 'No se pudieron cargar los gimnasios. Inténtalo de nuevo.';
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
@endpush
@endsection