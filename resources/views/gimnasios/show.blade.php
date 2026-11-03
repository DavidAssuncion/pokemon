@extends('layouts.app')

@section('title', 'Gimnasio')

@section('content')
@php
    $tipoBadges = \Src\Shared\UI\TipoBadges::etiquetasPorId();
    $defaultBadge = \Src\Shared\UI\TipoBadges::DEFAULT['label'];
@endphp

<div x-data="gimnasioShow('{{ $slug }}')" x-init="init()">
    <div class="max-w-4xl mx-auto space-y-6">
        {{-- Volver --}}
        <a href="{{ route('gimnasios.index') }}" aria-label="Volver a gimnasios" title="Volver a gimnasios"
           class="inline-flex items-center gap-2 rounded-control text-sm font-medium text-ink-muted transition-colors hover:text-primary-ink focus-ring">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Volver a gimnasios
        </a>

        {{-- Loading --}}
        <div x-show="loading" x-cloak role="status" class="flex items-center gap-2 text-sm text-ink-muted">
            <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Cargando gimnasio...
        </div>

        {{-- Error --}}
        <div x-show="error" x-cloak role="alert" class="text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2">
            <span x-text="error"></span>
        </div>

        <template x-if="!loading && !error && gym">
            <div class="space-y-6">
                {{-- Cabecera --}}
                <div class="card-surface">
                    <div class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-4xl shrink-0" aria-hidden="true"><img :src="'images/medallas/' + gym.tipo_slug + '.webp'" :alt="gym.tipo_medalla" onerror="this.style.display='none'"></span>
                                <div class="min-w-0">
                                    <h1 class="text-xl font-bold text-ink truncate" x-text="gym.tipo_medalla"></h1>
                                    <span class="inline-block rounded-full px-2 py-0.5 text-2xs font-bold uppercase text-on-primary"
                                          :style="'background-color: var(--type-' + gym.tipo_slug + ')'"
                                          x-text="gym.tipo_nombre"></span>
                                    <div class="flex flex-wrap items-center gap-2 mt-4">
                                        <span class="text-xs text-ink-muted">
                                            Nivel mínimo: <strong x-text="'Nv ' + gym.nivel_minimo"></strong>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            {{-- Etapa actual destacada --}}
                            <div class="text-right">
                                <template x-if="gym.estado === 'disponible'">
                                    <div>
                                        <span class="text-xs text-ink-muted">Etapa actual</span>
                                        <p class="text-2xl font-bold text-primary-ink"
                                           x-text="gym.etapa_actual + ' / 4'"></p>
                                    </div>
                                </template>
                                <template x-if="gym.estado === 'completado'">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-success-ink" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm font-bold text-success-ink">Medalla ganada</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Aviso bloqueado --}}
                <div x-show="gym.estado === 'bloqueado'" x-cloak role="alert"
                     class="flex items-center gap-3 rounded-card border border-warning bg-warning-soft px-4 py-3 text-sm text-warning-ink">
                    <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                    </svg>
                    <p class="font-medium" x-text="'Requiere Nv ' + gym.nivel_minimo + ' de jugador para acceder a este gimnasio.'"></p>
                </div>

                {{-- Etapas --}}
                <div class="space-y-3">
                    <template x-for="(etapa, idx) in gym.etapas" :key="etapa.etapa">
                        <div class="card-surface"
                             :class="etapaEstado(etapa.etapa) === 'actual' && gym.estado === 'disponible'
                                 ? 'border-primary ring-1 ring-primary'
                                 : ''">
                            <div class="px-4 py-3 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    {{-- Icono de estado --}}
                                    <template x-if="etapaEstado(etapa.etapa) === 'superada'">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-success-soft">
                                            <svg class="h-4 w-4 text-success-ink" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                    </template>
                                    <template x-if="etapaEstado(etapa.etapa) === 'actual'">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-soft">
                                            <span class="h-3 w-3 animate-pulse rounded-full bg-primary"></span>
                                        </span>
                                    </template>
                                    <template x-if="etapaEstado(etapa.etapa) === 'bloqueada'">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-sunken">
                                            <svg class="h-4 w-4 text-ink-muted" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                    </template>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-ink" x-text="etapa.nombre"></p>
                                        <p class="text-xs text-ink-muted" x-text="etapaDescripcion(etapa.etapa)"></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    {{-- Avatar/placeholder genérico de entrenador (sin revelar su equipo) --}}
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-surface-sunken"
                                          :class="etapaEstado(etapa.etapa) === 'actual' ? 'bg-primary-soft' : ''"
                                          :title="'Entrenador: ' + etapa.nombre"
                                          aria-hidden="true">
                                        <svg class="h-5 w-5 text-ink-muted" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                    </span>
                                    {{-- Botón combatir (solo etapa actual y gimnasio disponible) --}}
                                    <template x-if="etapaEstado(etapa.etapa) === 'actual' && gym.estado === 'disponible'">
                                        <button @click="openCombatPopup()"
                                                class="shrink-0 rounded-control bg-danger px-4 py-2 text-sm font-bold uppercase tracking-wide text-on-primary transition-colors hover:bg-danger/90 focus-ring disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="combating">
                                            ¡Combatir!
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- Popup: selección de equipo + formación (patrón de habitats/show) --}}
    <template x-if="showFormacionPopup">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true" aria-labelledby="formacion-modal-title"
             @keydown.escape.window="closeFormacionPopup()">
            <div class="absolute inset-0 scrim" @click="closeFormacionPopup()"></div>
            <div class="relative w-full max-w-md rounded-panel bg-surface-raised p-6 shadow-modal">
                <h3 id="formacion-modal-title" class="mb-4 text-lg font-bold text-ink">Configurar Formación</h3>
                <p class="mb-4 text-sm text-ink-muted">
                    Selecciona un equipo y ajusta la posición de cada pokémon antes del combate contra
                    <strong class="text-ink" x-text="gym ? gym.tipo_medalla : ''"></strong>.
                </p>

                {{-- Error del popup --}}
                <div x-show="popupError" x-cloak role="alert" class="text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2 mb-4">
                    <span x-text="popupError"></span>
                </div>

                {{-- Selección de equipo --}}
                <div class="space-y-2 mb-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Equipo</p>
                    <template x-if="teams.length === 0">
                        <p class="py-2 text-center text-sm text-ink-subtle">No tienes equipos creados</p>
                    </template>
                    <template x-for="team in teams" :key="team.id">
                        <button type="button" @click="selectTeam(team.id, team.name)"
                                :class="selectedTeamId === team.id
                                    ? 'border-primary bg-primary-soft'
                                    : 'border-line hover:border-line-strong'"
                                class="flex w-full items-center justify-between rounded-control border-2 p-3 text-left transition-all focus-ring"
                                :aria-label="'Seleccionar equipo ' + team.name">
                            <span class="text-sm font-medium text-ink" x-text="team.name"></span>
                            <template x-if="selectedTeamId === team.id">
                                <svg class="h-5 w-5 text-primary-ink" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </template>
                        </button>
                    </template>
                </div>

                {{-- Formación --}}
                <div x-show="selectedTeamId" x-cloak class="space-y-3 mb-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Formación</p>
                    <template x-for="miembro in (selectedTeamMembers || [])" :key="miembro.id">
                        <div class="flex items-center justify-between rounded-control border border-line bg-surface-sunken p-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <x-sprite
                                    size="64"
                                    x-bind:src="iconoReclutado(miembro.reclutado)"
                                    x-on:error="onSpriteError($event, miembro.reclutado.pokemon_id, miembro.reclutado.es_shiny)"
                                    x-bind:alt="miembro.reclutado.nombre"
                                />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink" x-text="miembro.reclutado.nombre"></p>
                                    <p class="text-xs text-ink-muted" x-text="'Posición ' + miembro.slot"></p>
                                </div>
                            </div>
                            <button type="button"
                                    @click="toggleFormacionSlot(miembro.slot)"
                                    :class="formacion[miembro.slot] === 'vanguardia'
                                        ? 'border-primary bg-primary text-on-primary'
                                        : 'border-line bg-surface-sunken text-ink'"
                                    class="shrink-0 rounded-control border px-3 py-1.5 text-xs font-bold transition-colors focus-ring"
                                    x-text="(formacion[miembro.slot] === 'vanguardia' ? 'Vanguardia' : 'Retaguardia')">
                            </button>
                        </div>
                    </template>
                    <template x-if="!selectedTeamMembers || selectedTeamMembers.length === 0">
                        <p class="py-4 text-center text-sm text-ink-subtle">Selecciona un equipo primero</p>
                    </template>
                </div>

                {{-- Botones --}}
                <div class="flex gap-3">
                    <button @click="closeFormacionPopup()"
                            class="flex-1 rounded-control bg-surface-sunken px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-line focus-ring">
                        Cancelar
                    </button>
                    <button @click="confirmarCombate()"
                            :disabled="!selectedTeamId || combating"
                            class="flex-1 rounded-control bg-danger px-4 py-2.5 text-sm font-bold text-on-primary transition-colors hover:bg-danger/90 disabled:cursor-not-allowed disabled:opacity-50 focus-ring">
                        <span x-show="!combating">¡Combatir!</span>
                        <span x-show="combating" x-cloak class="inline-flex items-center gap-2">
                            <svg class="w-8 h-8 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Iniciando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

