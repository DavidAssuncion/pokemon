@extends('layouts.app')

@section('title', 'Pokédex')

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900" x-data="pokedexApp()" x-init="init()">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Pokédex</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                <span x-text="stats.vistos"></span> vistos · <span x-text="stats.atrapados"></span> atrapados · <span x-text="stats.total"></span> totales
            </p>
        </div>

        <!-- Filter bar -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
            <div class="flex gap-2">
                <button
                    @click="activeFilter = 'all'"
                    :class="activeFilter === 'all'
                        ? 'bg-blue-600 text-white dark:bg-blue-500'
                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                >
                    Todos
                </button>
                <button
                    @click="activeFilter = 'vistos'"
                    :class="activeFilter === 'vistos'
                        ? 'bg-blue-600 text-white dark:bg-blue-500'
                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                >
                    Vistos
                </button>
                <button
                    @click="activeFilter = 'atrapados'"
                    :class="activeFilter === 'atrapados'
                        ? 'bg-blue-600 text-white dark:bg-blue-500'
                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                >
                    Atrapados
                </button>
            </div>
            <div class="relative flex-1 max-w-xs">
                <input
                    type="text"
                    x-model="searchQuery"
                    placeholder="Buscar por nombre o número..."
                    class="w-full px-4 py-2 pl-10 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                >
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </div>

        <!-- Pokemon grid -->
        <div class="grid grid-cols-3 sm:grid-cols-6 lg:grid-cols-9 gap-3">
            <template x-for="pokemon in filteredPokemons" :key="pokemon.id">
                <div
                    @click="openDetail(pokemon)"
                    class="relative bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden cursor-pointer transition-all hover:shadow-md hover:border-blue-300 dark:hover:border-blue-600 group"
                    :class="{ 'border-green-500 dark:border-green-400': pokemon.atrapado }"
                    role="button"
                    tabindex="0"
                    @keydown.space.prevent="openDetail(pokemon)"
                    @keydown.enter.prevent="openDetail(pokemon)"
                    :aria-label="'Pokemon #' + pokemon.id + ' ' + (pokemon.visto ? pokemon.name : 'No visto')"
                >
                    <!-- Image -->
                    <div class="aspect-square relative bg-gray-50 dark:bg-gray-900 p-2">
                        <img
                            :src="'/images/iconos/' + pokemon.id + '.png'"
                            :alt="pokemon.visto ? pokemon.name : 'Pokemon desconocido'"
                            class="w-full h-full object-contain transition-transform group-hover:scale-110"
                            :class="{ 'grayscale opacity-40': !pokemon.visto }"
                            onerror="this.style.display='none'"
                        >
                        <!-- Captured badge -->
                        <template x-if="pokemon.atrapado">
                            <span class="absolute top-1 right-1 px-1 py-0.5 bg-green-500 text-white text-[10px] font-bold rounded">
                                ★
                            </span>
                        </template>
                    </div>
                    <!-- Info -->
                    <div class="px-2 pb-2 text-center">
                        <p class="text-[10px] text-gray-400 dark:text-gray-500" x-text="'#' + pokemon.id"></p>
                        <p
                            class="text-xs font-medium text-gray-900 dark:text-white truncate"
                            :class="{ 'text-gray-400 dark:text-gray-500': !pokemon.visto }"
                            x-text="pokemon.visto ? pokemon.name : '???'"
                        ></p>
                    </div>
                </div>
            </template>
        </div>

        <!-- Empty state -->
        <template x-if="filteredPokemons.length === 0">
            <div class="text-center py-16">
                <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <p class="text-gray-500 dark:text-gray-400">No se encontraron Pokémon</p>
            </div>
        </template>
    </div>

    <!-- Detail Modal -->
    <template x-if="showModal && selectedPokemon">
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            @keydown.escape.window="closeDetail()"
        >
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/60" @click="closeDetail()"></div>
            <!-- Modal content -->
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <!-- Close button -->
                <button
                    @click="closeDetail()"
                    class="absolute top-3 right-3 z-10 w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    aria-label="Cerrar"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                <!-- Header -->
                <div class="p-6 pb-4 text-center border-b border-gray-200 dark:border-gray-700">
                    <img
                        :src="'/images/iconos/' + selectedPokemon.id + '.png'"
                        :alt="selectedPokemon.visto ? selectedPokemon.name : 'Desconocido'"
                        class="w-24 h-24 object-contain mx-auto mb-3"
                        :class="{ 'grayscale opacity-40': !selectedPokemon.visto }"
                        onerror="this.style.display='none'"
                    >
                    <p class="text-xs text-gray-400 dark:text-gray-500" x-text="'#' + selectedPokemon.id"></p>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white" x-text="selectedPokemon.visto ? selectedPokemon.name : '???'"></h2>
                    <template x-if="selectedPokemon.atrapado">
                        <span class="inline-block mt-2 px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs font-medium rounded-full">
                            Atrapado
                        </span>
                    </template>
                </div>

                <!-- Stats -->
                <div class="p-6 space-y-4">
                    <!-- Types -->
                    <template x-if="selectedPokemon.visto && selectedPokemon.types && selectedPokemon.types.length > 0">
                        <div>
                            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Tipo</h3>
                            <div class="flex gap-2">
                                <template x-for="type in selectedPokemon.types" :key="type">
                                    <span
                                        class="px-3 py-1 text-xs font-medium rounded-full"
                                        :class="getTypeClass(type)"
                                        x-text="type"
                                    ></span>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Stats bars -->
                    <template x-if="selectedPokemon.visto && selectedPokemon.stats && selectedPokemon.stats.length > 0">
                        <div>
                            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Estadísticas</h3>
                            <div class="space-y-2">
                                <template x-for="stat in selectedPokemon.stats" :key="stat.name">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-gray-600 dark:text-gray-400 w-20 text-right" x-text="stat.name"></span>
                                        <div class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div
                                                class="h-full rounded-full transition-all duration-500"
                                                :class="getStatColor(stat.name)"
                                                :style="'width: ' + Math.min((stat.value / 255) * 100, 100) + '%'"
                                            ></div>
                                        </div>
                                        <span class="text-xs font-medium text-gray-900 dark:text-white w-8" x-text="stat.value"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Habitat -->
                    <template x-if="selectedPokemon.habitat_name">
                        <div>
                            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Hábitat</h3>
                            <p class="text-sm text-gray-900 dark:text-white" x-text="selectedPokemon.habitat_name"></p>
                        </div>
                    </template>

                    <!-- Not viewed message -->
                    <template x-if="!selectedPokemon.visto">
                        <div class="text-center py-6">
                            <p class="text-gray-400 dark:text-gray-500 italic">Este Pokémon aún no ha sido avistado.</p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>

