@extends('layouts.app')

@section('title', 'Pokédex')

@section('content')
<div x-data="pokedexApp()" x-init="init()" @combobox-seleccionado.window="onComboboxSeleccionado($event)">
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
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
            <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filtros de habilidad y movimiento">
                <x-ui.combobox
                    name="ability"
                    label="Habilidad"
                    :options="$habilidades"
                />
                <x-ui.combobox
                    name="move"
                    label="Movimiento"
                    :options="$movimientos"
                    placeholder="Buscar movimiento…"
                    ayuda="Incluye todos los movimientos del learnset, no solo los aprendidos por nivel"
                />
            </div>
            <div class="relative flex-1 max-w-xs">
                <input
                    type="text"
                    x-model="searchQuery"
                    @input.debounce.300ms="onSearchInput()"
                    placeholder="Buscar por nombre..."
                    aria-label="Buscar por nombre"
                    class="w-full px-4 py-2 pl-10 bg-surface border border-line rounded-control text-sm text-ink placeholder:text-ink-subtle focus-ring focus:border-primary transition-colors"
                >
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
        <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 2xl:grid-cols-12 gap-2 sm:gap-3">
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
                    <div class="aspect-square relative bg-surface-sunken p-2 flex items-center justify-center">
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
                                size="128"
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
                    <div class="px-2 pb-2 pt-1 text-center min-h-[3.5rem]">
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
            <div class="relative flex max-h-[94vh] w-[90vw] max-w-[90vw] flex-col overflow-hidden rounded-panel bg-surface-raised shadow-modal">
                <!-- Close button -->
                <button
                    @click="closeDetail()"
                    class="absolute top-3 right-3 z-10 w-8 h-8 flex items-center justify-center rounded-full bg-surface-sunken text-ink-muted hover:bg-line transition-colors focus-ring"
                    aria-label="Cerrar"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                <!-- Header -->
                <div class="shrink-0 border-b border-line p-5 pr-12 sm:p-6 sm:pr-12">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-6">
                        <!-- Identidad -->
                        <div class="flex min-w-0 flex-1 items-start gap-4">
                            <div class="w-20 h-20 sm:w-32 sm:h-32 shrink-0 rounded-card bg-surface-sunken flex items-center justify-center overflow-hidden">
                                <template x-if="!selectedPokemon.visto && !unlockAll">
                                    <span class="text-2xl font-bold text-ink-subtle select-none" aria-hidden="true">?</span>
                                </template>
                                <template x-if="selectedPokemon.visto || unlockAll">
                                    <x-sprite
                                        size="128"
                                        loading="eager"
                                        x-bind:src="iconUrl(selectedPokemon)"
                                        x-bind:alt="selectedPokemon.name"
                                        x-on:error="onIconError($event, selectedPokemon)"
                                    />
                                </template>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-ink-subtle" x-text="'#' + selectedPokemon.id"></p>
                                <h2 id="pokedex-modal-title" class="truncate text-xl font-bold text-ink" x-text="(selectedPokemon.visto || unlockAll) ? selectedPokemon.name : '???'"></h2>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <template x-for="t in typePills" :key="t.slug + '-' + t.id">
                                        <span
                                            class="tipo-badge shrink-0 py-0.5 pl-0 pr-2"
                                            :style="'--type-color: ' + tipoBadge(t.slug).color"
                                        >
                                            <img
                                                :src="t.icon"
                                                alt=""
                                                class="w-6 h-6 object-contain"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                            <span x-text="t.label"></span>
                                        </span>
                                    </template>
                                    <template x-if="selectedPokemon.atrapado">
                                        <span class="inline-block px-3 py-1 bg-success-soft text-success-ink text-xs font-medium rounded-full">
                                            Atrapado
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Ubicación -->
                        <div
                            x-show="detail && !detailLoading && (selectedPokemon.visto || unlockAll)"
                            :class="ubicacionesDetalle.length ? 'lg:w-72' : ''"
                            class="flex flex-col gap-3 lg:ml-auto lg:mr-1 lg:shrink-0"
                        >
                            <template x-for="(loc, index) in ubicacionesDetalle" :key="'ubicacion-' + loc.habitat_id">
                                <div class="flex min-w-0 items-start gap-3">
                                    <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                                        <p
                                            class="truncate text-3xs uppercase tracking-wide text-ink-subtle"
                                            x-show="loc.province_name"
                                            x-text="loc.province_name"
                                        ></p>
                                        <p class="truncate text-sm font-semibold text-ink" x-text="loc.habitat_name"></p>
                                        <template x-if="index === 0">
                                            <a
                                                :href="loc.url"
                                                :aria-label="'Ir al hábitat ' + loc.habitat_name"
                                                :title="'Ir al hábitat ' + loc.habitat_name"
                                                class="inline-flex items-center gap-1 self-start rounded-control bg-primary px-2.5 py-1.5 text-xs font-medium text-on-primary transition-colors hover:bg-primary-hover focus-ring"
                                            >
                                                Ir al hábitat
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </a>
                                        </template>
                                        <template x-if="index > 0">
                                            <a
                                                :href="loc.url"
                                                :aria-label="'Ir al hábitat ' + loc.habitat_name"
                                                :title="'Ir al hábitat ' + loc.habitat_name"
                                                class="inline-flex items-center gap-1 self-start text-2xs font-medium text-primary-ink hover:underline focus-ring"
                                            >
                                                Ir al hábitat
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </a>
                                        </template>
                                    </div>
                                    <template x-if="index === 0">
                                        <img
                                            :src="loc.habitat_image"
                                            alt=""
                                            loading="lazy"
                                            decoding="async"
                                            class="w-24 max-w-32 sm:w-28 lg:w-32 shrink-0 aspect-[4/3] rounded-card border border-line bg-surface-sunken object-cover"
                                        >
                                    </template>
                                </div>
                            </template>
                            <p x-show="ubicacionesDetalle.length === 0" class="text-xs italic text-ink-muted">Sin hábitat asignado.</p>
                        </div>
                    </div>
                </div>
                <!-- Detail body -->
                <div class="min-h-0 flex-1 overflow-y-auto p-5 sm:p-6" :aria-busy="detailLoading ? 'true' : 'false'">
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
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
                                <!-- Columna izquierda: familia y habilidades -->
                                <div class="space-y-4 md:col-span-3">
                                    <!-- Familia evolutiva -->
                                    <template x-if="detail.family?.members?.length">
                                        <div class="rounded-panel border border-line bg-surface-sunken p-4">
                                            <h3 class="text-sm font-bold uppercase tracking-wide text-ink-muted mb-2">Familia evolutiva</h3>
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

                                    <!-- Habilidades -->
                                    <template x-if="(detail.abilities || []).length > 0">
                                        <div class="rounded-panel border border-line bg-surface-sunken p-4">
                                            <h3 class="text-sm font-bold uppercase tracking-wide text-ink-muted mb-2">Habilidades</h3>
                                            <div class="space-y-3">
                                                <template x-for="hab in detail.abilities" :key="'hab-' + hab.id">
                                                    <div>
                                                        <p class="text-sm font-semibold text-ink" x-text="hab.name"></p>
                                                        <template x-if="hab.effect">
                                                            <p class="text-xs text-ink-muted line-clamp-3" :title="hab.effect" x-text="hab.effect"></p>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <!-- Columna derecha: stats y especie -->
                                <div class="space-y-4 md:col-span-2">
                                    <!-- Stats base -->
                                    <template x-if="detail.stats && detail.stats.length > 0">
                                        <div class="rounded-panel border border-line bg-surface-sunken p-4">
                                            <h3 class="text-sm font-bold uppercase tracking-wide text-ink-muted mb-2">Estadísticas base</h3>
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

                                    <!-- Datos de especie -->
                                    <template x-if="detail.species && (detail.species.height_m != null || detail.species.weight_kg != null || detail.species.base_experience != null || detail.species.capture_rate != null)">
                                        <div class="rounded-panel border border-line bg-surface-sunken p-4">
                                            <h3 class="text-sm font-bold uppercase tracking-wide text-ink-muted mb-2">Datos de especie</h3>
                                            <dl class="grid grid-cols-2 gap-x-3 gap-y-2">
                                                <template x-if="detail.species.height_m != null">
                                                    <div>
                                                        <dt class="text-2xs uppercase tracking-wide text-ink-subtle">Altura</dt>
                                                        <dd class="text-sm font-medium text-ink" x-text="detail.species.height_m + ' m'"></dd>
                                                    </div>
                                                </template>
                                                <template x-if="detail.species.weight_kg != null">
                                                    <div>
                                                        <dt class="text-2xs uppercase tracking-wide text-ink-subtle">Peso</dt>
                                                        <dd class="text-sm font-medium text-ink" x-text="detail.species.weight_kg + ' kg'"></dd>
                                                    </div>
                                                </template>
                                                <template x-if="detail.species.base_experience != null">
                                                    <div>
                                                        <dt class="text-2xs uppercase tracking-wide text-ink-subtle">Exp. base</dt>
                                                        <dd class="text-sm font-medium text-ink" x-text="detail.species.base_experience"></dd>
                                                    </div>
                                                </template>
                                                <template x-if="detail.species.capture_rate != null">
                                                    <div>
                                                        <dt class="text-2xs uppercase tracking-wide text-ink-subtle">Ratio de captura</dt>
                                                        <dd class="text-sm font-medium text-ink" x-text="detail.species.capture_rate"></dd>
                                                    </div>
                                                </template>
                                            </dl>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Movimientos por nivel (ancho completo) -->
                            <div class="rounded-panel border border-line bg-surface-sunken p-4">
                                <h3 class="text-sm font-bold uppercase tracking-wide text-ink-muted mb-2">Movimientos por nivel</h3>
                                <template x-if="(detail.moves || []).length > 0">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead>
                                                <tr class="border-b border-line text-2xs uppercase tracking-wide text-ink-subtle">
                                                    <th scope="col" class="py-1.5 pr-3 font-semibold">Nv.</th>
                                                    <th scope="col" class="py-1.5 pr-3 font-semibold">Movimiento</th>
                                                    <th scope="col" class="py-1.5 pr-3 font-semibold">Tipo</th>
                                                    <th scope="col" class="hidden py-1.5 pr-3 font-semibold sm:table-cell">Categoría</th>
                                                    <th scope="col" class="py-1.5 pr-3 text-right font-semibold">Pot.</th>
                                                    <th scope="col" class="hidden py-1.5 text-right font-semibold sm:table-cell">Prec.</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="(mov, index) in detail.moves" :key="mov.slug + '-' + index">
                                                    <tr class="border-b border-line/60 last:border-0">
                                                        <td class="py-1.5 pr-3 text-ink-muted" x-text="mov.level"></td>
                                                        <td class="py-1.5 pr-3 font-medium text-ink">
                                                            <span x-text="mov.name"></span>
                                                            <template x-if="mov.objetivo_multiple">
                                                                <span
                                                                    class="ml-1 inline-flex items-center rounded-full border px-1.5 py-0.5 align-middle text-2xs font-bold"
                                                                    :class="mov.objetivo_incluye_aliados
                                                                        ? 'border-danger bg-danger-soft text-danger-ink'
                                                                        : (mov.objetivo_es_fila ? 'border-info bg-info-soft text-info-ink' : 'border-warning bg-warning-soft text-warning-ink')"
                                                                    :title="mov.objetivo_label"
                                                                    x-text="mov.objetivo_corto"
                                                                ></span>
                                                            </template>
                                                        </td>
                                                        <td class="py-1.5 pr-3">
                                                            <span
                                                                class="tipo-badge shrink-0 py-0.5 pl-0 pr-2"
                                                                :style="'--type-color: ' + tipoBadge(mov.tipo_slug).color"
                                                            >
                                                                <img
                                                                    :src="tipoBadge(mov.tipo_slug).icono"
                                                                    alt=""
                                                                    class="w-4 h-4 object-contain"
                                                                    loading="lazy"
                                                                    decoding="async"
                                                                >
                                                                <span x-text="mov.tipo_nombre"></span>
                                                            </span>
                                                        </td>
                                                        <td class="hidden py-1.5 pr-3 text-ink-muted sm:table-cell" x-text="mov.category_label"></td>
                                                        <td class="py-1.5 pr-3 text-right text-ink-muted" x-text="mov.base_power ?? '—'"></td>
                                                        <td class="hidden py-1.5 text-right text-ink-muted sm:table-cell" x-text="mov.accuracy != null ? mov.accuracy + '%' : '—'"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </template>
                                <template x-if="!(detail.moves || []).length">
                                    <p class="text-sm text-ink-muted italic">Sin movimientos por nivel.</p>
                                </template>
                            </div>

                            {{-- TASK-064 --}}
                            <template x-if="qaAcciones && detail">
                                <div class="rounded-panel border border-dashed border-warning bg-warning-soft p-4 space-y-3">
                                    <h3 class="text-sm font-bold uppercase tracking-wide text-warning-ink">Acciones QA</h3>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-ui.button
                                            variant="success"
                                            size="sm"
                                            x-on:click="reclutarQa()"
                                            x-bind:disabled="qaProcesando"
                                        >
                                            Reclutar
                                        </x-ui.button>
                                        <template x-if="detail.family && detail.family.evolution_chain_id">
                                            <template x-for="cantidad in qaCantidades" :key="'qa-caramelo-' + cantidad">
                                                <x-ui.button
                                                    variant="secondary"
                                                    size="sm"
                                                    x-on:click="carameloFamiliaQa(cantidad)"
                                                    x-bind:disabled="qaProcesando"
                                                >
                                                    +<span x-text="cantidad"></span> familia
                                                </x-ui.button>
                                            </template>
                                        </template>
                                    </div>
                                    <p
                                        x-cloak
                                        x-show="qaFeedback.mensaje"
                                        role="status"
                                        class="text-sm font-medium"
                                        :class="qaFeedback.tipo === 'error' ? 'text-danger-ink' : 'text-success-ink'"
                                        x-text="qaFeedback.mensaje"
                                    ></p>
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
@include('partials.tipos-badge-script')
@include('partials.combobox-filtro-script')
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
        abilityFilterId: null,
        moveFilterId: null,
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
            if (this.searchQuery.trim() || this.typeFilter || this.effortFilter || this.abilityFilterId || this.moveFilterId) {
                return 'No se encontraron Pokémon con los filtros aplicados';
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

        get ubicacionesDetalle() {
            if (!this.detail) {
                return [];
            }
            if (Array.isArray(this.detail.locations) && this.detail.locations.length > 0) {
                return this.detail.locations;
            }
            return this.detail.location ? [this.detail.location] : [];
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
            if (this.abilityFilterId) {
                params.set('filter[ability]', String(this.abilityFilterId));
            }
            if (this.moveFilterId) {
                params.set('filter[move]', String(this.moveFilterId));
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

        onComboboxSeleccionado(event) {
            const nombre = event?.detail?.name;
            const id = event?.detail?.id ?? null;
            if (nombre === 'ability') {
                this.abilityFilterId = id;
            } else if (nombre === 'move') {
                this.moveFilterId = id;
            } else {
                return;
            }
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
            // TASK-064
            this.qaFeedback = { tipo: '', mensaje: '' };
            this.qaProcesando = false;
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

        // TASK-064 — estado y acciones QA del modal
        qaAcciones: @json(\App\Support\Qa\ModoQa::accionesPokedexHabilitadas($unlockAll ?? false)),
        qaCantidades: @json(\App\Support\Qa\AjustesQa::CANTIDADES_CARAMELOS),
        qaProcesando: false,
        qaFeedback: { tipo: '', mensaje: '' },

        async _postQa(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            let data = null;
            try {
                data = await response.json();
            } catch (err) {
                data = null;
            }

            if (!response.ok) {
                const error = new Error((data && data.message) || 'No se pudo completar la acción.');
                error.status = response.status;
                throw error;
            }

            return data || {};
        },

        async reclutarQa() {
            if (!this.qaAcciones || !this.selectedPokemon || this.qaProcesando) {
                return;
            }
            this.qaProcesando = true;
            this.qaFeedback = { tipo: '', mensaje: '' };
            try {
                const data = await this._postQa('/pokedex/qa/reclutar', {
                    pokemon_id: this.selectedPokemon.id,
                });
                if (this.selectedPokemon) {
                    this.selectedPokemon.atrapado = true;
                }
                this.qaFeedback = { tipo: 'success', mensaje: data.mensaje || 'Pokémon reclutado.' };
            } catch (err) {
                this.qaFeedback = { tipo: 'error', mensaje: err?.message || 'No se pudo reclutar el Pokémon.' };
            } finally {
                this.qaProcesando = false;
            }
        },

        async carameloFamiliaQa(cantidad) {
            if (!this.qaAcciones || !this.selectedPokemon || this.qaProcesando) {
                return;
            }
            this.qaProcesando = true;
            this.qaFeedback = { tipo: '', mensaje: '' };
            try {
                const data = await this._postQa('/pokedex/qa/caramelo-familia', {
                    pokemon_id: this.selectedPokemon.id,
                    cantidad: Number(cantidad),
                });
                this.qaFeedback = {
                    tipo: 'success',
                    mensaje: data.mensaje || ('+' + cantidad + ' caramelos de familia.'),
                };
            } catch (err) {
                this.qaFeedback = { tipo: 'error', mensaje: err?.message || 'No se pudieron añadir los caramelos.' };
            } finally {
                this.qaProcesando = false;
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
