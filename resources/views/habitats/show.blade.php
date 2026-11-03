@extends('layouts.app')

@section('title', 'Hábitat - ' . ($habitat['name'] ?? ''))

@section('content')
<div x-data="habitatShow()" x-init="init()">
        <!-- Main: Left 1/5 (back/image/construction) + Right 4/5 (explorations/teams/levels) -->
        <div class="grid lg:grid-cols-5 gap-6">
            <!-- Left 1/5: Back, Image (auto size) with title overlay, stacked construction buttons -->
            <div class="lg:col-span-1 space-y-4">
                <!-- Back card (visual) -->
                <a href="/habitats" aria-label="Volver a hábitats" title="Volver a hábitats"
                   class="card-surface p-3 flex items-center gap-3 cursor-pointer transition-all hover:shadow-md hover:border-primary group">
                    <span class="w-8 shrink-0 flex justify-center">
                        <svg class="w-5 h-5 text-ink-muted group-hover:text-primary-ink transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </span>
                    <span class="flex-1 text-sm font-medium text-ink group-hover:text-primary-ink transition-colors">Volver a hábitats</span>
                </a>

                <!-- Image card with title overlay -->
                <div class="card-surface relative p-3 overflow-hidden w-fit min-h-[6rem] flex items-center justify-center">
                    @if(!empty($habitat['image']))
                    <img
                        src="{{ $habitat['image'] }}"
                        alt="{{ $habitat['name'] }}"
                        class="w-auto h-auto max-w-full rounded-control"
                        onerror="this.style.display='none'"
                    >
                    @else
                    <div class="w-48 h-32 rounded-control bg-surface-sunken flex items-center justify-center">
                        <span class="text-4xl">🏔️</span>
                    </div>
                    @endif
                    <h1 class="absolute bottom-0 inset-x-0 px-3 py-2 text-on-primary text-sm font-bold truncate rounded-b-lg"
                        style="background: linear-gradient(to top, var(--color-scrim), transparent)"
                        title="{{ $habitat['name'] }}">{{ $habitat['name'] }}</h1>
                </div>

                <!-- Construction buttons (stacked, full width) -->
                @php
                    $bloqueadoConstruccion = $exploracionesActivas && $exploracionesActivas->count() > 0;
                @endphp
                <div class="space-y-3">
                    <!-- Combate de Ruta (modo por defecto) -->
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : 'setModoRuta()' }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="w-full px-4 py-3 bg-surface border border-line rounded-card text-sm font-medium text-ink transition-colors flex items-center gap-3 {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : '' }}"
                        :class="modo === 'ruta' ? 'ring-2 ring-primary border-primary bg-primary-soft' : 'hover:bg-surface-sunken'"
                    >
                        <span class="w-8 shrink-0 flex justify-center">
                            <svg class="w-6 h-6 text-danger-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </span>
                        <span class="flex-1 text-sm text-left font-medium text-ink">Combate de ruta</span>
                    </button>
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : 'openGranjaModal()' }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="w-full px-4 py-3 bg-surface border border-line rounded-card text-sm font-medium text-ink transition-colors flex items-center gap-3 {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : 'hover:bg-surface-sunken' }}"
                    >
                        <span class="w-8 shrink-0 flex justify-center">
                            <img src="/images/misc/farm.webp" loading="lazy" decoding="async" class="w-8 h-8 object-contain" alt="Granjas">
                        </span>
                        <span class="flex-1 text-sm text-left font-medium text-ink">Granjas</span>
                    </button>
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : "toggleEntrenadores()" }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="w-full px-4 py-3 bg-surface border border-line rounded-card text-sm font-medium text-ink transition-colors flex items-center gap-3 {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : '' }}"
                        :class="modo === 'entrenadores' ? 'ring-2 ring-primary border-primary bg-primary-soft' : 'hover:bg-surface-sunken'"
                    >
                        <span class="w-8 shrink-0 flex justify-center">
                            <img src="/images/misc/trainer.webp" loading="lazy" decoding="async" class="w-8 h-8 object-contain" alt="Entrenadores">
                        </span>
                        <span class="flex-1 text-sm text-left font-medium text-ink">Entrenadores</span>
                    </button>
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : "toggleExploraciones()" }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="w-full px-4 py-3 bg-surface border border-line rounded-card text-sm font-medium text-ink transition-colors flex items-center gap-3 {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : '' }}"
                        :class="modo === 'pokemon' ? 'ring-2 ring-primary border-primary bg-primary-soft' : 'hover:bg-surface-sunken'"
                    >
                        <span class="w-8 shrink-0 flex justify-center">
                            <svg class="w-6 h-6 text-warning-ink" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6z"/>
                            </svg>
                        </span>
                        <span class="flex-1 text-sm text-left font-medium text-ink">Exploraciones</span>
                    </button>
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : 'openMazmorraModal()' }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="w-full px-4 py-3 bg-surface border border-line rounded-card text-sm font-medium text-ink transition-colors flex items-center gap-3 {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : 'hover:bg-surface-sunken' }}"
                    >
                        <span class="w-8 shrink-0 flex justify-center">
                            <img src="/images/misc/raid.webp" loading="lazy" decoding="async" class="w-8 h-8 object-contain" alt="Mazmorras">
                        </span>
                        <span class="flex-1 text-sm text-left font-medium text-ink">Mazmorras</span>
                    </button>
                    <button
                        @click="openGestionModal()"
                        class="w-full px-4 py-3 bg-primary text-on-primary rounded-card text-sm font-bold transition-colors hover:bg-primary-hover flex items-center gap-3 uppercase tracking-wide"
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
                <p class="text-xs text-center text-warning-ink">
                    No disponible durante exploraciones activas
                </p>
                @endif
            </div>

            <!-- Right 4/5: Explorations, Teams, Levels -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Active Explorations List -->
                @if($exploracionesActivas && $exploracionesActivas->count() > 0)
                <div class="card-surface">
                    <div class="px-4 py-3 border-b border-line flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-ink flex items-center gap-2">
                            <svg class="w-4 h-4 text-warning-ink" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6z"/>
                            </svg>
                            Exploraciones activas
                        </h3>
                        <span class="px-2 py-0.5 bg-warning-soft text-warning-ink text-xs font-bold rounded-full">
                            {{ $exploracionesActivas->count() }}
                        </span>
                    </div>
                    <div class="divide-y divide-line">
                        @foreach($exploracionesActivas as $exp)
                        <div class="px-4 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-primary-soft flex items-center justify-center">
                                    <svg class="w-4 h-4 text-primary-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    @php
                                        $nombreReclutado = $exp->reclutado?->nombre;
                                        if (! filled($nombreReclutado)) {
                                            $nombreReclutado = $exp->reclutado?->pokemon?->name;
                                        }
                                        if (! filled($nombreReclutado)) {
                                            $nombreReclutado = 'Reclutado eliminado';
                                        }
                                    @endphp
                                    <p class="text-sm font-medium text-ink">{{ $nombreReclutado }}</p>
                                    <p class="text-xs text-ink-muted">Nivel {{ $exp->nivel }}</p>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 text-2xs font-bold rounded-full uppercase
                                {{ $exp->inicio_exploracion
                                    ? 'bg-success-soft text-success-ink'
                                    : 'bg-surface-sunken text-ink-muted' }}">
                                {{ $exp->inicio_exploracion ? 'En curso' : 'Preparando' }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Teams Panel: 3-column grid of team cards (modo entrenadores / ruta) -->
                <div class="card-surface" x-show="modo === 'entrenadores' || modo === 'ruta'" x-cloak>
                    <div class="p-4">
                        <div class="grid sm:grid-cols-4 gap-4">
                            @forelse($teams as $team)
                            @php
                                // La exploración es individual: el equipo se bloquea solo
                                // si alguno de sus miembros está explorando.
                                $miembrosEnExploracion = collect($team->members)
                                    ->pluck('pokemon_id')
                                    ->filter()
                                    ->intersect($reclutadosEnExploracion ?? []);
                                $bloqueado = $miembrosEnExploracion->isNotEmpty();
                            @endphp
                            <div
                                class="rounded-control p-3 border-2 transition-all {{ $bloqueado ? 'bg-surface-sunken border-line opacity-60 cursor-not-allowed' : 'bg-surface-sunken cursor-pointer hover:border-line-strong' }}"
                                :class="{{ $bloqueado ? 'false' : "selectedTeamId === {$team->id} ? 'border-primary ring-1 ring-primary' : 'border-line hover:border-line-strong'" }}"
                                @if(!$bloqueado)
                                @click="selectTeam({{ $team->id }}, $el.dataset.teamName)"
                                role="button"
                                tabindex="0"
                                @keydown.space.prevent="selectTeam({{ $team->id }}, $el.dataset.teamName)"
                                @keydown.enter.prevent="selectTeam({{ $team->id }}, $el.dataset.teamName)"
                                @else
                                aria-disabled="true"
                                @endif
                                data-team-name="{{ $team->name }}"
                                aria-label="{{ $bloqueado ? 'Equipo con miembro en exploración' : 'Seleccionar equipo ' . $team->name }}"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-ink flex items-center gap-1.5">
                                        @if($bloqueado)
                                            <svg class="w-3.5 h-3.5 text-ink-muted" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                            </svg>
                                        @endif
                                        {{ $team->name }}
                                    </span>
                                    @if($bloqueado)
                                        <span class="px-2 py-0.5 bg-warning-soft text-warning-ink text-2xs font-bold rounded-full uppercase">
                                            Miembro en exploración
                                        </span>
                                    @endif
                                </div>
                                @if($bloqueado)
                                <p class="text-2xs text-ink-muted mt-1">
                                    No disponible para combatir hasta que vuelva
                                </p>
                                @endif
                                <div class="grid grid-cols-3 gap-0">
                                    @for($i = 0; $i < 3; $i++)
                                        @if(isset($team->members[$i]))
                                            <div class="text-center min-w-0">
                                                <x-sprite
                                                    :id="$team->members[$i]->reclutado->pokemon_id"
                                                    :name="$team->members[$i]->reclutado->nombre"
                                                    :shiny="$team->members[$i]->reclutado->es_shiny"
                                                    size="96"
                                                    wrapper-class="mx-auto"
                                                    :title="$team->members[$i]->reclutado->nombre"
                                                />
                                            </div>
                                        @else
                                            <div class="text-center min-w-0">
                                                <div class="w-full h-14 mx-auto rounded border-2 border-dashed border-line-strong"></div>
                                                <p class="text-2xs text-ink-subtle mt-0.5">Vacío</p>
                                            </div>
                                        @endif
                                    @endfor
                                </div>
                            </div>
                            @empty
                            <div class="sm:col-span-3 text-center py-8">
                                <svg class="w-12 h-12 mx-auto text-ink-subtle mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <p class="text-sm text-ink-muted">No hay equipos creados</p>
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Favoritos Panel (exploración individual; modo pokémon) -->
                <div class="card-surface" x-show="modo === 'pokemon'" x-cloak>
                    <div class="p-4 border-b border-line flex items-center justify-between flex-wrap gap-2">
                        <h3 class="text-sm font-semibold text-ink">Tus favoritos para este hábitat</h3>
                        <span class="px-2 py-0.5 bg-warning-soft text-warning-ink text-2xs font-bold rounded-full" x-text="favoritos.length + '/6'"></span>
                    </div>
                    <div class="p-4">
                        <div x-show="favoritosLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-ink-muted">
                            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Cargando favoritos...
                        </div>
                        <div x-show="favoritosError" x-cloak role="alert" class="text-sm text-warning-ink bg-warning-soft border border-warning rounded-control px-3 py-2">
                            <span x-text="favoritosError"></span>
                        </div>
                        <div x-show="!favoritosLoading && !favoritosError && favoritos.length === 0" x-cloak class="text-center py-6">
                            <p class="text-sm text-ink-muted">No tienes Pokémon favoritos para este hábitat</p>
                            <button
                                @click="openGestionFavoritosHabitat()"
                                class="mt-3 px-4 py-2 bg-primary text-on-primary rounded-control text-sm font-medium hover:bg-primary-hover transition-colors"
                            >
                                + Añadir favorito
                            </button>
                        </div>

                        <!-- Aviso preventivo: máx. 6 favoritos por hábitat -->
                        <div x-show="!favoritosLoading && favoritos.length >= 6" x-cloak role="status"
                             class="mb-3 px-3 py-2 rounded-control bg-warning-soft border border-warning text-xs text-warning-ink">
                            Has alcanzado el máximo de 6 favoritos para este hábitat.
                        </div>

                        <div x-show="!favoritosLoading && favoritos.length > 0" x-cloak class="grid grid-cols-2 sm:grid-cols-6 gap-3">
                            <template x-for="favorito in favoritos" :key="favorito.id">
                                <div
                                    class="rounded-control p-2 border-2 transition-all text-center select-none"
                                    :class="esFavoritoEnExploracion(favorito.id)
                                        ? 'bg-surface-sunken border-line opacity-60'
                                        : (selectedFavoritoId === favorito.id
                                            ? 'border-primary bg-primary-soft ring-1 ring-primary'
                                            : 'border-line bg-surface-sunken')"
                                >
                                    <div class="flex items-center justify-end mb-1" x-show="esFavoritoEnExploracion(favorito.id)">
                                        <span class="px-1.5 py-0.5 bg-warning-soft text-warning-ink text-2xs font-bold rounded-full uppercase">En exploración</span>
                                    </div>
                                    <button
                                        type="button"
                                        @click="!esFavoritoEnExploracion(favorito.id) && selectFavorito(favorito.id, favorito.nombre)"
                                        :disabled="esFavoritoEnExploracion(favorito.id)"
                                        class="w-full text-center disabled:cursor-not-allowed"
                                        :aria-label="esFavoritoEnExploracion(favorito.id) ? 'Pokémon en exploración' : 'Seleccionar ' + favorito.nombre"
                                    >
                                        <x-sprite
                                            size="96"
                                            wrapper-class="mx-auto"
                                            x-bind:src="iconoReclutado(favorito)"
                                            x-on:error="onSpriteError($event, favorito.pokemon_id, favorito.es_shiny)"
                                            x-bind:alt="favorito.nombre"
                                        />
                                        <p class="text-2xs text-ink-subtle" x-text="'Nv ' + (favorito.nivel || 1)"></p>
                                    </button>
                                    <button
                                        :disabled="esFavoritoEnExploracion(favorito.id)"
                                        @click="!esFavoritoEnExploracion(favorito.id) && openEnviarHabitat(favorito)"
                                        class="mt-2 w-32 px-2 py-1 bg-success text-on-primary rounded-control text-2xs font-bold hover:bg-success/90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed uppercase"
                                        x-text="esFavoritoEnExploracion(favorito.id) ? 'En exploración' : 'Enviar a explorar'"
                                    ></button>
                                </div>
                            </template>
                        </div>

                        <!-- Botón añadir favorito (visible siempre que no esté al máximo) -->
                        <div x-show="!favoritosLoading && !favoritosError && favoritos.length > 0 && favoritos.length < 6" class="mt-3">
                            <button
                                @click="openGestionFavoritosHabitat()"
                                class="w-full px-4 py-2.5 border border-dashed border-line-strong rounded-control text-sm font-medium text-ink-muted hover:border-primary hover:text-primary-ink transition-colors"
                            >
                                + Añadir favorito
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Niveles Panel: 3 clickable level rows (ruta = sin fetch; pokémon / entrenadores) -->
                <div class="card-surface" x-show="modo === 'ruta' || modo === 'pokemon' || modo === 'entrenadores'" x-cloak>
                    <div class="p-4 space-y-3">
                        <!-- MODO POKÉMON / RUTA -->
                        <div x-show="modo === 'pokemon' || modo === 'ruta'">
                            <p x-show="avisoNivel && modo === 'pokemon'" x-cloak x-text="avisoNivel" role="status"
                               class="text-xs text-warning-ink bg-warning-soft border border-warning rounded-control px-3 py-2"></p>
                            @foreach([1,2,3] as $level)
                            @php
                                $minLvl = $habitat['min_lvl_' . $level] ?? null;
                                $bloqueadoNivel = $minLvl !== null && ($nivelJugador ?? 1) < $minLvl;
                            @endphp
                            <button
                                @click="modo === 'ruta' ? selectRutaNivel({{ $level }}) : selectLevel({{ $level }})"
                                :disabled="modo === 'pokemon' && {{ $bloqueadoNivel ? 'true' : 'false' }}"
                                @if($bloqueadoNivel) title="Requiere Nv {{ $minLvl }}" @endif
                                :class="[
                                    (modo === 'ruta' ? rutaNivel : selectedLevel) === {{ $level }}
                                        ? 'border-primary bg-primary-soft'
                                        : 'border-line hover:border-line-strong',
                                    modo === 'pokemon' && {{ $bloqueadoNivel ? 'true' : 'false' }}
                                        ? 'opacity-60 cursor-not-allowed border-dashed'
                                        : ''
                                ]"
                                class="w-full p-4 rounded-card border-2 text-left transition-all"
                                aria-label="Nivel {{ $level }}"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-semibold text-ink flex items-center gap-1.5">
                                        @if($bloqueadoNivel)
                                            <svg x-show="modo === 'pokemon'" x-cloak class="w-3.5 h-3.5 text-warning-ink" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                            </svg>
                                        @endif
                                        Nivel {{ $level }}
                                    </span>
                                    <span class="flex items-center gap-2">
                                        @if($bloqueadoNivel)
                                            <span x-show="modo === 'pokemon'" x-cloak class="px-2 py-0.5 bg-warning-soft text-warning-ink text-2xs font-bold rounded-full">
                                                Requiere Nv {{ $minLvl }}
                                            </span>
                                        @endif
                                        <span class="text-xs text-ink-muted">{{ count($habitat['levels'][$level] ?? []) }} pokémon</span>
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
                                            <x-sprite :src="$pokemon['icon']" :name="$pokemon['name']" size="96" :title="$pokemon['name']" />
                                        </div>
                                    </template>
                                    @empty
                                    <span class="text-xs text-ink-subtle">Sin Pokémon en este nivel</span>
                                    @endforelse
                                </div>
                            </button>
                            @endforeach
                        </div>

                        <!-- MODO ENTRENADORES -->
                        <div x-show="modo === 'entrenadores'" x-cloak>
                            <template x-if="!trainers && !trainersLoading">
                                <p class="text-xs text-ink-muted">No hay entrenadores disponibles</p>
                            </template>
                            <template x-if="trainersLoading">
                                <p class="text-xs text-ink-muted flex items-center gap-2">
                                    <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Cargando entrenadores...
                                </p>
                            </template>
                            <template x-for="(levelData, levelIdx) in (trainers || [])" :key="'level-' + levelIdx">
                                <div class="mb-4">
                                    <h4 class="text-sm font-semibold text-ink mb-2" x-text="'Nivel ' + levelIdx"></h4>
                                    <div class="grid grid-cols-3 gap-2">
                                        <template x-for="(entrenador, eIdx) in levelData" :key="eIdx">
                                            <button
                                                @click="selectTrainer(levelIdx, entrenador.indice)"
                                                :disabled="!entrenador.desbloqueado || !selectedTeamId"
                                                :class="{
                                                    'border-success bg-success-soft': entrenador.desbloqueado && selectedTeamId,
                                                    'border-line opacity-50 cursor-not-allowed': !entrenador.desbloqueado || !selectedTeamId,
                                                    'hover:border-success': entrenador.desbloqueado && selectedTeamId
                                                }"
                                                class="w-full p-2 rounded-card border-2 text-left transition-all"
                                            >
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="text-xs font-semibold text-ink" x-text="'Entrenador ' + entrenador.indice"></span>
                                                    <span x-show="!entrenador.desbloqueado" class="px-1.5 py-0.5 bg-danger-soft text-danger-ink text-2xs font-bold rounded-full">Derrotado</span>
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
                    x-show="modo === 'pokemon'"
                    x-cloak
                    @click="checkAndOpenModal()"
                    :disabled="!canStartExploration"
                    class="w-full px-4 py-3 bg-success text-on-primary rounded-card text-sm font-bold transition-all hover:bg-success/90 disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed uppercase tracking-wide"
                >
                    Iniciar Exploración
                </button>
                <button
                    x-show="modo === 'ruta'"
                    x-cloak
                    @click="openRutaFormacionPopup()"
                    :disabled="!selectedTeamId"
                    class="w-full px-4 py-3 bg-danger text-on-primary rounded-card text-sm font-bold transition-all hover:bg-danger/90 disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed uppercase tracking-wide"
                >
                    ⚔️ Combate de Ruta
                </button>
                <button
                    x-show="modo === 'entrenadores'"
                    @click="openFormacionPopup()"
                    :disabled="!selectedTeamId || !selectedTrainer"
                    class="w-full px-4 py-3 bg-danger text-on-primary rounded-card text-sm font-bold transition-all hover:bg-danger/90 disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed uppercase tracking-wide"
                >
                    Iniciar Combate contra Entrenador
                </button>
            </div>
        </div>

    <!-- Exploration Modal (individual) -->
    <template x-if="showExplorationModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="exploration-modal-title" @keydown.escape.window="closeExplorationModal()">
            <div class="absolute inset-0 scrim" @click="closeExplorationModal()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-md p-6">
                <h3 id="exploration-modal-title" class="text-lg font-bold text-ink mb-4">Confirmar Exploración</h3>
                <p class="text-sm text-ink-muted mb-4">
                    ¿Quieres enviar a explorar a <strong class="text-ink capitalize" x-text="selectedFavoritoName"></strong> a la zona <strong class="text-ink">{{ $habitat['name'] }}</strong>?
                </p>

                <!-- Preparación de la expedición (preview individual) -->
                <div class="mb-4">
                    <div x-show="previewLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-ink-muted bg-surface-sunken border border-line rounded-control px-3 py-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Calculando la preparación de la expedición...
                    </div>
                    <div x-show="previewError" x-cloak role="alert" class="text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2">
                        <span x-text="previewError"></span>
                    </div>
                    <template x-if="previewLoaded && preview">
                        <div class="space-y-3 bg-surface-sunken border border-line rounded-control p-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-ink-muted">Peligro de la zona</span>
                                <span class="text-sm text-warning-ink tracking-tight" x-html="starRating(preview.peligro)"
                                      :title="'Peligro ' + preview.peligro + ' de 5 estrellas'" aria-hidden="true"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-ink-muted">Nivel del Pokémon</span>
                                <span class="text-sm font-medium text-ink" x-text="'Nv ' + preview.nivel_pokemon"></span>
                            </div>
                            <template x-if="preview.capacidades">
                                <div>
                                    <p class="text-xs font-medium text-ink-muted mb-1.5">Capacidades del Pokémon</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <template x-for="(valor, key) in preview.capacidades" :key="key">
                                            <div class="flex items-center justify-between px-2 py-1 bg-surface rounded border border-line">
                                                <span class="text-2xs text-ink-muted capitalize" x-text="key"></span>
                                                <span class="text-2xs font-medium text-ink" x-text="Math.round(valor * 100) / 100"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <template x-if="preview.min_lvl">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-ink-muted">Nivel mínimo requerido</span>
                                    <span class="text-xs font-medium text-ink">Nv <span x-text="preview.min_lvl"></span></span>
                                </div>
                            </template>
                            <div class="flex items-center justify-between">
                                <span class="px-2 py-0.5 text-2xs font-bold rounded-full uppercase" :class="riesgoClass(preview.riesgo)" x-text="'Riesgo ' + preview.riesgo"></span>
                            </div>
                            <template x-if="preview.rol || preview.rol_sugerido">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-ink-muted">Rol</span>
                                    <span class="px-2 py-0.5 text-2xs font-bold rounded-full uppercase bg-info-soft text-info-ink" x-text="preview.rol || preview.rol_sugerido"></span>
                                </div>
                            </template>
                            <!-- Recompensas esperadas (tolerante: se oculta si el backend aún no las expone) -->
                            <div class="space-y-1.5 pt-2 border-t border-line"
                                 x-show="previewLoaded && preview?.recompensas_esperadas">
                                <p class="text-xs font-bold text-ink">Ganarás esto</p>
                                <template x-if="preview.recompensas_esperadas?.por_horas">
                                    <p class="text-xs text-ink-muted">
                                        Duración estimada:
                                        <span class="font-medium text-ink" x-text="preview.recompensas_esperadas.por_horas + ' h'"></span>
                                    </p>
                                </template>
                                <template x-if="preview.recompensas_esperadas?.items?.length">
                                    <ul class="space-y-1">
                                        <template x-for="(item, i) in preview.recompensas_esperadas.items" :key="i">
                                            <li class="flex items-center justify-between gap-3 text-xs text-ink-muted">
                                                <span x-text="item.label"></span>
                                                <span class="font-medium text-ink" x-text="'entre ' + item.min + ' y ' + item.max"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </template>
                                <template x-if="preview.recompensas_esperadas?.aviso">
                                    <p class="text-2xs text-ink-subtle italic" x-text="preview.recompensas_esperadas.aviso"></p>
                                </template>
                                {{-- Aviso económico: el backend calcula factor y mensaje; la vista solo los presenta. --}}
                                <template x-if="preview?.aviso_economia">
                                    <div class="flex items-start gap-1.5 pt-0.5">
                                        <p role="note" class="text-2xs leading-snug text-warning-ink" x-text="preview.aviso_economia"></p>
                                        <template x-if="preview?.economia?.factor">
                                            <span class="shrink-0 whitespace-nowrap px-1.5 py-0.5 rounded-full border border-warning bg-warning-soft text-2xs font-medium text-warning-ink"
                                                  x-text="'Factor ×' + Number(preview.economia.factor).toLocaleString('es-ES', { maximumFractionDigits: 2 })"></span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Duration options (copied from existing) -->
                <div class="space-y-3 mb-6">
                    <label class="flex items-center gap-3 p-3 rounded-control border border-line cursor-pointer hover:bg-surface-sunken transition-colors"
                        :class="{ 'border-primary bg-primary-soft': durationMode === 'hours' }"
                    >
                        <input type="radio" x-model="durationMode" value="hours" class="w-4 h-4 text-primary-ink" @change="loadPreview()">
                        <span class="text-sm text-ink">Duración: </span>
                        <input
                            type="number"
                            x-model.number="durationHours"
                            min="1"
                            max="72"
                            :disabled="durationMode !== 'hours'"
                            class="w-16 px-2 py-1 text-sm border border-line-strong rounded bg-surface text-ink disabled:opacity-50"
                            @click.stop
                            @change="loadPreview()"
                        >
                        <span class="text-sm text-ink-muted">horas</span>
                    </label>

                    <label class="flex items-center gap-3 p-3 rounded-control border border-line cursor-pointer hover:bg-surface-sunken transition-colors"
                        :class="{ 'border-primary bg-primary-soft': durationMode === 'return_time' }"
                    >
                        <input type="radio" x-model="durationMode" value="return_time" class="w-4 h-4 text-primary-ink" @change="loadPreview()">
                        <span class="text-sm text-ink">Regresar antes de las </span>
                        <input
                            type="time"
                            x-model="returnTime"
                            :disabled="durationMode !== 'return_time'"
                            class="px-2 py-1 text-sm border border-line-strong rounded bg-surface text-ink disabled:opacity-50"
                            @click.stop
                            @change="loadPreview()"
                        >
                    </label>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3">
                    <button
                        @click="closeExplorationModal()"
                        class="flex-1 px-4 py-2.5 bg-surface-sunken text-ink rounded-control text-sm font-medium hover:bg-line transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="confirmExploration()"
                        :disabled="!previewLoaded"
                        class="flex-1 px-4 py-2.5 bg-success text-on-primary rounded-control text-sm font-bold hover:bg-success/90 transition-colors disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed"
                    >
                        Enviar expedición
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- Gestion Favoritos Habitat Modal -->
    <template x-if="showGestionFavoritosModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="gestion-favoritos-title" @keydown.escape.window="closeGestionFavoritosModal()">
            <div class="absolute inset-0 scrim" @click="closeGestionFavoritosModal()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-line">
                    <h3 id="gestion-favoritos-title" class="text-lg font-bold text-ink">Favoritos de esta hábitat</h3>
                    <button @click="closeGestionFavoritosModal()" class="p-2 rounded-control hover:bg-surface-sunken text-ink-muted focus-ring" aria-label="Cerrar">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-6">
                    <div x-show="gestionFavoritosLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-ink-muted">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Cargando...
                    </div>
                    <div x-show="gestionFavoritosError" x-cloak role="alert" class="text-sm text-warning-ink bg-warning-soft border border-warning rounded-control px-3 py-2">
                        <span x-text="gestionFavoritosError"></span>
                    </div>
                    <div x-show="!gestionFavoritosLoading && !gestionFavoritosError">
                        <!-- Máximo alcanzado -->
                        <div x-show="favoritos.length >= 6" class="mb-3 px-3 py-2 rounded-control bg-warning-soft border border-warning text-xs text-warning-ink">
                            Ya tienes 6 favoritos en este hábitat (máximo). Quita uno para poder añadir otro.
                        </div>
                        <!-- Grid de reclutados del usuario -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                            <template x-for="pokemon in allReclutados" :key="pokemon.id">
                                <div
                                    class="relative bg-surface rounded-control shadow-sm border text-center p-2 transition-all cursor-pointer select-none"
                                    :class="esFavoritoHabitat(pokemon.id)
                                        ? 'border-warning ring-1 ring-warning/30'
                                        : 'border-line hover:border-line-strong'"
                                    @click="toggleFavoritoHabitat(pokemon)"
                                    :title="esFavoritoHabitat(pokemon.id) ? 'Quitar de favoritos de este hábitat' : 'Añadir a favoritos de este hábitat'"
                                >
                                    <div class="w-24 h-24 mx-auto">
                                        <x-sprite
                                            size="96"
                                            x-bind:src="iconoReclutado(pokemon)"
                                            x-on:error="onSpriteError($event, pokemon.pokemon_id, pokemon.es_shiny)"
                                            x-bind:alt="pokemon.nombre"
                                        />
                                    </div>
                                    <p class="text-2xs text-ink-muted truncate mt-1" x-text="pokemon.nombre"></p>
                                    <p class="text-2xs text-ink-subtle" x-text="'Nv ' + (pokemon.nivel || 1)"></p>
                                    <div class="mt-1">
                                        <template x-if="esFavoritoHabitat(pokemon.id)">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-warning-soft text-warning-ink text-2xs font-bold rounded-full">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                Favorito
                                            </span>
                                        </template>
                                        <template x-if="!esFavoritoHabitat(pokemon.id)">
                                            <span class="text-2xs text-ink-subtle">Click para marcar</span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div x-show="allReclutados.length === 0" class="text-center py-12 text-ink-muted">
                            <p>No hay Pokémon disponibles</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Formacion Modal (Entrenadores / Ruta) -->
    <template x-if="showFormacionPopup">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="formacion-modal-title" @keydown.escape.window="closeFormacionPopup()">
            <div class="absolute inset-0 scrim" @click="closeFormacionPopup()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-md p-6">
                <h3 id="formacion-modal-title" class="text-lg font-bold text-ink mb-4" x-text="formacionContexto === 'ruta' ? 'Combate de Ruta · Configurar Formación' : 'Configurar Formación'"></h3>
                <p class="text-sm text-ink-muted mb-4">
                    Ajusta la posición de cada pokémon antes del combate.
                </p>

                <!-- Error 422 inline (ruta: sin redirigir) -->
                <div x-show="rutaCombatError" x-cloak role="alert" class="mb-4 text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2">
                    <span x-text="rutaCombatError"></span>
                </div>

                <!-- Miembros del equipo con toggle -->
                <div class="space-y-3 mb-6">
                    <template x-for="miembro in (selectedTeamMembers || [])" :key="miembro.id">
                        <div class="flex items-center justify-between p-3 bg-surface-sunken border border-line rounded-control">
                            <div class="flex items-center gap-3">
                                <x-sprite size="64" x-bind:src="iconoReclutado(miembro.reclutado)" x-on:error="onSpriteError($event, miembro.reclutado.pokemon_id, miembro.reclutado.es_shiny)" x-bind:alt="miembro.reclutado.nombre" />
                                <div>
                                    <p class="text-sm font-medium text-ink" x-text="miembro.reclutado.nombre || miembro.reclutado?.pokemon?.name"></p>
                                </div>
                            </div>
                            <button
                                @click="toggleFormacionSlot(miembro.slot)"
                                :class="formacion[miembro.slot] === 'vanguardia'
                                    ? 'bg-primary text-on-primary border-primary'
                                    : 'bg-surface-sunken text-ink border-line-strong'"
                                class="px-3 py-1.5 rounded-control text-xs font-bold border transition-colors"
                                x-text="formacion[miembro.slot] === 'vanguardia' ? '🛡️ Vanguardia' : '⚔️ Retaguardia'"
                            ></button>
                        </div>
                    </template>
                    <template x-if="!selectedTeamMembers || selectedTeamMembers.length === 0">
                        <p class="text-sm text-ink-subtle text-center py-4">Selecciona un equipo primero</p>
                    </template>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3">
                    <button
                        @click="closeFormacionPopup()"
                        class="flex-1 px-4 py-2.5 bg-surface-sunken text-ink rounded-control text-sm font-medium hover:bg-line transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="confirmarCombate()"
                        :disabled="formacionContexto === 'ruta'
                            ? (!selectedTeamId || rutaCombatiendo)
                            : (!selectedTeamId || !selectedTrainer)"
                        class="flex-1 px-4 py-2.5 bg-danger text-on-primary rounded-control text-sm font-bold hover:bg-danger/90 transition-colors disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed"
                        x-text="formacionContexto === 'ruta'
                            ? (rutaCombatiendo ? 'Iniciando combate...' : '⚔️ ¡Combatir! (Ruta 3v3)')
                            : '¡Combatir!'"
                    ></button>
                </div>
            </div>
        </div>
    </template>

    <!-- Admin Gestion Modal -->
    <template x-if="showGestionModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="gestion-modal-title" @keydown.escape.window="closeGestionModal()">
            <div class="absolute inset-0 scrim" @click="closeGestionModal()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-line">
                    <h3 id="gestion-modal-title" class="text-lg font-bold text-ink">Admin - Gestión de Familias</h3>
                    <button @click="closeGestionModal()" class="p-2 rounded-control hover:bg-surface-sunken text-ink-muted focus-ring" aria-label="Cerrar modal">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Tabs -->
                <div class="border-b border-line px-6">
                    <nav class="flex gap-6" role="tablist" aria-label="Gestión de familias">
                        <button
                            role="tab"
                            :aria-selected="gestionTab === 'assign'"
                            :class="['py-3 text-sm font-medium border-b-2 transition-colors', gestionTab === 'assign' ? 'border-primary text-primary-ink' : 'border-transparent text-ink-muted hover:text-ink']"
                            @click="gestionTab = 'assign'"
                        >
                            Asignar
                        </button>
                        <button
                            role="tab"
                            :aria-selected="gestionTab === 'unassign'"
                            :class="['py-3 text-sm font-medium border-b-2 transition-colors', gestionTab === 'unassign' ? 'border-danger text-danger-ink' : 'border-transparent text-ink-muted hover:text-ink']"
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
                                    class="flex-1 px-3 py-2 text-sm border border-line-strong rounded-control bg-surface text-ink"
                                >
                                <select
                                    x-model="assignTypeFilter"
                                    class="px-3 py-2 text-sm border border-line-strong rounded-control bg-surface text-ink"
                                >
                                    <option value="">Todos los tipos</option>
                                    <template x-for="tipo in allTypes" :key="tipo.id">
                                        <option :value="tipo.id" x-text="tipo.name"></option>
                                    </template>
                                </select>
                            </div>

                            <!-- Unassigned families grid -->
                            <div x-show="filteredUnassigned.length === 0 && !gestionLoading" class="text-center py-12 text-ink-muted">
                                <p>No hay familias sin hábitat que coincidan con la búsqueda</p>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                <template x-for="family in filteredUnassigned" :key="family.evolution_chain_id">
                                    <button
                                        type="button"
                                        :disabled="gestionLoading"
                                        @click="assignFamily(family)"
                                        class="group card-surface p-3 text-center hover:border-primary hover:shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                        :title="family.base.name"
                                    >
                                        <div class="w-full aspect-square bg-surface-sunken rounded-control flex items-center justify-center overflow-hidden mb-2">
                                            <x-sprite
                                                size="96"
                                                class="group-hover:scale-105 transition-transform"
                                                x-bind:src="'/images/iconos_webp/' + family.base.id + '.webp'"
                                                x-bind:alt="family.base.name"
                                            />
                                        </div>
                                        <p class="text-sm font-medium text-ink truncate" x-text="family.base.name"></p>
                                        <div class="flex flex-wrap justify-center gap-1 mt-1">
                                            <template x-for="tipo in (family.types || [])" :key="tipo.id">
                                                <span class="tipo-badge" :style="'--type-color: ' + tipoBadgePorId(tipo.id).color">
                                                    <template x-if="tipoBadgePorId(tipo.id).icono">
                                                        <img :src="tipoBadgePorId(tipo.id).icono" alt="" loading="lazy" decoding="async" class="w-4 h-4 object-contain">
                                                    </template>
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
                            <div x-show="assignedFamilies.length === 0 && !gestionLoading" class="text-center py-12 text-ink-muted">
                                <p>No hay familias asignadas a este hábitat</p>
                            </div>

                            <template x-for="level in [1, 2, 3]" :key="'level-' + level">
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-sm font-semibold text-ink">Nivel <span x-text="level"></span></h4>
                                        <span class="text-xs text-ink-muted" x-text="assignedByLevel[level].length + ' pokémon'"></span>
                                    </div>

                                    <div x-show="assignedByLevel[level].length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                        <template x-for="pokemon in assignedByLevel[level]" :key="pokemon.evolution_chain_id + '-' + pokemon.id">
                                            <div class="relative card-surface p-3 text-center group">
                                                <template x-if="pokemon.is_base">
                                                    <button
                                                        type="button"
                                                        :disabled="gestionLoading"
                                                        @click="removeFamily(pokemon.evolution_chain_id)"
                                                        class="absolute -top-2 -right-2 w-6 h-6 bg-danger text-on-primary rounded-full text-xs flex items-center justify-center hover:bg-danger/90 transition-colors disabled:opacity-30"
                                                        :aria-label="'Quitar la familia completa de ' + pokemon.name"
                                                        title="Quitar familia completa"
                                                    >
                                                        ✕
                                                    </button>
                                                </template>
                                                <div class="w-full aspect-square bg-surface-sunken rounded-control flex items-center justify-center overflow-hidden mb-2">
                                                    <x-sprite
                                                        size="96"
                                                        x-bind:src="'/images/iconos_webp/' + pokemon.id + '.webp'"
                                                        x-bind:alt="pokemon.name"
                                                    />
                                                </div>
                                                <p class="text-sm font-medium text-ink truncate" x-text="pokemon.name"></p>
                                                <p class="text-2xs text-ink-muted mt-0.5" x-text="pokemon.is_base ? 'Base de la familia' : 'Evolución'"></p>
                                                <div class="flex justify-center gap-1 mt-2">
                                                    <button
                                                        type="button"
                                                        :disabled="gestionLoading"
                                                        @click="updatePokemonLevel(pokemon.evolution_chain_id, pokemon.id, 1)"
                                                        :class="pokemon.level === 1 ? 'bg-primary text-on-primary border-primary' : 'bg-surface text-ink-muted border-line hover:border-primary'"
                                                        class="w-8 h-7 text-xs font-bold rounded border transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                        :aria-label="'Mover ' + pokemon.name + ' al nivel 1'"
                                                        title="Nivel 1"
                                                    >1</button>
                                                    <button
                                                        type="button"
                                                        :disabled="gestionLoading"
                                                        @click="updatePokemonLevel(pokemon.evolution_chain_id, pokemon.id, 2)"
                                                        :class="pokemon.level === 2 ? 'bg-primary text-on-primary border-primary' : 'bg-surface text-ink-muted border-line hover:border-primary'"
                                                        class="w-8 h-7 text-xs font-bold rounded border transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                        :aria-label="'Mover ' + pokemon.name + ' al nivel 2'"
                                                        title="Nivel 2"
                                                    >2</button>
                                                    <button
                                                        type="button"
                                                        :disabled="gestionLoading"
                                                        @click="updatePokemonLevel(pokemon.evolution_chain_id, pokemon.id, 3)"
                                                        :class="pokemon.level === 3 ? 'bg-primary text-on-primary border-primary' : 'bg-surface text-ink-muted border-line hover:border-primary'"
                                                        class="w-8 h-7 text-xs font-bold rounded border transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                        :aria-label="'Mover ' + pokemon.name + ' al nivel 3'"
                                                        title="Nivel 3"
                                                    >3</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <div x-show="assignedByLevel[level].length === 0 && assignedFamilies.length > 0 && !gestionLoading" class="text-center py-6 text-ink-subtle border border-dashed border-line rounded-card">
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

    <!-- Mazmorra Modal -->
    <template x-if="showMazmorraModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="mazmorra-modal-title" @keydown.escape.window="closeMazmorraModal()">
            <div class="absolute inset-0 scrim" @click="closeMazmorraModal()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-line">
                    <h3 id="mazmorra-modal-title" class="text-lg font-bold text-ink">Mazmorra del Hábitat</h3>
                    <button @click="closeMazmorraModal()" class="p-2 rounded-control hover:bg-surface-sunken text-ink-muted focus-ring" aria-label="Cerrar modal de mazmorra">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-6">
                    <!-- Loading -->
                    <div x-show="mazmorraLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-ink-muted">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Cargando mazmorra...
                    </div>

                    <!-- Error -->
                    <div x-show="mazmorraError" x-cloak role="alert" class="text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2">
                        <span x-text="mazmorraError"></span>
                        <button @click="loadMazmorra()" class="ml-2 underline font-medium">Reintentar</button>
                    </div>

                    <!-- Empty: sin pisos -->
                    <template x-if="!mazmorraLoading && !mazmorraError && (!pisos || pisos.length === 0)">
                        <div class="text-center py-10 text-ink-muted">
                            <p>Esta mazmorra aún no tiene pisos configurados.</p>
                        </div>
                    </template>

                    <template x-if="!mazmorraLoading && !mazmorraError && pisos && pisos.length > 0">
                        <div class="space-y-4">
                            <p class="text-xs text-ink-muted">
                                En cada piso te enfrentas al pokémon más débil del hábitat. Si pierdes, tendrás
                                que esperar 1 hora para repetir el piso; los pisos ganados no se pueden repetir.
                            </p>

                            <!-- Pisos -->
                            <div class="space-y-2">
                                <template x-for="piso in pisos" :key="'piso-'+piso.piso">
                                    <div
                                        class="flex items-center justify-between gap-3 p-3 rounded-card border-2 transition-all"
                                        :class="estadoPisoClass(piso)"
                                    >
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
                                                  :class="estadoPisoIconoClass(piso)">
                                                <span x-text="piso.piso"></span>
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-ink truncate" x-text="piso.nombre || 'Piso ' + piso.piso"></p>
                                                <p class="text-xs text-ink-muted" x-text="estadoPisoTexto(piso)"></p>
                                            </div>
                                        </div>
                                        <template x-if="piso.estado === 'disponible'">
                                            <button @click="selectPiso(piso)"
                                                    class="shrink-0 px-3 py-1.5 bg-primary text-on-primary rounded-control text-xs font-bold hover:bg-primary-hover transition-colors"
                                                    :disabled="mazmorraCombatiendo">
                                                Combatir
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <!-- Acción: conectar equipo + formación para el piso seleccionado -->
                            <template x-if="pisoSeleccionado">
                                <div class="border-t border-line pt-4">
                                    <p class="text-xs font-semibold text-ink-muted uppercase tracking-wide mb-2">Equipo y formación</p>
                                    <div class="space-y-2 mb-3">
                                        <template x-if="availableTeams.length === 0">
                                            <p class="text-sm text-ink-subtle text-center py-2">No tienes equipos disponibles</p>
                                        </template>
                                        <template x-for="team in availableTeams" :key="team.id">
                                            <button type="button" @click="selectMazmorraTeam(team.id, team.name)"
                                                    :class="mazmorraTeamId === team.id
                                                        ? 'border-primary bg-primary-soft'
                                                        : 'border-line hover:border-line-strong'"
                                                    class="w-full p-3 rounded-control border-2 text-left transition-all flex items-center justify-between">
                                                <span class="text-sm font-medium text-ink" x-text="team.name"></span>
                                                <template x-if="mazmorraTeamId === team.id">
                                                    <svg class="w-5 h-5 text-primary-ink" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                </template>
                                            </button>
                                        </template>
                                    </div>

                                    <!-- Formación (hasta 3 miembros) -->
                                    <div x-show="mazmorraTeamId" x-cloak class="space-y-3 mb-4">
                                        <p class="text-xs font-semibold text-ink-muted uppercase tracking-wide">Formación</p>
                                        <template x-for="miembro in (mazmorraTeamMembers || [])" :key="miembro.id">
                                            <div class="flex items-center justify-between p-3 bg-surface-sunken border border-line rounded-control">
                                                <div class="flex items-center gap-3">
                                                    <x-sprite size="64" x-bind:src="iconoReclutado(miembro.reclutado)" x-on:error="onSpriteError($event, miembro.reclutado.pokemon_id, miembro.reclutado.es_shiny)" x-bind:alt="miembro.reclutado.nombre" />
                                                    <div>
                                                        <p class="text-sm font-medium text-ink" x-text="miembro.reclutado.nombre || miembro.reclutado?.pokemon?.name"></p>
                                                        <p class="text-xs text-ink-muted" x-text="'Posición ' + miembro.slot"></p>
                                                    </div>
                                                </div>
                                                <button
                                                    @click="toggleFormacionSlot(miembro.slot)"
                                                    :class="mazmorraFormacion[miembro.slot] === 'vanguardia'
                                                        ? 'bg-primary text-on-primary border-primary'
                                                        : 'bg-surface-sunken text-ink border-line-strong'"
                                                    class="px-3 py-1.5 rounded-control text-xs font-bold border transition-colors shrink-0"
                                                    x-text="(mazmorraFormacion[miembro.slot] === 'vanguardia' ? '🛡️ Vanguardia' : '⚔️ Retaguardia')"
                                                ></button>
                                            </div>
                                        </template>
                                        <template x-if="!mazmorraTeamMembers || mazmorraTeamMembers.length === 0">
                                            <p class="text-sm text-ink-subtle text-center py-3">Selecciona un equipo primero</p>
                                        </template>
                                    </div>

                                    <!-- Error de combate -->
                                    <div x-show="mazmorraCombatError" x-cloak role="alert" class="text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2 mb-3">
                                        <span x-text="mazmorraCombatError"></span>
                                    </div>

                                    <button @click="confirmarPiso()"
                                            :disabled="!mazmorraTeamId || mazmorraCombatiendo"
                                            class="w-full px-4 py-2.5 bg-danger text-on-primary rounded-control text-sm font-bold hover:bg-danger/90 transition-colors disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed">
                                        <span x-show="!mazmorraCombatiendo">¡Combatir en el piso <span x-text="pisoSeleccionado ? pisoSeleccionado.piso : ''"></span>!</span>
                                        <span x-show="mazmorraCombatiendo" x-cloak class="inline-flex items-center gap-2">
                                            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            Iniciando...
                                        </span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- Granjas Modal -->
    <template x-if="showGranjaModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="granja-modal-title" @keydown.escape.window="closeGranjaModal()">
            <div class="absolute inset-0 scrim" @click="closeGranjaModal()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-line">
                    <div>
                        <h3 id="granja-modal-title" class="text-lg font-bold text-ink">
                            Granjas — <span x-text="granja?.habitat?.name || '{{ $habitat['name'] }}'"></span>
                        </h3>
                        <p class="text-xs text-ink-muted">Nidos, refugios y complementos de las familias del hábitat.</p>
                    </div>
                    <button @click="closeGranjaModal()" class="p-2 rounded-control hover:bg-surface-sunken text-ink-muted focus-ring" aria-label="Cerrar modal de granjas">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-6 space-y-4">
                    <div x-show="granjaLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-ink-muted">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Cargando granja...
                    </div>

                    <div x-show="granjaError" x-cloak role="alert" class="text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2">
                        <span x-text="granjaError"></span>
                        <button @click="loadGranja()" class="ml-2 underline font-medium">Reintentar</button>
                    </div>

                    <template x-if="!granjaLoading && !granjaError && granja">
                        <div class="space-y-4">
                            <div class="rounded-card bg-surface-sunken border border-line p-3 text-xs text-ink-muted space-y-1">
                                <p><strong>Nido:</strong> cada nivel sube el rango de IV de los huevos y reduce su tiempo de gestación/eclosión.</p>
                                <p><strong>Refugio:</strong> modifica la aparición y la captura de la familia. Las condiciones insalubres invierten el efecto.</p>
                                <p><strong>Complementos:</strong> dan EV a toda la familia; al subir un complemento se aplica también a los reclutados existentes.</p>
                            </div>

                            <div x-show="granja.familias.length === 0" class="text-center py-10 text-ink-muted">
                                Este hábitat no tiene familias asignadas todavía.
                            </div>

                            <template x-for="familia in (granja.familias || [])" :key="'granja-' + familia.evolution_chain_id">
                                <div class="rounded-panel border border-line bg-surface p-4 space-y-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-32 shrink-0 rounded-card bg-surface-sunken flex items-center justify-center overflow-hidden">
                                            <x-sprite size="128" x-bind:src="familia.base.icon" x-bind:alt="familia.base.name" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-bold text-ink truncate" x-text="familia.base.name"></p>
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                <template x-for="tipo in (familia.types || [])" :key="familia.evolution_chain_id + '-tipo-' + tipo.id">
                                                    <span class="tipo-badge" :style="'--type-color: ' + tipoBadgePorId(tipo.id).color">
                                                        <template x-if="tipoBadgePorId(tipo.id).icono">
                                                            <img :src="tipoBadgePorId(tipo.id).icono" alt="" loading="lazy" decoding="async" class="w-4 h-4 object-contain">
                                                        </template>
                                                        <span x-text="tipo.name"></span>
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <p class="text-2xs uppercase tracking-wide text-ink-subtle">Caramelos familia</p>
                                            <p class="text-sm font-bold text-ink">🍬 <span x-text="saldoFamilia(familia.evolution_chain_id)"></span></p>
                                        </div>
                                    </div>

                                    <div class="grid md:grid-cols-2 gap-3">
                                        <!-- Nido -->
                                        <div class="rounded-card border border-warning bg-warning-soft p-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-sm font-bold text-ink">🪺 Nido</p>
                                                <span class="text-xs font-semibold text-warning-ink" x-text="'Nv ' + familia.nido.nivel + '/' + familia.nido.max"></span>
                                            </div>
                                            <p class="text-xs text-ink-muted mt-2">
                                                IV por stat: <strong x-text="familia.nido.rango_iv.min + '–' + familia.nido.rango_iv.max"></strong>
                                            </p>
                                            <p class="text-xs text-ink-muted">
                                                Tiempo de huevo: <strong x-text="familia.nido.reduccion_tiempo_pct + '% más rápido'"></strong>
                                            </p>
                                            <button
                                                type="button"
                                                @click="mejorarNido(familia.evolution_chain_id)"
                                                :disabled="granjaAccionando || !familia.nido.puede_mejorar"
                                                class="mt-3 w-full px-3 py-2 rounded-control text-xs font-bold transition-colors disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed bg-warning text-on-primary hover:bg-warning/90"
                                            >
                                                <span x-text="familia.nido.coste_siguiente !== null ? (familia.nido.nivel === 0 ? 'Construir nido' : 'Mejorar nido') + ' · ' + familia.nido.coste_siguiente + ' 🍬' : 'Nido al máximo'"></span>
                                            </button>
                                        </div>

                                        <!-- Refugio -->
                                        <div class="rounded-card border border-success bg-success-soft p-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-sm font-bold text-ink">🏕️ Refugio</p>
                                                <span class="text-xs font-semibold text-success-ink" x-text="'Nv ' + familia.refugio.nivel + '/' + familia.refugio.max"></span>
                                            </div>
                                            <p class="text-xs text-ink-muted mt-2">
                                                Aparición ×<strong x-text="familia.refugio.multiplicador_aparicion"></strong>
                                                · Captura ×<strong x-text="familia.refugio.multiplicador_captura"></strong>
                                            </p>
                                            <p x-show="familia.refugio.exclusivo" class="text-xs font-semibold text-success-ink mt-1">
                                                Nivel 5: solo aparece esta familia.
                                            </p>
                                            <p x-show="familia.refugio.excluido" class="text-xs font-semibold text-warning-ink mt-1">
                                                Otra familia tiene un refugio nivel 5: esta familia no aparece.
                                            </p>
                                            <p x-show="familia.refugio.insalubres" class="text-xs font-semibold text-danger-ink mt-1">
                                                Condiciones insalubres activas: efecto invertido y complementos bloqueados.
                                            </p>
                                            <button
                                                type="button"
                                                @click="mejorarRefugio(familia.evolution_chain_id)"
                                                :disabled="granjaAccionando || !familia.refugio.puede_mejorar"
                                                class="mt-3 w-full px-3 py-2 rounded-control text-xs font-bold transition-colors disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed bg-success text-on-primary hover:bg-success/90"
                                            >
                                                <span x-text="familia.refugio.coste_siguiente !== null ? (familia.refugio.nivel === 0 ? 'Construir refugio' : 'Mejorar refugio') + ' · ' + familia.refugio.coste_siguiente + ' 🍬' : 'Refugio al máximo'"></span>
                                            </button>
                                            <button
                                                type="button"
                                                x-show="familia.refugio.nivel > 0"
                                                @click="alternarInsalubres(familia.evolution_chain_id)"
                                                :disabled="granjaAccionando"
                                                class="mt-2 w-full px-3 py-1.5 rounded-control text-3xs font-semibold border transition-colors disabled:opacity-50 disabled:cursor-not-allowed border-danger text-danger-ink hover:bg-danger-soft"
                                                x-text="familia.refugio.insalubres ? 'Activar condiciones salubres' : 'Activar condiciones insalubres'"
                                            ></button>
                                        </div>
                                    </div>

                                    <!-- Complementos -->
                                    <div>
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-sm font-bold text-ink">🧪 Complementos EV</p>
                                            <span class="text-xs font-semibold text-primary-ink" x-text="'Total familia: +' + familia.ev_total + ' EV'"></span>
                                        </div>
                                        <p x-show="familia.refugio.insalubres" class="text-xs text-danger-ink mt-1">
                                            Desactivados mientras las condiciones insalubres estén activas.
                                        </p>
                                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 mt-2">
                                            <template x-for="comp in (familia.complementos || [])" :key="familia.evolution_chain_id + '-comp-' + comp.stat">
                                                <div class="rounded-control border border-line bg-surface-sunken p-2">
                                                    <div class="flex items-center justify-between gap-1">
                                                        <span class="text-xs font-semibold text-ink" x-text="comp.nombre"></span>
                                                        <span class="text-2xs font-bold text-ink-muted" x-text="'Nv ' + comp.nivel + '/' + comp.max"></span>
                                                    </div>
                                                    <p class="text-3xs text-primary-ink mt-1">+<span x-text="comp.ev"></span> EV</p>
                                                    <p class="text-2xs text-ink-muted">
                                                        <span x-show="comp.coste_ev !== null" x-text="comp.coste_ev + ' EV 🍬 + ' + comp.coste_familia + ' fam. 🍬'"></span>
                                                        <span x-show="comp.coste_ev === null">Máximo</span>
                                                    </p>
                                                    <button
                                                        type="button"
                                                        @click="mejorarComplemento(familia.evolution_chain_id, comp.stat)"
                                                        :disabled="granjaAccionando || !comp.puede_mejorar"
                                                        class="mt-2 w-full px-2 py-1.5 rounded-md text-3xs font-bold transition-colors disabled:bg-surface-sunken disabled:text-ink-muted disabled:cursor-not-allowed bg-primary text-on-primary hover:bg-primary-hover"
                                                        x-text="textoBotonComplemento(comp)"
                                                    ></button>
                                                </div>
                                            </template>
                                        </div>
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
@include('partials.tipos-badge-script')
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
        // TEMPORAL: modo QA, muestra todos los Pokémon del hábitat aunque no estén avistados.
        unlockAll: @json($unlockAll ?? false),
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
        modo: 'ruta',
        trainers: null,
        trainersLoading: false,
        selectedTrainer: null,
        showFormacionPopup: false,
        formacion: {},
        formacionContexto: 'entrenadores',

        // ─── Ruta mode (combate 3v3; default) ─────────────
        rutaNivel: 1,
        rutaCombatiendo: false,
        rutaCombatError: '',

        get selectedTeamMembers() {
            if (!this.selectedTeamId || !this.teams) return [];
            const team = this.teams.find(t => t.id === this.selectedTeamId);
            if (!team || !team.members) return [];
            return team.members.sort((a, b) => a.slot - b.slot).filter(m => m.reclutado && m.reclutado.pokemon);
        },

        async toggleEntrenadores() {
            if (this.modo === 'entrenadores') {
                this.modo = 'ruta';
                this.selectedTrainer = null;
                this.selectedLevel = null;
                return;
            }
            this.modo = 'entrenadores';
            this.selectedLevel = null;
            this.selectedTrainer = null;
            await this.loadTrainers();
        },

        // Exploraciones (modo individual = pokemon). Toggle: vuelve al modo por defecto ruta.
        toggleExploraciones() {
            if (this.modo === 'pokemon') {
                this.modo = 'ruta';
                this.selectedLevel = null;
                return;
            }
            this.modo = 'pokemon';
            this.selectedLevel = null;
            this.cargarFavoritos();
        },

        // Vuelta explícita al modo por defecto (botón "Combate de ruta").
        setModoRuta() {
            this.modo = 'ruta';
            this.selectedTrainer = null;
            this.selectedLevel = null;
        },

        selectRutaNivel(nivel) {
            this.rutaNivel = nivel;
            this.rutaCombatError = '';
        },

        openRutaFormacionPopup() {
            if (!this.selectedTeamId) {
                return;
            }
            this.formacionContexto = 'ruta';
            this.selectedTrainer = null;
            // Formación completa (3 slots): la persistida del backend o 'vanguardia'
            // por defecto. Se envía explícitamente; el backend nunca auto-clasifica.
            this.formacion = this.formacionInicialDe(this.selectedTeamId);
            this.rutaCombatError = '';
            this.showFormacionPopup = true;
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
            this.formacionContexto = 'entrenadores';
            this.rutaCombatError = '';
            // Formación completa (3 slots) desde la persistida del equipo.
            this.formacion = this.formacionInicialDe(this.selectedTeamId);
            this.showFormacionPopup = true;
        },

        openFormacionPopup() {
            if (!this.selectedTeamId || !this.selectedTrainer) {
                return;
            }
            this.formacionContexto = 'entrenadores';
            this.formacion = this.formacionInicialDe(this.selectedTeamId);
            this.showFormacionPopup = true;
        },

        /** Formación inicial para un equipo: formación persistida o 'vanguardia'. */
        formacionInicialDe(teamId) {
            const team = this.teams.find(t => t.id === teamId);
            const persistida = team && team.formacion ? team.formacion : {};
            return {
                1: persistida[1] || 'vanguardia',
                2: persistida[2] || 'vanguardia',
                3: persistida[3] || 'vanguardia',
            };
        },

        toggleFormacionSlot(slot) {
            this.formacion[slot] = this.formacion[slot] === 'vanguardia' ? 'retaguardia' : 'vanguardia';
        },

        closeFormacionPopup() {
            this.showFormacionPopup = false;
            this.selectedTrainer = null;
            this.rutaCombatError = '';
        },

        async confirmarCombate() {
            if (this.formacionContexto === 'ruta') {
                await this.confirmarCombateRuta();
                return;
            }
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

        async confirmarCombateRuta() {
            if (!this.selectedTeamId || this.rutaCombatiendo) return;
            this.rutaCombatiendo = true;
            this.rutaCombatError = '';
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/ruta/iniciar', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        team_id: this.selectedTeamId,
                        nivel: this.rutaNivel,
                        formacion: this.formacion,
                    }),
                });
                if (!response.ok) {
                    // 422 (equipo ≠3, pool vacío...) → error inline en el popup, sin redirigir.
                    const err = await response.json().catch(() => ({}));
                    this.rutaCombatError = err.message || err.error || 'No se pudo iniciar el combate de ruta.';
                    return;
                }
                const data = await response.json();
                this.showFormacionPopup = false;
                window.location.href = data.redirect || '/combate?battle_id=' + data.battle_id;
            } catch (e) {
                console.error('Error iniciando combate de ruta:', e);
                this.rutaCombatError = 'Error al iniciar combate: ' + e.message;
            } finally {
                this.rutaCombatiendo = false;
            }
        },

        // ─── Mazmorra ─────────────────────────────────────────────────────
        showMazmorraModal: false,
        mazmorraLoading: false,
        mazmorraError: '',
        pisos: null,
        pisoSeleccionado: null,
        mazmorraTeamId: null,
        mazmorraTeamName: '',
        mazmorraFormacion: {},
        mazmorraCombatiendo: false,
        mazmorraCombatError: '',

        get mazmorraTeamMembers() {
            if (!this.mazmorraTeamId || !this.teams) return [];
            const team = this.teams.find(t => t.id === this.mazmorraTeamId);
            if (!team || !team.members) return [];
            return team.members.sort((a, b) => a.slot - b.slot).filter(m => m.reclutado && m.reclutado.pokemon);
        },

        async openMazmorraModal() {
            this.showMazmorraModal = true;
            this.pisoSeleccionado = null;
            this.mazmorraTeamId = null;
            this.mazmorraFormacion = {};
            this.mazmorraCombatError = '';
            await this.loadMazmorra();
        },

        closeMazmorraModal() {
            this.showMazmorraModal = false;
            this.pisoSeleccionado = null;
            this.mazmorraCombatError = '';
        },

        async loadMazmorra() {
            this.mazmorraLoading = true;
            this.mazmorraError = '';
            try {
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/mazmorra', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('Error al cargar la mazmorra');
                const data = await response.json();
                this.pisos = Array.isArray(data) ? data : (data.pisos || []);
            } catch (e) {
                console.error('Error loading mazmorra:', e);
                this.mazmorraError = 'No se pudo cargar la mazmorra. Inténtalo de nuevo.';
            } finally {
                this.mazmorraLoading = false;
            }
        },

        estadoPisoClass(piso) {
            if (piso.estado === 'ganado') return 'border-success bg-success-soft';
            if (piso.estado === 'cooldown') return 'border-warning bg-warning-soft opacity-70';
            return 'border-line hover:border-primary';
        },

        estadoPisoIconoClass(piso) {
            if (piso.estado === 'ganado') return 'bg-success-soft text-success-ink';
            if (piso.estado === 'cooldown') return 'bg-warning-soft text-warning-ink';
            return 'bg-primary-soft text-primary-ink';
        },

        estadoPisoTexto(piso) {
            if (piso.estado === 'ganado') return '✔ Piso superado';
            if (piso.estado === 'cooldown') {
                const hasta = piso.cooldown_hasta;
                if (hasta) return 'Cooldown hasta ' + new Date(hasta).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
                return 'Cooldown activo (1 h)';
            }
            return 'Disponible — enfréntate al pokémon más débil del hábitat';
        },

        selectPiso(piso) {
            if (piso.estado !== 'disponible') return;
            this.pisoSeleccionado = piso;
            this.mazmorraTeamId = null;
            this.mazmorraFormacion = {};
            this.mazmorraCombatError = '';
        },

        selectMazmorraTeam(id, name) {
            this.mazmorraTeamId = id;
            this.mazmorraTeamName = name;
            this.mazmorraFormacion = {};
            const team = this.teams.find(t => t.id === id);
            if (team && team.members) {
                team.members.forEach(m => {
                    this.mazmorraFormacion[m.slot] = 'vanguardia';
                });
            }
        },

        async confirmarPiso() {
            if (!this.pisoSeleccionado || !this.mazmorraTeamId || this.mazmorraCombatiendo) return;
            this.mazmorraCombatiendo = true;
            this.mazmorraCombatError = '';
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/mazmorra/' + this.pisoSeleccionado.piso + '/combatir', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        team_id: this.mazmorraTeamId,
                        formacion: this.mazmorraFormacion,
                    }),
                });
                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    this.mazmorraCombatError = err.message || 'Error al iniciar combate';
                    return;
                }
                const data = await response.json();
                this.showMazmorraModal = false;
                window.location.href = data.redirect || '/combate?battle_id=' + data.battle_id;
            } catch (e) {
                this.mazmorraCombatError = 'Error al iniciar combate: ' + e.message;
            } finally {
                this.mazmorraCombatiendo = false;
            }
        },

        // Granja
        showGranjaModal: false,
        granjaLoading: false,
        granjaError: '',
        granja: null,
        granjaAccionando: false,

        openGranjaModal() {
            this.showGranjaModal = true;
            this.granjaError = '';
            this.loadGranja();
        },

        closeGranjaModal() {
            this.showGranjaModal = false;
        },

        async loadGranja() {
            this.granjaLoading = true;
            this.granjaError = '';
            try {
                const response = await fetch('/api/habitats/{{ $habitat['id'] }}/farm', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || 'No se pudo cargar la granja');
                }
                this.granja = await response.json();
            } catch (e) {
                console.error('Error loading granja:', e);
                this.granjaError = e.message || 'No se pudo cargar la granja.';
                this.granja = null;
            } finally {
                this.granjaLoading = false;
            }
        },

        async accionGranja(url) {
            if (this.granjaAccionando) return;
            this.granjaAccionando = true;
            this.granjaError = '';
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });
                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || 'No se pudo completar la acción.');
                }
                this.granja = await response.json();
            } catch (e) {
                console.error('Error updating granja:', e);
                this.granjaError = e.message || 'Error de conexión al actualizar la granja.';
            } finally {
                this.granjaAccionando = false;
            }
        },

        saldoFamilia(chainId) {
            const item = (this.granja?.inventario?.familia || []).find(
                (caramelo) => Number(caramelo.evolution_chain_id) === Number(chainId)
            );

            return item?.cantidad ?? 0;
        },

        textoBotonComplemento(comp) {
            if (comp?.coste_ev === null) return 'Máximo';
            if (comp?.motivo === 'refugio') return 'Refugio primero';
            if (comp?.motivo === 'insalubres') return 'Insalubres';
            if (comp?.motivo === 'saldo') return 'Faltan caramelos';

            return comp?.nivel === 0 ? 'Crear' : 'Subir';
        },

        mejorarNido(chainId) {
            return this.accionGranja('/api/habitats/{{ $habitat['id'] }}/farm/nests/' + chainId + '/upgrade');
        },

        mejorarRefugio(chainId) {
            return this.accionGranja('/api/habitats/{{ $habitat['id'] }}/farm/shelters/' + chainId + '/upgrade');
        },

        alternarInsalubres(chainId) {
            return this.accionGranja('/api/habitats/{{ $habitat['id'] }}/farm/shelters/' + chainId + '/toggle-unhealthy');
        },

        mejorarComplemento(chainId, stat) {
            return this.accionGranja('/api/habitats/{{ $habitat['id'] }}/farm/shelters/' + chainId + '/complements/' + encodeURIComponent(stat) + '/upgrade');
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
            return this.teams || [];
        },

        init() {
            // Cargar favoritos para la exploración individual. Tolerante: si el
            // endpoint aún no está disponible, se muestra el estado vacío/error.
            this.cargarFavoritos();
        },

        /** URL del sprite de un reclutado; shiny usa el sufijo _s. */
        iconoReclutado(reclutado) {
            if (!reclutado || !reclutado.pokemon_id) return '/images/iconos_webp/0.webp';
            if (reclutado.es_shiny) return '/images/iconos_webp/shiny/' + reclutado.pokemon_id + '_s.webp';
            return '/images/iconos_webp/' + reclutado.pokemon_id + '.webp';
        },

        /** Fallback de sprites: shiny → normal → 0.webp → ocultar. */
        onSpriteError(event, pokemonId, esShiny) {
            const img = event?.target;
            if (!img) return;
            if (!pokemonId) {
                img.style.display = 'none';
                return;
            }
            const intento = img.dataset.intento || (esShiny ? 'shiny' : 'normal');
            if (intento === 'shiny') {
                img.dataset.intento = 'normal';
                img.src = '/images/iconos_webp/' + pokemonId + '.webp';
                return;
            }
            if (intento === 'normal') {
                img.dataset.intento = 'cero';
                img.src = '/images/iconos_webp/0.webp';
                return;
            }
            img.style.display = 'none';
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
            this.selectedTeamId = id;
            this.selectedTeamName = name;
            if (this.modo === 'pokemon') {
                this.checkAndOpenModal();
            }
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
            return this.unlockAll || this.sightedPokemonIds.includes(pokemonId);
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
                if (this.durationMode === 'hours') {
                    params.set('duracion_horas', String(this.durationHours));
                } else if (this.durationMode === 'return_time') {
                    params.set('return_time', this.returnTime);
                }
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
                'Bajo': 'bg-success-soft text-success-ink',
                'Medio': 'bg-warning-soft text-warning-ink',
                'Alto': 'bg-warning-soft text-warning-ink',
                'Extremo': 'bg-danger-soft text-danger-ink',
            };
            return clases[riesgo] || 'bg-surface-sunken text-ink-muted';
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
