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
                                                    size="128"
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
                            @if($habitat['sin_spawns'] ?? false)
                            <p class="mb-3 rounded-control border border-warning bg-warning-soft px-3 py-2 text-xs text-warning-ink">
                                Este hábitat no tiene Pokémon salvajes: sus especies se sirven desde encuentros especiales.
                            </p>
                            @endif
                            <p x-show="avisoNivel && modo === 'pokemon'" x-cloak x-text="avisoNivel" role="status"
                               class="text-xs text-warning-ink bg-warning-soft border border-warning rounded-control px-3 py-2"></p>
                            @foreach([1,2,3] as $level)
                            @php
                                $minLvl = $habitat['min_lvl_' . $level] ?? null;
                                $sinSpawns = count($habitat['levels'][$level] ?? []) === 0;
                                $bloqueadoNivel = $sinSpawns || ($minLvl !== null && ($nivelJugador ?? 1) < $minLvl);
                                $tooltipNivel = $sinSpawns
                                    ? 'Sin Pokémon disponibles en este nivel'
                                    : ($minLvl !== null ? 'Se desbloquea a Nv ' . $minLvl : '');
                            @endphp
                            <button
                                @click="modo === 'ruta' ? selectRutaNivel({{ $level }}) : selectLevel({{ $level }})"
                                :disabled="{{ $bloqueadoNivel ? 'true' : 'false' }}"
                                @if($bloqueadoNivel) title="{{ $tooltipNivel }}" @endif
                                :class="[
                                    (modo === 'ruta' ? rutaNivel : selectedLevel) === {{ $level }}
                                        ? 'border-primary bg-primary-soft'
                                        : 'border-line hover:border-line-strong',
                                    {{ $bloqueadoNivel ? 'true' : 'false' }}
                                        ? 'opacity-60 cursor-not-allowed border-dashed'
                                        : ''
                                ]"
                                class="w-full p-4 rounded-card border-2 text-left transition-all"
                                aria-label="Nivel {{ $level }}{{ $bloqueadoNivel ? ' (bloqueado)' : '' }}"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-semibold text-ink flex items-center gap-1.5">
                                        @if($bloqueadoNivel)
                                            <svg class="w-3.5 h-3.5 text-warning-ink" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                            </svg>
                                        @endif
                                        Nivel {{ $level }}
                                    </span>
                                    <span class="flex items-center gap-2">
                                        @if($bloqueadoNivel)
                                            <span class="px-2 py-0.5 bg-warning-soft text-warning-ink text-2xs font-bold rounded-full">
                                                {{ $sinSpawns ? 'Sin Pokémon' : 'Requiere Nv ' . $minLvl }}
                                            </span>
                                        @endif
                                        <span class="text-xs text-ink-muted">{{ count($habitat['levels'][$level] ?? []) }} pokémon</span>
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @forelse($habitat['levels'][$level] ?? [] as $pokemon)
                                    @php
                                        $bloqueadoPokemon = ($pokemon['min_level'] ?? null) !== null
                                            && ($nivelJugador ?? 1) < (int) $pokemon['min_level'];
                                    @endphp
                                    @if($bloqueadoPokemon)
                                        <div class="relative w-24 h-24"
                                             title="Se desbloquea a Nv {{ $pokemon['min_level'] }}"
                                             aria-label="Pokémon bloqueado, se desbloquea a nivel {{ $pokemon['min_level'] }}">
                                            <img src="/images/misc/unknown.webp" alt="Pokémon bloqueado"
                                                 class="w-full h-full object-contain grayscale opacity-50">
                                            <span class="absolute bottom-0.5 right-0.5 rounded-full bg-warning-soft px-1.5 py-0.5 text-2xs font-bold text-warning-ink">
                                                Nv {{ $pokemon['min_level'] }}
                                            </span>
                                        </div>
                                    @else
                                    <template x-if="!isSighted({{ $pokemon['id'] ?? $pokemon['species_id'] ?? 0 }})">
                                        <div class="relative w-24 h-24" title="Aún no avistado">
                                            <img src="/images/misc/unknown.webp" alt="Pokémon no avistado"
                                                 class="w-full h-full object-contain grayscale opacity-50">
                                        </div>
                                    </template>
                                    <template x-if="isSighted({{ $pokemon['id'] ?? $pokemon['species_id'] ?? 0 }})">
                                        <div class="relative w-24 h-24">
                                            <x-sprite :src="$pokemon['icon']" :name="$pokemon['name']" size="96" :title="$pokemon['name']" />
                                        </div>
                                    </template>
                                    @endif
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
                                <div class="mb-4" :class="levelBlocked(levelIdx) ? 'opacity-60' : ''">
                                    <div class="mb-2 flex items-center justify-between">
                                        <h4 class="text-sm font-semibold text-ink" x-text="'Nivel ' + levelIdx"></h4>
                                        <span x-show="levelBlocked(levelIdx)" class="rounded-full bg-warning-soft px-2 py-0.5 text-2xs font-bold text-warning-ink"
                                              x-text="minLvls[levelIdx] ? 'Requiere Nv ' + minLvls[levelIdx] : 'Sin Pokémon'"></span>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                        <template x-for="(entrenador, eIdx) in levelData" :key="eIdx">
                                            <button
                                                @click="selectTrainer(levelIdx, entrenador.indice)"
                                                :disabled="!entrenador.desbloqueado || !selectedTeamId || levelBlocked(levelIdx)"
                                                :class="{
                                                    'border-success bg-success-soft': entrenador.desbloqueado && selectedTeamId && !levelBlocked(levelIdx),
                                                    'border-line opacity-50 cursor-not-allowed': !entrenador.desbloqueado || !selectedTeamId || levelBlocked(levelIdx),
                                                    'hover:border-success': entrenador.desbloqueado && selectedTeamId && !levelBlocked(levelIdx)
                                                }"
                                                class="w-full p-2 rounded-card border-2 text-left transition-all"
                                                :aria-label="'Entrenador ' + entrenador.indice + (spriteAlt(spriteEntrenador(entrenador.indice)) ? ' · ' + spriteAlt(spriteEntrenador(entrenador.indice)) : '')"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <img
                                                        :src="spriteUrl(spriteEntrenador(entrenador.indice))"
                                                        alt=""
                                                        aria-hidden="true"
                                                        width="80"
                                                        height="80"
                                                        loading="lazy"
                                                        decoding="async"
                                                        style="image-rendering: pixelated"
                                                        class="w-20 h-20 shrink-0 object-contain"
                                                        onerror="this.onerror=null;if(this.src.indexOf('/images/misc/trainer.webp')===-1){this.src='/images/misc/trainer.webp';}"
                                                    >
                                                    <div class="min-w-0 flex-1">
                                                        <span class="block truncate text-xs font-semibold text-ink" x-text="'Entrenador ' + entrenador.indice"></span>
                                                        <span class="block text-2xs text-ink-muted truncate" x-text="spriteAlt(spriteEntrenador(entrenador.indice))"></span>
                                                    </div>
                                                </div>
                                                <span x-show="!entrenador.desbloqueado" class="mt-1 inline-block px-1.5 py-0.5 bg-danger-soft text-danger-ink text-2xs font-bold rounded-full">Derrotado</span>
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
                    Combate de Ruta
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
