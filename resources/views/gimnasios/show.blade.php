@extends('layouts.app')

@section('title', 'Gimnasio')

@section('content')
@php
    // Mapeo tipo (int de TipoPokemon) → nombre en español + clases de badge (compartido con index).
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
    $cardPanelClass = 'bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden';
@endphp

<div x-data="gimnasioShow('{{ $slug }}')" x-init="init()">
    <div class="max-w-4xl mx-auto space-y-6">
        {{-- Volver --}}
        <a href="{{ route('gimnasios.index') }}" aria-label="Volver a gimnasios" title="Volver a gimnasios"
           class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Volver a gimnasios
        </a>

        {{-- Loading --}}
        <div x-show="loading" x-cloak role="status" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Cargando gimnasio...
        </div>

        {{-- Error --}}
        <div x-show="error" x-cloak role="alert" class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg px-3 py-2">
            <span x-text="error"></span>
        </div>

        <template x-if="!loading && !error && gym">
            <div class="space-y-6">
                {{-- Cabecera --}}
                <div class="{{ $cardPanelClass }}">
                    <div class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-4xl shrink-0" aria-hidden="true">🏅</span>
                                <div class="min-w-0">
                                    <h1 class="text-xl font-bold text-gray-900 dark:text-white truncate" x-text="gym.medalla"></h1>
                                    <div class="flex flex-wrap items-center gap-2 mt-1">
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full uppercase"
                                              :class="tipoBadge(gym.tipo)[1]"
                                              x-text="tipoBadge(gym.tipo)[0]"></span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            Nivel mínimo: <strong x-text="'Nv ' + gym.nivel_minimo"></strong>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            {{-- Etapa actual destacada --}}
                            <div class="text-right">
                                <template x-if="gym.estado === 'disponible'">
                                    <div>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Etapa actual</span>
                                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400"
                                           x-text="gym.etapa_actual + ' / 4'"></p>
                                    </div>
                                </template>
                                <template x-if="gym.estado === 'completado'">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm font-bold text-green-700 dark:text-green-400">✔ Medalla ganada</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Aviso bloqueado --}}
                <div x-show="gym.estado === 'bloqueado'" x-cloak role="alert"
                     class="flex items-center gap-3 px-4 py-3 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-xl text-sm text-orange-700 dark:text-orange-400">
                    <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                    </svg>
                    <p class="font-medium" x-text="'Requiere Nv ' + gym.nivel_minimo + ' de jugador para acceder a este gimnasio.'"></p>
                </div>

                {{-- Etapas --}}
                <div class="space-y-3">
                    <template x-for="(etapa, idx) in gym.etapas" :key="etapa.etapa">
                        <div class="{{ $cardPanelClass }}"
                             :class="etapaEstado(etapa.etapa) === 'actual' && gym.estado === 'disponible'
                                 ? 'border-blue-400 dark:border-blue-600 ring-1 ring-blue-200 dark:ring-blue-800'
                                 : ''">
                            <div class="px-4 py-3 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    {{-- Icono de estado --}}
                                    <template x-if="etapaEstado(etapa.etapa) === 'superada'">
                                        <span class="w-7 h-7 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                    </template>
                                    <template x-if="etapaEstado(etapa.etapa) === 'actual'">
                                        <span class="w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center shrink-0">
                                            <span class="w-3 h-3 rounded-full bg-blue-600 animate-pulse"></span>
                                        </span>
                                    </template>
                                    <template x-if="etapaEstado(etapa.etapa) === 'bloqueada'">
                                        <span class="w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                    </template>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="etapa.nombre"></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400" x-text="etapaDescripcion(etapa.etapa)"></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    {{-- Avatar/placeholder genérico de entrenador (sin revelar su equipo) --}}
                                    <span class="w-9 h-9 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center shrink-0"
                                          :class="etapaEstado(etapa.etapa) === 'actual' ? 'bg-blue-100 dark:bg-blue-900/30' : ''"
                                          :title="'Entrenador: ' + etapa.nombre"
                                          aria-hidden="true">
                                        <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                    </span>
                                    {{-- Botón combatir (solo etapa actual y gimnasio disponible) --}}
                                    <template x-if="etapaEstado(etapa.etapa) === 'actual' && gym.estado === 'disponible'">
                                        <button @click="openCombatPopup()"
                                                class="shrink-0 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-bold uppercase tracking-wide transition-colors hover:bg-red-700"
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
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeFormacionPopup()">
            <div class="absolute inset-0 bg-black/60" @click="closeFormacionPopup()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Configurar Formación</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                    Selecciona un equipo y ajusta la posición de cada pokémon antes del combate contra
                    <strong class="text-gray-900 dark:text-white" x-text="gym ? gym.medalla : ''"></strong>.
                </p>

                {{-- Error del popup --}}
                <div x-show="popupError" x-cloak role="alert" class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg px-3 py-2 mb-4">
                    <span x-text="popupError"></span>
                </div>

                {{-- Selección de equipo --}}
                <div class="space-y-2 mb-5">
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Equipo</p>
                    <template x-if="teams.length === 0">
                        <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-2">No tienes equipos creados</p>
                    </template>
                    <template x-for="team in teams" :key="team.id">
                        <button type="button" @click="selectTeam(team.id, team.name)"
                                :class="selectedTeamId === team.id
                                    ? 'border-blue-500 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                class="w-full p-3 rounded-lg border-2 text-left transition-all flex items-center justify-between"
                                :aria-label="'Seleccionar equipo ' + team.name">
                            <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="team.name"></span>
                            <template x-if="selectedTeamId === team.id">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </template>
                        </button>
                    </template>
                </div>

                {{-- Formación --}}
                <div x-show="selectedTeamId" x-cloak class="space-y-3 mb-6">
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Formación</p>
                    <template x-for="miembro in (selectedTeamMembers || [])" :key="miembro.id">
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <div class="flex items-center gap-3 min-w-0">
                                <img :src="'/images/iconos_webp/' + miembro.reclutado.pokemon_id + '.webp'" loading="lazy" decoding="async"
                                     :alt="miembro.reclutado.nombre" class="w-12 h-12 object-contain shrink-0"
                                     onerror="this.style.display='none'">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="miembro.reclutado.nombre"></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="'Posición ' + miembro.slot"></p>
                                </div>
                            </div>
                            <button type="button"
                                    @click="toggleFormacionSlot(miembro.slot)"
                                    :class="formacion[miembro.slot] === 'vanguardia'
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors shrink-0"
                                    x-text="(formacion[miembro.slot] === 'vanguardia' ? '🛡️ Vanguardia' : '⚔️ Retaguardia')">
                            </button>
                        </div>
                    </template>
                    <template x-if="!selectedTeamMembers || selectedTeamMembers.length === 0">
                        <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">Selecciona un equipo primero</p>
                    </template>
                </div>

                {{-- Botones --}}
                <div class="flex gap-3">
                    <button @click="closeFormacionPopup()"
                            class="flex-1 px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        Cancelar
                    </button>
                    <button @click="confirmarCombate()"
                            :disabled="!selectedTeamId || combating"
                            class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-bold hover:bg-red-700 transition-colors disabled:bg-gray-300 dark:disabled:bg-gray-700 disabled:text-gray-500 dark:disabled:text-gray-500 disabled:cursor-not-allowed">
                        <span x-show="!combating">¡Combatir!</span>
                        <span x-show="combating" x-cloak class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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

        // Mapeo tipo int (TipoPokemon) → [nombre español, clases badge].
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
            return anterior ? '🔒 Derrota antes a ' + anterior : '🔒 Etapa bloqueada';
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
            this.formacion = {};
            const team = this.teams.find(t => t.id === id);
            if (team && team.members) {
                team.members.forEach(m => {
                    this.formacion[m.slot] = 'vanguardia';
                });
            }
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