@extends('layouts.app')

@section('title', 'Pokédex')

@section('content')
<div x-data="pokedexApp()" x-init="init()">
        <!-- Header -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-ink">Pokédex</h1>
                <p class="text-sm text-ink-muted mt-1">
                    <span x-text="counts.vistos"></span> vistos · <span x-text="counts.atrapados"></span> atrapados · <span x-text="counts.total"></span> totales
                </p>
            </div>
            <template x-if="unlockAll">
                <span class="inline-flex items-center gap-2 self-start px-3 py-1.5 rounded-control bg-warning-soft text-warning-ink text-xs font-medium border border-line">
                    <span class="w-2 h-2 rounded-full bg-warning"></span>
                    Modo test: todos desbloqueados
                </span>
            </template>
        </div>

        <!-- Filter bar -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
            <div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="Filtrar pokédex por avistamiento">
                <button
                    @click="setFilter('vistos')"
                    role="tab"
                    :aria-selected="activeFilter === 'vistos'"
                    :class="tabClass('vistos')"
                    class="px-4 py-2 rounded-control text-sm font-medium transition-colors focus-ring"
                >
                    Vistos
                </button>
                <button
                    @click="setFilter('no_vistos')"
                    role="tab"
                    :aria-selected="activeFilter === 'no_vistos'"
                    :class="tabClass('no_vistos')"
                    class="px-4 py-2 rounded-control text-sm font-medium transition-colors focus-ring"
                >
                    No vistos
                </button>
                <button
                    @click="setFilter('atrapados')"
                    role="tab"
                    :aria-selected="activeFilter === 'atrapados'"
                    :class="tabClass('atrapados')"
                    class="px-4 py-2 rounded-control text-sm font-medium transition-colors focus-ring"
                >
                    Atrapados
                </button>
                <button
                    x-show="unlockAll"
                    @click="setFilter('todos')"
                    role="tab"
                    :aria-selected="activeFilter === 'todos'"
                    :class="tabClass('todos')"
                    class="px-4 py-2 rounded-control text-sm font-medium transition-colors focus-ring"
                >
                    Todos
                </button>
                <div class="relative">
                    <button
                        @click="showTypeFilter = !showTypeFilter"
                        class="px-4 py-2 bg-surface border border-line rounded-control text-sm text-ink-muted hover:bg-surface-sunken transition-colors flex items-center gap-2 focus-ring"
                        aria-haspopup="listbox"
                        :aria-expanded="showTypeFilter"
                    >
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        <span x-text="typeFilter || 'Tipo'"></span>
                        <span
                            x-show="typeFilter"
                            @click.stop="clearTypeFilter()"
                            class="ml-0.5 text-danger-ink hover:text-danger font-bold"
                            aria-label="Quitar filtro de tipo"
                            title="Quitar filtro de tipo"
                        >✕</span>
                    </button>
                    <template x-if="showTypeFilter">
                        <div class="absolute left-0 top-full mt-1 z-40 bg-surface-raised border border-line rounded-card shadow-modal p-3 w-48 max-h-64 overflow-y-auto">
                            <button
                                @click="selectType(null)"
                                class="w-full text-left px-3 py-1.5 text-sm rounded-control hover:bg-surface-sunken transition-colors focus-ring"
                                :class="typeFilter === null ? 'text-primary-ink font-medium' : 'text-ink-muted'"
                            >
                                Todos
                            </button>
                            @php
                                $tipos = $tipos ?? \App\Enums\TipoEnum::options();
                            @endphp
                            @foreach($tipos as $id => $nombre)
                            <button
                                @click="selectType('{{ $nombre }}')"
                                class="w-full text-left px-3 py-1.5 text-sm rounded-control hover:bg-surface-sunken transition-colors focus-ring"
                                :class="typeFilter === '{{ $nombre }}' ? 'text-primary-ink font-medium' : 'text-ink-muted'"
                            >
                                {{ $nombre }}
                            </button>
                            @endforeach
                        </div>
                    </template>
                </div>
                <div class="relative">
                    <button
                        @click="showEffortFilter = !showEffortFilter"
                        class="px-4 py-2 bg-surface border border-line rounded-control text-sm text-ink-muted hover:bg-surface-sunken transition-colors flex items-center gap-2 focus-ring"
                        aria-haspopup="listbox"
                        :aria-expanded="showEffortFilter"
                    >
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span x-text="effortFilter || 'Esfuerzo'"></span>
                        <span
                            x-show="effortFilter"
                            @click.stop="clearEffortFilter()"
                            class="ml-0.5 text-danger-ink hover:text-danger font-bold"
                            aria-label="Quitar filtro de esfuerzo"
                            title="Quitar filtro de esfuerzo"
                        >✕</span>
                    </button>
                    <template x-if="showEffortFilter">
                        <div class="absolute left-0 top-full mt-1 z-40 bg-surface-raised border border-line rounded-card shadow-modal p-3 w-48 max-h-64 overflow-y-auto">
                            <button
                                @click="selectEffort(null)"
                                class="w-full text-left px-3 py-1.5 text-sm rounded-control hover:bg-surface-sunken transition-colors focus-ring"
                                :class="effortFilter === null ? 'text-primary-ink font-medium' : 'text-ink-muted'"
                            >
                                Todos
                            </button>
                            @php
                                $stats = $stats ?? \App\Enums\StatEnum::options();
                            @endphp
                            @foreach($stats as $id => $nombre)
                            <button
                                @click="selectEffort('{{ $nombre }}')"
                                class="w-full text-left px-3 py-1.5 text-sm rounded-control hover:bg-surface-sunken transition-colors focus-ring"
                                :class="effortFilter === '{{ $nombre }}' ? 'text-primary-ink font-medium' : 'text-ink-muted'"
                            >
                                {{ $nombre }}
                            </button>
                            @endforeach
                        </div>
                    </template>
                </div>
            </div>
            <div class="relative flex-1 max-w-xs">
                <input
                    type="text"
                    x-model="searchQuery"
                    @input.debounce.300ms="onSearchInput()"
                    placeholder="Buscar por nombre o número..."
                    aria-label="Buscar por nombre o número"
                    class="w-full px-4 py-2 pl-10 bg-surface border border-line rounded-control text-sm text-ink placeholder:text-ink-subtle focus-ring focus:border-primary transition-colors"
                >
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-8 h-8 text-ink-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </div>

        <!-- Fetch error banner (non-blocking: the list stays visible) -->
        <template x-if="error">
            <div role="alert" class="mb-4 px-4 py-3 rounded-control bg-danger-soft text-danger-ink border border-danger/40 text-sm flex items-center justify-between gap-3">
                <span>No se pudo cargar la Pokédex. Revisa tu conexión.</span>
                <button
                    @click="resetAndFetch()"
                    class="px-3 py-1 bg-danger text-on-primary rounded-control text-xs font-medium hover:bg-danger/90 transition-colors focus-ring"
                >
                    Reintentar
                </button>
            </div>
        </template>

        <!-- Pokemon grid -->
        <div class="grid grid-cols-3 sm:grid-cols-6 lg:grid-cols-9 gap-3">
            <template x-for="pokemon in items" :key="pokemon.id">
                <div
                    @click="openDetail(pokemon)"
                    class="relative bg-surface rounded-card shadow-card border border-line overflow-hidden cursor-pointer transition-all hover:shadow-modal hover:border-primary/40 group focus-ring"
                    :class="{ 'border-success': pokemon.atrapado }"
                    role="button"
                    tabindex="0"
                    @keydown.space.prevent="openDetail(pokemon)"
                    @keydown.enter.prevent="openDetail(pokemon)"
                    :aria-label="'Pokemon #' + pokemon.id + ' ' + ((pokemon.visto || unlockAll) ? pokemon.name : 'No visto')"
                >
                    <!-- Image / placeholder -->
                    <div class="aspect-square relative bg-surface-sunken p-2">
                        <template x-if="!pokemon.visto && !unlockAll">
                            <div
                                class="w-full h-full rounded-control bg-surface-sunken flex items-center justify-center"
                                aria-hidden="true"
                            >
                                <span class="text-2xl font-bold text-ink-subtle select-none">?</span>
                            </div>
                        </template>
                        <template x-if="pokemon.visto || unlockAll">
                            <x-sprite
                                size="96"
                                wrapper-class="mx-auto"
                                class="transition-transform group-hover:scale-110"
                                x-bind:src="iconUrl(pokemon)"
                                x-bind:alt="pokemon.name"
                                x-on:error="onIconError($event, pokemon)"
                            />
                        </template>
                        <!-- Captured badge -->
                        <template x-if="pokemon.atrapado">
                            <span class="absolute top-1 right-1 px-1 py-0.5 bg-success text-on-primary text-2xs font-bold rounded">
                                Atrapado
                            </span>
                        </template>
                    </div>
                    <!-- Info -->
                    <div class="px-2 pb-2 text-center">
                        <p class="text-2xs text-ink-subtle" x-text="'#' + pokemon.id"></p>
                        <p
                            class="text-xs font-medium text-ink truncate"
                            :class="{ 'text-ink-subtle': !pokemon.visto && !unlockAll }"
                            x-text="(pokemon.visto || unlockAll) ? pokemon.name : '???'"
                        ></p>
                        <template x-if="pokemon.habitat && (pokemon.visto || unlockAll)">
                            <p class="text-2xs text-primary-ink truncate" x-text="pokemon.habitat"></p>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <!-- Loading more -->
        <div x-show="loading && items.length > 0" class="flex justify-center py-6" aria-busy="true">
            <div class="animate-spin rounded-full h-8 w-8 border-4 border-primary border-t-transparent"></div>
        </div>

        <!-- No more pages -->
        <p
            x-show="lastPage !== null && page >= lastPage && items.length > 0"
            class="text-center text-sm text-ink-subtle py-6"
        >
            No hay más Pokémon
        </p>

        <!-- Infinite scroll sentinel -->
        <div x-ref="sentinel" class="h-2" aria-hidden="true"></div>

        <!-- Empty state -->
        <template x-if="!loading && !error && items.length === 0">
            <div class="text-center py-16">
                <svg class="w-16 h-16 mx-auto text-ink-subtle mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <p class="text-ink-muted" x-text="emptyMessage"></p>
            </div>
        </template>

    <!-- Detail Modal -->
    <template x-if="showModal && selectedPokemon">
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="pokedex-modal-title"
            @keydown.escape.window="closeDetail()"
        >
            <!-- Backdrop -->
            <div class="absolute inset-0 scrim" @click="closeDetail()"></div>
            <!-- Modal content -->
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <!-- Close button -->
                <button
                    @click="closeDetail()"
                    class="absolute top-3 right-3 z-10 w-8 h-8 flex items-center justify-center rounded-full bg-surface-sunken text-ink-muted hover:bg-line transition-colors focus-ring"
                    aria-label="Cerrar"
                >
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                <!-- Header -->
                <div class="p-6 pb-4 text-center border-b border-line">
                    <template x-if="!selectedPokemon.visto && !unlockAll">
                        <div
                            class="w-32 h-32 rounded-panel bg-surface-sunken flex items-center justify-center mx-auto mb-3"
                            aria-hidden="true"
                        >
                            <span class="text-3xl font-bold text-ink-subtle select-none">?</span>
                        </div>
                    </template>
                    <template x-if="selectedPokemon.visto || unlockAll">
                        <x-sprite
                            size="128"
                            wrapper-class="mx-auto mb-3"
                            loading="eager"
                            x-bind:src="iconUrl(selectedPokemon)"
                            x-bind:alt="selectedPokemon.name"
                            x-on:error="onIconError($event, selectedPokemon)"
                        />
                    </template>
                    <p class="text-xs text-ink-subtle" x-text="'#' + selectedPokemon.id"></p>
                    <h2 id="pokedex-modal-title" class="text-xl font-bold text-ink" x-text="(selectedPokemon.visto || unlockAll) ? selectedPokemon.name : '???'"></h2>
                    <template x-if="selectedPokemon.atrapado">
                        <span class="inline-block mt-2 px-3 py-1 bg-success-soft text-success-ink text-xs font-medium rounded-full">
                            Atrapado
                        </span>
                    </template>
                </div>

                <!-- Detail body -->
                <div class="p-6 space-y-4" :aria-busy="detailLoading ? 'true' : 'false'">
                    <!-- Loading skeleton -->
                    <template x-if="detailLoading">
                        <div class="space-y-4">
                            <div class="h-6 bg-surface-sunken rounded animate-pulse"></div>
                            <div class="space-y-2">
                                <div class="h-2 bg-surface-sunken rounded animate-pulse"></div>
                                <div class="h-2 bg-surface-sunken rounded animate-pulse"></div>
                            </div>
                            <div class="h-6 bg-surface-sunken rounded animate-pulse"></div>
                        </div>
                    </template>

                    <!-- Error with retry -->
                    <template x-if="detailError">
                        <div class="text-center py-6" role="alert">
                            <p class="text-danger-ink text-sm mb-3">No se pudo cargar el detalle de este Pokémon.</p>
                            <button
                                @click="retryDetail()"
                                class="px-4 py-2 bg-danger text-on-primary rounded-control text-sm font-medium hover:bg-danger/90 transition-colors focus-ring"
                            >
                                Reintentar
                            </button>
                        </div>
                    </template>

                    <!-- Not viewed message (no fetch for unseen pokemon) -->
                    <template x-if="!detailLoading && !detailError && !selectedPokemon.visto && !unlockAll">
                        <div class="text-center py-6">
                            <p class="text-ink-subtle italic">Este Pokémon aún no ha sido avistado.</p>
                        </div>
                    </template>

                    <!-- Detail content -->
                    <template x-if="!detailLoading && !detailError && (selectedPokemon.visto || unlockAll) && detail">
                        <!-- Types -->
                        <template x-if="typePills.length > 0">
                            <div>
                                <h3 class="text-xs font-semibold text-ink-muted uppercase tracking-wide mb-2">Tipo</h3>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="t in typePills" :key="t.slug + '-' + t.id">
                                        <span
                                            class="tipo-badge shrink-0 py-0.5 pl-0 pr-2"
                                            :style="'--type-color: ' + tipoBadge(t.slug).color"
                                        >
                                            <img
                                                :src="t.icon"
                                                alt=""
                                                class="w-7 h-7 object-contain"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                            <span x-text="t.label"></span>
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Familia evolutiva -->
                        <template x-if="detail.family?.members?.length">
                            <div>
                                <h3 class="text-xs font-semibold text-ink-muted uppercase tracking-wide mb-2">Familia evolutiva</h3>
                                <div class="space-y-3">
                                    <template x-for="etapa in familyStages" :key="etapa.stage">
                                        <div>
                                            <p class="text-3xs font-medium text-ink-subtle mb-1.5" x-text="'Etapa ' + etapa.stage"></p>
                                            <div class="flex flex-wrap gap-2">
                                                <template x-for="member in etapa.members" :key="member.id">
                                                    <div
                                                        class="flex items-center gap-2 rounded-control border px-2 py-1.5"
                                                        :class="member.is_current ? 'border-primary bg-primary-soft' : 'border-line bg-surface'"
                                                        :aria-current="member.is_current ? 'true' : null"
                                                    >
                                                        <x-sprite
                                                            size="64"
                                                            x-bind:src="member.icon"
                                                            x-bind:alt="nombrePokemon(member.name)"
                                                        />
                                                        <div class="min-w-0">
                                                            <p class="text-xs font-medium text-ink truncate" x-text="nombrePokemon(member.name)"></p>
                                                            <div class="flex items-center gap-1.5">
                                                                <span x-show="member.minimum_level" class="text-2xs text-ink-muted" x-text="'Nv ' + member.minimum_level"></span>
                                                                <span x-show="member.is_current" class="text-2xs font-semibold text-primary-ink">Actual</span>
                                                            </div>
                                                            <p x-show="familyParentName(member)" class="text-2xs text-ink-subtle" x-text="'Evoluciona de ' + familyParentName(member)"></p>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Stats bars -->
                        <template x-if="detail.stats && detail.stats.length > 0">
                            <div>
                                <h3 class="text-xs font-semibold text-ink-muted uppercase tracking-wide mb-2">Estadísticas</h3>
                                <div class="space-y-2">
                                    <template x-for="stat in detail.stats" :key="stat.name">
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-ink-muted w-20 text-right" x-text="stat.name"></span>
                                            <div class="flex-1 h-2 bg-surface-sunken rounded-full overflow-hidden">
                                                <div
                                                    class="h-full rounded-full transition-all duration-500"
                                                    :class="getStatColor(stat.name)"
                                                    :style="'width: ' + Math.min((stat.value / 255) * 100, 100) + '%'"
                                                ></div>
                                            </div>
                                            <span class="text-xs font-medium text-ink w-8" x-text="stat.value"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Ubicación -->
                        <div>
                            <h3 class="text-xs font-semibold text-ink-muted uppercase tracking-wide mb-2">Ubicación</h3>
                            <template x-if="detail.location">
                                <div class="rounded-control border border-line overflow-hidden">
                                    <template x-if="detail.location.province_name">
                                        <p class="px-3 pt-2 text-3xs uppercase tracking-wide text-ink-subtle" x-text="detail.location.province_name"></p>
                                    </template>
                                    <img
                                        :src="detail.location.habitat_image"
                                        :alt="'Hábitat ' + detail.location.habitat_name"
                                        loading="lazy"
                                        decoding="async"
                                        class="w-full h-24 object-cover"
                                    >
                                    <div class="p-3 flex items-center justify-between gap-3">
                                        <p class="text-sm font-medium text-ink" x-text="detail.location.habitat_name"></p>
                                        <a
                                            :href="detail.location.url"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-control bg-primary text-on-primary text-xs font-medium hover:bg-primary-hover transition-colors focus-ring"
                                        >
                                            Ir al hábitat
                                        </a>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!detail.location">
                                <p class="text-sm text-ink-muted italic">Sin hábitat asignado.</p>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>