@push('scripts')
<script>
function gimnasioShow(slug) {
    return {
        slug: slug,
        gym: null,
        loading: false,
        error: '',

        // Estado del popup de formación
        showFormacionPopup: false,
        selectedTeamId: null,
        selectedTeamName: '',
        formacion: {},
        popupError: '',
        combating: false,
        teams: @json($teams),

        // Mapeo tipo int (TipoPokemon) → nombre español.
        tipoBadges: @json($tipoBadges),

        tipoBadge(tipo) {
            return this.tipoBadges[tipo] || @json($defaultBadge);
        },

        get selectedTeamMembers() {
            if (!this.selectedTeamId || !this.teams) return [];
            const team = this.teams.find(t => t.id === this.selectedTeamId);
            if (!team || !team.members) return [];
            return team.members
                .slice()
                .sort((a, b) => a.slot - b.slot)
                .filter(m => m.reclutado && m.reclutado.pokemon);
        },

        // Estado de cada etapa respecto a la actual: superada / actual / bloqueada.
        etapaEstado(etapa) {
            const actual = this.gym ? this.gym.etapa_actual : 1;
            if (etapa < actual) return 'superada';
            if (etapa === actual) return 'actual';
            return 'bloqueada';
        },

        // Texto descriptivo de cada etapa según su estado.
        etapaDescripcion(etapa) {
            const estado = this.etapaEstado(etapa);
            if (estado === 'superada') return 'Etapa superada';
            if (estado === 'actual') return 'Etapa actual — ¡enfréntate a ella!';
            const anterior = this.gym && this.gym.etapas
                ? this.gym.etapas.find(e => e.etapa === etapa - 1)?.nombre
                : null;
            return anterior ? 'Derrota antes a ' + anterior : 'Etapa bloqueada';
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

        async init() {
            this.loading = true;
            this.error = '';
            try {
                const response = await fetch('/api/gimnasios/' + this.slug, {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    throw new Error(err.message || 'Error al cargar el gimnasio');
                }
                this.gym = await response.json();
            } catch (e) {
                console.error('Error loading gym detail:', e);
                this.error = e.message || 'No se pudo cargar el gimnasio.';
            } finally {
                this.loading = false;
            }
        },

        // Abre el popup de selección de equipo + formación.
        openCombatPopup() {
            if (!this.gym || this.gym.estado !== 'disponible') return;
            this.selectedTeamId = null;
            this.selectedTeamName = '';
            this.formacion = {};
            this.popupError = '';
            this.showFormacionPopup = true;
        },

        selectTeam(id, name) {
            this.selectedTeamId = id;
            this.selectedTeamName = name;
            this.formacion = this.formacionInicialDe(id);
        },

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
            this.popupError = '';
        },

        async confirmarCombate() {
            if (!this.selectedTeamId || this.combating) return;
            this.combating = true;
            this.popupError = '';
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/api/gimnasios/' + this.slug + '/combatir', {
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
                    this.popupError = err.message || 'Error al iniciar combate';
                    return;
                }
                const data = await response.json();
                this.showFormacionPopup = false;
                window.location.href = data.redirect || '/combate?battle_id=' + data.battle_id;
            } catch (e) {
                this.popupError = 'Error al iniciar combate: ' + e.message;
            } finally {
                this.combating = false;
            }
        },
    };
}
</script>
@endpush
@endsection