@push('scripts')
<script>
function pokedexApp() {
    return {
        pokemons: @json($pokemons ?? []),
        activeFilter: 'all',
        searchQuery: '',
        showModal: false,
        selectedPokemon: null,

        get stats() {
            const total = this.pokemons.length;
            const vistos = this.pokemons.filter(p => p.visto).length;
            const atrapados = this.pokemons.filter(p => p.atrapado).length;
            return { total, vistos, atrapados };
        },

        get filteredPokemons() {
            let result = this.pokemons;

            // Apply filter
            if (this.activeFilter === 'vistos') {
                result = result.filter(p => p.visto);
            } else if (this.activeFilter === 'atrapados') {
                result = result.filter(p => p.atrapado);
            }

            // Apply search
            if (this.searchQuery.trim()) {
                const q = this.searchQuery.toLowerCase().trim();
                result = result.filter(p =>
                    p.name.toLowerCase().includes(q) ||
                    String(p.id).includes(q) ||
                    ('#' + p.id).includes(q)
                );
            }

            return result;
        },

        init() {
            // Compute stats
        },

        openDetail(pokemon) {
            this.selectedPokemon = pokemon;
            this.showModal = true;
        },

        closeDetail() {
            this.showModal = false;
            this.selectedPokemon = null;
        },

        getTypeClass(type) {
            const classes = {
                'Normal': 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
                'Fuego': 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
                'Agua': 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
                'Planta': 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
                'Eléctrico': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400',
                'Hielo': 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-400',
                'Lucha': 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400',
                'Veneno': 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400',
                'Tierra': 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
                'Volador': 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-400',
                'Psíquico': 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-400',
                'Bicho': 'bg-lime-100 text-lime-700 dark:bg-lime-900/40 dark:text-lime-400',
                'Roca': 'bg-stone-100 text-stone-700 dark:bg-stone-700 dark:text-stone-300',
                'Fantasma': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400',
                'Dragón': 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-400',
                'Siniestro': 'bg-gray-800 text-gray-100 dark:bg-gray-600 dark:text-gray-200',
                'Acero': 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
                'Hada': 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/40 dark:text-fuchsia-400',
            };
            return classes[type] || 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200';
        },

        getStatColor(statName) {
            const colors = {
                'PS (HP)': 'bg-green-500',
                'Ataque': 'bg-red-500',
                'Defensa': 'bg-blue-500',
                'Ataque Especial': 'bg-purple-500',
                'Defensa Especial': 'bg-teal-500',
                'Velocidad': 'bg-yellow-500',
            };
            return colors[statName] || 'bg-gray-500';
        },
    };
}
</script>
@endpush
@endsection