@push('scripts')
@include('partials.tipos-badge-script')
<script>
function pokedexApp() {
    return {
        items: [],
        activeFilter: 'vistos',
        // TEMPORAL: modo QA para ver todos los Pokémon/hábitats sin avistar.
        unlockAll: @json($unlockAll ?? false),
        typeFilter: null,
        showTypeFilter: false,
        effortFilter: null,
        showEffortFilter: false,
        searchQuery: '',
        showModal: false,
        selectedPokemon: null,
        detail: null,
        detailLoading: false,
        detailError: false,
        page: 1,
        lastPage: null,
        loading: false,
        error: false,
        counts: { total: 0, vistos: 0, atrapados: 0, no_vistos: 0 },
        controller: null,
        detailController: null,
        observer: null,
        clickHandler: null,

        get emptyMessage() {
            if (this.searchQuery.trim() || this.typeFilter || this.effortFilter) {
                return 'No se encontraron Pokémon';
            }
            if (this.activeFilter === 'atrapados') {
                return 'No has atrapado ningún Pokémon';
            }
            if (this.activeFilter === 'no_vistos') {
                return 'No hay Pokémon sin avistar';
            }
            if (this.activeFilter === 'todos') {
                return 'No hay Pokémon';
            }
            return 'No se encontraron Pokémon';
        },

        get canLoadMore() {
            if (this.loading || this.error) {
                return false;
            }
            // lastPage desconocido (seed inicial): asumimos que hay más si ya hay items
            if (this.lastPage === null) {
                return this.items.length > 0;
            }
            return this.page < this.lastPage;
        },

        get typePills() {
            if (!this.detail) {
                return [];
            }
            if (Array.isArray(this.detail.type_details) && this.detail.type_details.length > 0) {
                return this.detail.type_details;
            }
            const tipos = Array.isArray(this.detail.types) ? this.detail.types : [];
            return tipos.map((tipo) => {
                const badge = window.tipoBadge(tipo);
                return { id: badge.slug, label: badge.label, slug: badge.slug, icon: badge.icono };
            });
        },

        get familyStages() {
            const miembros = this.detail?.family?.members;
            if (!Array.isArray(miembros) || miembros.length === 0) {
                return [];
            }
            const etapas = new Map();
            miembros.forEach((miembro) => {
                const stage = Number(miembro.stage) || 1;
                if (!etapas.has(stage)) {
                    etapas.set(stage, []);
                }
                etapas.get(stage).push(miembro);
            });
            return Array.from(etapas, ([stage, members]) => ({ stage, members }))
                .sort((a, b) => a.stage - b.stage);
        },

        nombrePokemon(nombre) {
            if (!nombre) {
                return '';
            }
            const texto = String(nombre);
            return texto.charAt(0).toUpperCase() + texto.slice(1);
        },

        familyParentName(member) {
            if (!member || !member.parent_id) {
                return '';
            }
            const padre = (this.detail?.family?.members ?? []).find((m) => m.id === member.parent_id);
            return padre ? this.nombrePokemon(padre.name) : '';
        },

        init() {
            // Contrato del backend: $pokemons = { data: [...], meta: {...} } (o array plano durante la transición)
            const initial = @json($pokemons ?? null);
            const seed = Array.isArray(initial)
                ? initial
                : (initial && Array.isArray(initial.data) ? initial.data : []);
            // Guard defensivo: el backend entrega la página 1 SIN filtrar por la pestaña inicial;
            // filtramos aquí (por flags del item) para que la primera pintura coincida con "Vistos".
            this.items = this.unlockAll ? seed : seed.filter(p => p && p.visto);
            if (this.unlockAll) {
                // En modo test el seed llega sin filtro y arrancamos en "Todos".
                this.activeFilter = 'todos';
            }
            this.page = Number(initial?.meta?.page) || 1;
            this.lastPage = initial?.meta?.last_page != null ? Number(initial.meta.last_page) : null;
            const fallbackCounts = {
                total: this.items.length,
                vistos: this.items.length,
                atrapados: this.items.filter(p => p.atrapado).length,
                no_vistos: 0,
            };
            this.counts = @json($counts ?? null) || initial?.meta?.counts || fallbackCounts;

            // Bootstrap guard: si el seed viene SIN filtrar (contrato viejo) y la pestaña
            // activa es 'vistos' pero no hay items locales, fuerza un fetch al servidor para
            // cubrir el caso de transición. Solo se ejecuta una vez en init(); resetAndFetch()
            // no re-invoca init(), por lo que no hay riesgo de bucle.
            if (this.activeFilter === 'vistos' && this.items.length === 0) {
                const hasSightings = (this.counts?.vistos ?? 0) > 0
                    || Number(initial?.meta?.total ?? 0) > 0;
                if (hasSightings) {
                    this.resetAndFetch();
                }
            }

            // Close filter dropdowns on outside click
            this.clickHandler = (e) => {
                if (!e.target.closest('[x-data]')?.contains(e.target)) {
                    this.showTypeFilter = false;
                    this.showEffortFilter = false;
                }
            };
            document.addEventListener('click', this.clickHandler);

            this.$nextTick(() => this.setupObserver());
        },

        destroy() {
            this.observer?.disconnect();
            this.controller?.abort();
            this.detailController?.abort();
            if (this.clickHandler) {
                document.removeEventListener('click', this.clickHandler);
                this.clickHandler = null;
            }
        },

        setupObserver() {
            if (!this.$refs.sentinel) {
                return;
            }
            this.observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting && this.canLoadMore) {
                    this.fetchPage(this.page + 1);
                }
            }, { rootMargin: '300px' });
            this.observer.observe(this.$refs.sentinel);
        },

        buildParams(page) {
            const params = new URLSearchParams({
                page: String(page),
                per_page: '120',
                sort: 'id',
                order: 'asc',
            });
            if (this.searchQuery.trim()) {
                params.set('search', this.searchQuery.trim());
            }
            if (this.typeFilter) {
                params.set('filter[types]', this.typeFilter);
            }
            if (this.effortFilter) {
                params.set('filter[effort]', this.effortFilter);
            }
            if (this.activeFilter === 'vistos') {
                params.set('filter[visto]', '1');
            } else if (this.activeFilter === 'no_vistos') {
                params.set('filter[visto]', '0');
            } else if (this.activeFilter === 'atrapados') {
                params.set('filter[atrapado]', '1');
            }
            return params;
        },

        async fetchPage(page) {
            if (this.loading) {
                return;
            }
            const controller = new AbortController();
            if (this.controller) {
                this.controller.abort();
            }
            this.controller = controller;
            this.loading = true;
            this.error = false;
            try {
                const response = await fetch('/datagrid/pokemon?' + this.buildParams(page), {
                    signal: controller.signal,
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                const data = await response.json();
                if (controller !== this.controller) {
                    return;
                }
                const list = Array.isArray(data.data) ? data.data : [];
                // Dedupe por id: nunca duplicar cards entre páginas
                const known = new Set(this.items.map(p => p.id));
                list.forEach(p => {
                    if (!known.has(p.id)) {
                        this.items.push(p);
                        known.add(p.id);
                    }
                });
                this.page = Number(data.meta?.page) || page;
                this.lastPage = data.meta?.last_page != null ? Number(data.meta.last_page) : null;
                if (data.meta?.counts) {
                    this.counts = data.meta.counts;
                }
            } catch (err) {
                if (err.name !== 'AbortError' && controller === this.controller) {
                    this.error = true;
                }
            } finally {
                if (controller === this.controller) {
                    this.loading = false;
                }
            }
        },

        // Resetea paginación y estado y re-fetcha la página 1 (tab, búsqueda o tipo cambiaron)
        resetAndFetch() {
            if (this.controller) {
                this.controller.abort();
            }
            this.items = [];
            this.page = 1;
            this.lastPage = null;
            this.loading = false;
            this.error = false;
            this.fetchPage(1);
        },

        setFilter(tab) {
            if (this.activeFilter === tab) {
                return;
            }
            this.activeFilter = tab;
            this.resetAndFetch();
        },

        tabClass(tab) {
            return this.activeFilter === tab
                ? 'bg-primary text-on-primary'
                : 'bg-surface text-ink-muted hover:bg-surface-sunken border border-line';
        },

        onSearchInput() {
            this.resetAndFetch();
        },

        selectType(type) {
            this.typeFilter = type;
            this.showTypeFilter = false;
            this.resetAndFetch();
        },

        clearTypeFilter() {
            this.typeFilter = null;
            this.resetAndFetch();
        },

        selectEffort(label) {
            this.effortFilter = label;
            this.showEffortFilter = false;
            this.resetAndFetch();
        },

        clearEffortFilter() {
            this.effortFilter = null;
            this.resetAndFetch();
        },

        iconUrl(pokemon) {
            return pokemon.icon || '/images/iconos_webp/' + pokemon.id + '.webp';
        },

        // Fallback de icono: shiny -> normal -> 0.webp -> ocultar
        onIconError(e, pokemon) {
            const img = e.target;
            const normal = '/images/iconos_webp/' + pokemon.id + '.webp';
            const placeholder = '/images/iconos_webp/0.webp';
            if (img.src.includes('/shiny/')) {
                img.src = normal;
            } else if (!img.src.endsWith(placeholder)) {
                img.src = placeholder;
            } else {
                img.style.display = 'none';
            }
        },

        openDetail(pokemon) {
            this.selectedPokemon = pokemon;
            this.detail = null;
            this.detailLoading = false;
            this.detailError = false;
            this.showModal = true;
            if (pokemon.visto || this.unlockAll) {
                this.detailLoading = true;
                this.loadDetail(pokemon.id);
            }
            // Pokémon no visto y modo test desactivado: sin fetch bajo demanda.
        },

        closeDetail() {
            this.showModal = false;
            this.selectedPokemon = null;
            this.detail = null;
            this.detailLoading = false;
            this.detailError = false;
        },

        async loadDetail(id) {
            if (this.detailController) {
                this.detailController.abort();
            }
            const controller = new AbortController();
            this.detailController = controller;
            this.detailLoading = true;
            this.detailError = false;
            this.detail = null;
            try {
                const unlock = this.unlockAll ? '?unlock=1' : '';
                const response = await fetch('/datagrid/pokemon/' + id + '/detalle' + unlock, {
                    signal: controller.signal,
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                const data = await response.json();
                if (controller !== this.detailController) {
                    return;
                }
                this.detail = data;
            } catch (err) {
                if (err.name !== 'AbortError' && controller === this.detailController) {
                    this.detailError = true;
                }
            } finally {
                if (controller === this.detailController) {
                    this.detailLoading = false;
                }
            }
        },

        retryDetail() {
            if (this.selectedPokemon) {
                this.loadDetail(this.selectedPokemon.id);
            }
        },

        getStatColor(statName) {
            const colors = {
                'PS (HP)': 'bg-success',
                'Ataque': 'bg-danger',
                'Defensa': 'bg-primary',
                'Ataque Especial': 'bg-info',
                'Defensa Especial': 'bg-warning',
                'Velocidad': 'bg-ink-subtle',
            };
            return colors[statName] || 'bg-ink-subtle';
        },
    };
}
</script>
@endpush
@endsection
