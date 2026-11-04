@extends('layouts.app')

@section('title', 'Gimnasios - Gestión Admin')

@section('content')
@php
    $tipoBadges = \Src\Shared\UI\TipoBadges::etiquetasPorId();
    $defaultBadge = \Src\Shared\UI\TipoBadges::DEFAULT['label'];
@endphp

<div x-data="gimnasioAdminApp()" x-init="init()">
    {{-- Volver --}}
    <a href="{{ route('gimnasios.index') }}" aria-label="Volver a gimnasios" title="Volver a gimnasios"
       class="inline-flex items-center gap-2 rounded-control text-sm font-medium text-ink-muted transition-colors hover:text-primary-ink focus-ring">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Volver a gimnasios
    </a>

    <div class="mt-4 card-surface">
        <div class="p-6 text-center">
            <h1 class="text-2xl font-bold text-ink">Gestión-Admin de Gimnasios</h1>
            <p class="text-sm text-ink-muted mt-1">
                Administra los gimnasios: edita datos y los entrenadores de cada etapa (etapas 1-4).
            </p>
            <button
                @click="openGestionModal()"
                class="mt-5 inline-flex items-center gap-2 rounded-control bg-primary px-6 py-3 text-sm font-bold uppercase tracking-wide text-on-primary transition-colors hover:bg-primary-hover focus-ring"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                GESTION-ADMIN
            </button>
        </div>
    </div>

    {{-- ══════════════ CAPA 1: Popup listado de TODOS los gyms ══════════════ --}}
    <template x-if="showGestionModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true" aria-labelledby="gestion-modal-title"
             @keydown.escape.window="closeGestionModal()">
            <div class="absolute inset-0 scrim" @click="closeGestionModal()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-line">
                    <h2 id="gestion-modal-title" class="text-lg font-bold text-ink">Todos los gimnasios</h2>
                    <button @click="closeGestionModal()" class="rounded-control p-2 text-ink-muted hover:bg-surface-sunken focus-ring" aria-label="Cerrar">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-6">
                    <div x-show="gestionLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-ink-muted">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Cargando gimnasios...
                    </div>

                    <div x-show="gestionError" x-cloak role="alert" class="text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2">
                        <span x-text="gestionError"></span>
                        <button @click="loadGyms()" class="ml-2 underline font-medium">Reintentar</button>
                    </div>

                    <template x-if="!gestionLoading && !gestionError && gyms.length === 0">
                        <div class="text-center py-10 text-ink-muted">
                            <p>No hay gimnasios disponibles.</p>
                        </div>
                    </template>

                    <div x-show="!gestionLoading && !gestionError" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <template x-for="gym in gyms" :key="gym.slug">
                            <button
                                type="button"
                                @click="openGymDetail(gym)"
                                class="group card-surface p-4 flex items-start justify-between gap-3 text-left transition-all hover:border-primary hover:shadow-modal"
                                :aria-label="'Gestionar ' + gym.tipo_medalla"
                                :title="'Gestionar ' + gym.tipo_medalla"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-ink truncate" x-text="gym.tipo_medalla"></p>
                                        <p class="text-xs text-ink-muted" x-text="'Slug: ' + gym.slug"></p>
                                        <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                            <span class="px-2 py-0.5 text-2xs font-bold rounded-full uppercase text-on-primary"
                                                  :style="'background-color: var(--type-' + gym.tipo_slug + ')'"
                                                  x-text="gym.tipo_nombre || tipoBadge(gym.tipo)"></span>
                                            <span class="text-2xs text-ink-muted" x-text="'Niv mín ' + gym.nivel_minimo"></span>
                                        </div>
                                    </div>
                                </div>
                                <svg class="w-5 h-5 shrink-0 text-ink-subtle group-hover:text-primary-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- ══════════════ CAPA 2: Popup detalle de gym (4 pestañas + pokémon + edición) ══════════════ --}}
    <template x-if="showDetailModal">
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4"
             role="dialog" aria-modal="true" aria-labelledby="detail-modal-title"
             @keydown.escape.window="closeDetailModal()">
            <div class="absolute inset-0 scrim" @click="closeDetailModal()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-line">
                    <h2 id="detail-modal-title" class="text-lg font-bold capitalize text-ink" x-text="detail ? detail.tipo_medalla : ''"></h2>
                    <button @click="closeDetailModal()" class="rounded-control p-2 text-ink-muted hover:bg-surface-sunken focus-ring" aria-label="Cerrar">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <div x-show="detailLoading" x-cloak role="status" class="flex items-center gap-2 p-6 text-sm text-ink-muted">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Cargando detalle...
                    </div>

                    <div x-show="detailError" x-cloak role="alert" class="text-sm text-danger-ink bg-danger-soft border border-danger rounded-control m-6 px-3 py-2">
                        <span x-text="detailError"></span>
                        <button @click="loadDetail(currentSlug)" class="ml-2 underline font-medium">Reintentar</button>
                    </div>

                    <template x-if="!detailLoading && !detailError && detail">
                        <div class="p-6 space-y-6">
                            {{-- Datos editables --}}
                            <section class="space-y-3" aria-labelledby="datos-gym-heading">
                                <h3 id="datos-gym-heading" class="text-xs font-bold uppercase tracking-wide text-ink-muted">Datos del gimnasio</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="mb-1 block text-2xs font-medium text-ink-muted" for="edit-medalla">Medalla</label>
                                        <input id="edit-medalla" type="text" x-model="form.medalla"
                                               class="w-full rounded-control border border-line bg-surface px-3 py-2 text-sm text-ink focus-ring">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-2xs font-medium text-ink-muted" for="edit-tipo">Tipo</label>
                                        <select id="edit-tipo" x-model.number="form.tipo"
                                                class="w-full rounded-control border border-line bg-surface px-3 py-2 text-sm text-ink focus-ring">
                                            <template x-for="(badge, tipoId) in tipoBadges" :key="tipoId">
                                                <option :value="Number(tipoId)" x-text="badge"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-2xs font-medium text-ink-muted" for="edit-nivel-minimo">Nivel mínimo</label>
                                        <input id="edit-nivel-minimo" type="number" min="1" max="100" x-model.number="form.nivel_minimo"
                                               class="w-full rounded-control border border-line bg-surface px-3 py-2 text-sm text-ink focus-ring">
                                    </div>
                                </div>
                            </section>

                            {{-- 4 pestañas (etapas / entrenadores) --}}
                            <section aria-labelledby="etapas-heading">
                                <h3 id="etapas-heading" class="text-xs font-bold uppercase tracking-wide text-ink-muted mb-2">Entrenadores por etapa</h3>
                                <div class="border-b border-line">
                                    <nav class="flex gap-2" role="tablist" aria-label="Etapas del gimnasio">
                                        <template x-for="etapa in [1,2,3,4]" :key="'tab-'+etapa">
                                            <button
                                                type="button"
                                                role="tab"
                                                :aria-selected="activeTab === etapa"
                                                :class="activeTab === etapa
                                                    ? 'border-primary text-primary-ink'
                                                    : 'border-transparent text-ink-muted hover:text-ink'"
                                                @click="activeTab = etapa"
                                                class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                                                x-text="'Etapa ' + etapa"
                                            ></button>
                                        </template>
                                    </nav>
                                </div>

                                <div class="mt-4">
                                    <template x-for="etapa in [1,2,3,4]" :key="'panel-'+etapa">
                                        <div x-show="activeTab === etapa" x-cloak class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="mb-1 block text-2xs font-medium text-ink-muted" x-text="'Vanguardia (etapa ' + etapa + ') — species_id'"></label>
                                                <input type="text"
                                                       x-model="form.etapas[etapa].vanguardia"
                                                       placeholder="Ej: 268, 266"
                                                       class="w-full rounded-control border border-line bg-surface px-3 py-2 text-sm text-ink focus-ring">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-2xs font-medium text-ink-muted" x-text="'Retaguardia (etapa ' + etapa + ') — species_id'"></label>
                                                <input type="text"
                                                       x-model="form.etapas[etapa].retaguardia"
                                                       placeholder="Ej: 900"
                                                       class="w-full rounded-control border border-line bg-surface px-3 py-2 text-sm text-ink focus-ring">
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </section>

                            {{-- Pokémon del tipo del gym, SOLO última evolución --}}
                            <section aria-labelledby="pokemon-heading">
                                <div class="flex items-center justify-between mb-2">
                                    <h3 id="pokemon-heading" class="text-xs font-bold uppercase tracking-wide text-ink-muted">
                                        Pokémon de tipo <span class="text-primary-ink" x-text="tipoBadge(detail.tipo)"></span>
                                        <span class="text-ink-subtle">(última evolución)</span>
                                    </h3>
                                    <span class="text-2xs text-ink-muted" x-text="pokemonFiltered.length + ' pokémon'"></span>
                                </div>

                                <div x-show="pokemonLoading" x-cloak role="status" class="flex items-center gap-2 text-sm text-ink-muted">
                                    <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        Cargando pokémon...
                                </div>
                                <div x-show="pokemonError" x-cloak role="alert" class="text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2">
                                    <span x-text="pokemonError"></span>
                                </div>
                                <template x-if="!pokemonLoading && !pokemonError && pokemonFiltered.length === 0">
                                    <div class="rounded-card border border-dashed border-line py-8 text-center text-ink-muted">
                                        <p>No hay pokémon de este tipo con última evolución.</p>
                                    </div>
                                </template>

                                <div x-show="!pokemonLoading && !pokemonError" class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-6 gap-2">
                                    <template x-for="p in pokemonFiltered" :key="p.species_id">
                                        <div class="rounded-control border border-line bg-surface-sunken p-2 text-center">
                                            <x-sprite
                                                size="96"
                                                wrapper-class="mx-auto"
                                                x-bind:src="'/images/iconos_webp/' + p.species_id + '.webp'"
                                                x-bind:alt="p.name"
                                                x-bind:title="p.name + ' (species ' + p.species_id + ')'"
                                            />
                                            <p class="mt-1 truncate text-2xs text-ink" x-text="p.name"></p>
                                        </div>
                                    </template>
                                </div>
                            </section>

                            {{-- Acciones --}}
                            <div x-show="saveMsg" x-cloak class="px-3 py-2 rounded-lg text-sm font-medium"
                                 :class="saveMsgType === 'error'
                                     ? 'bg-danger-soft border border-danger text-danger-ink'
                                     : 'bg-success-soft border border-success text-success-ink'"
                                 x-text="saveMsg"></div>

                            <div class="flex gap-3 border-t border-line pt-2">
                                <button @click="closeDetailModal()"
                                        class="flex-1 rounded-control bg-surface-sunken px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-line focus-ring">
                                    Cancelar
                                </button>
                                <button @click="guardarGym()"
                                        :disabled="saving"
                                        class="flex-1 rounded-control bg-primary px-4 py-2.5 text-sm font-bold text-on-primary transition-colors hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50 focus-ring">
                                    <span x-show="!saving">Guardar cambios</span>
                                    <span x-show="saving" x-cloak class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        Guardando...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>

@push('scripts')
<script>
function gimnasioAdminApp() {
    return {
        // Datos
        gyms: [],
        detail: null,
        currentSlug: null,
        activeTab: 1,
        form: {
            medalla: '',
            tipo: null,
            nivel_minimo: null,
            etapas: { 1: { vanguardia: '', retaguardia: '' }, 2: { vanguardia: '', retaguardia: '' }, 3: { vanguardia: '', retaguardia: '' }, 4: { vanguardia: '', retaguardia: '' } },
        },
        // Pokémon del tipo del gym (última evolución)
        allPokemon: [],
        pokemonLoading: false,
        pokemonError: '',
        // Estados loading/error
        gestionLoading: false,
        gestionError: '',
        detailLoading: false,
        detailError: '',
        saving: false,
        saveMsg: '',
        saveMsgType: 'success',
        // Modales
        showGestionModal: false,
        showDetailModal: false,

        // Mapeo tipo int → nombre español.
        tipoBadges: @json($tipoBadges),

        tipoBadge(tipo) {
            return this.tipoBadges[tipo] || @json($defaultBadge);
        },

        init() {},

        openGestionModal() {
            this.showGestionModal = true;
            this.gestionError = '';
            this.loadGyms();
        },

        closeGestionModal() {
            this.showGestionModal = false;
        },

        async loadGyms() {
            this.gestionLoading = true;
            this.gestionError = '';
            try {
                const resp = await fetch('/api/admin/gyms', { headers: { 'Accept': 'application/json' } });
                if (!resp.ok) throw new Error('Error al cargar gimnasios');
                const gyms = await resp.json();
                this.gyms = Array.isArray(gyms) ? gyms : (gyms.data || []);
            } catch (e) {
                console.error('Error loading gyms:', e);
                this.gestionError = 'No se pudieron cargar los gimnasios. Inténtalo de nuevo.';
            } finally {
                this.gestionLoading = false;
            }
        },

        // ─── Detalle + edición ────────────────────────────────────────────
        openGymDetail(gym) {
            this.currentSlug = gym.slug;
            this.activeTab = 1;
            this.detail = null;
            this.form = { medalla: '', tipo: null, nivel_minimo: null, etapas: { 1: { vanguardia: '', retaguardia: '' }, 2: { vanguardia: '', retaguardia: '' }, 3: { vanguardia: '', retaguardia: '' }, 4: { vanguardia: '', retaguardia: '' } } };
            this.saveMsg = '';
            this.pokemonError = '';
            this.showDetailModal = true;
            this.loadDetail(gym.slug);
        },

        closeDetailModal() {
            this.showDetailModal = false;
            this.detail = null;
        },

        // Normaliza un array de species_id a string "a, b, c" para el input.
        idsATexto(ids) {
            if (!Array.isArray(ids)) return '';
            return ids.join(', ');
        },

        idsDe(etapa, lado) {
            const raw = this.form.etapas[etapa][lado];
            if (Array.isArray(raw)) return raw;
            const nums = (String(raw || '').split(','))
                .map(s => parseInt(s.trim(), 10))
                .filter(n => Number.isFinite(n) && n > 0);
            return Array.from(new Set(nums));
        },

        async loadDetail(slug) {
            if (!slug) return;
            this.detailLoading = true;
            this.detailError = '';
            this.allPokemon = [];
            try {
                const resp = await fetch('/api/admin/gyms/' + slug, { headers: { 'Accept': 'application/json' } });
                if (!resp.ok) {
                    const err = await resp.json().catch(() => ({}));
                    throw new Error(err.message || 'Error al cargar el detalle');
                }
                const data = await resp.json();
                this.detail = data;

                // Cargar el formulario desde el detalle (tolerante a shape).
                this.form.medalla = data.medalla || '';
                this.form.tipo = data.tipo != null ? Number(data.tipo) : null;
                this.form.nivel_minimo = data.nivel_minimo != null ? Number(data.nivel_minimo) : null;
                // El backend serializa etapas como objeto {1:{vanguardia,retaguardia}, 2:..., ...}
                const etapas = data.etapas || {};
                Object.entries(etapas).forEach(([key, et]) => {
                    const e = Number(key);
                    if (e >= 1 && e <= 4) {
                        this.form.etapas[e].vanguardia = this.idsATexto(et.vanguardia || []);
                        this.form.etapas[e].retaguardia = this.idsATexto(et.retaguardia || []);
                    }
                });

                // Pokémon: si el backend ya entrega el array filtrado en `pokemon`, lo usamos.
                // `es_ultima_evolucion` (si viene) refuerza el filtro; si no, filtramos localmente.
                if (Array.isArray(data.pokemon)) {
                    this.allPokemon = data.pokemon.map(p => ({
                        species_id: p.species_id != null ? Number(p.species_id) : Number(p.id),
                        name: p.name,
                        types: Array.isArray(p.types) ? p.types.map(t => Number(typeof t === 'object' ? (t.id ?? t) : t)) : [],
                        es_ultima_evolucion: p.es_ultima_evolucion === true ? true : (typeof p.es_ultima_evolucion === 'undefined' ? undefined : false),
                        evolves_from_species_id: p.evolves_from_species_id != null ? Number(p.evolves_from_species_id) : null,
                    }));
                    this.pokemonLoading = false;
                } else {
                    // Fallback: Datagrid de pokémon filtrado por tipo + última evolución localmente.
                    await this.loadPokemonFallback(data.tipo);
                }
            } catch (e) {
                console.error('Error loading gym detail:', e);
                this.detailError = e.message || 'No se pudo cargar el detalle del gimnasio.';
                this.pokemonLoading = false;
            } finally {
                this.detailLoading = false;
            }
        },

        async loadPokemonFallback(tipo) {
            if (tipo == null) { this.pokemonLoading = false; return; }
            this.pokemonLoading = true;
            this.pokemonError = '';
            try {
                // A.3: filtro por tipo del gym aplicado por defecto vía el relation filter del Datagrid.
                const resp = await fetch('/datagrid/pokemon?filter[types]=' + encodeURIComponent(tipo) + '&per_page=200', { headers: { 'Accept': 'application/json' } });
                if (!resp.ok) throw new Error('No se pudo cargar el catálogo de pokémon');
                const data = await resp.json();
                const rows = Array.isArray(data) ? data : (data.data || []);
                this.allPokemon = rows.map(row => {
                    // El Datagrid expone `types` como lista de nombres en español; el relation
                    // filter ya acotó por el tipo pedido, así que marcamos cada fila como de este
                    // tipo y dejamos el filtro de última evolución al getter local.
                    const speciesId = row.species_id != null ? Number(row.species_id) : Number(row.id);
                    return {
                        species_id: speciesId,
                        name: row.name,
                        types: [],
                        es_ultima_evolucion: row.es_ultima_evolucion === true,
                        evolves_from_species_id: row.evolves_from_species_id != null ? Number(row.evolves_from_species_id) : null,
                    };
                });
            } catch (e) {
                console.error('Error loading pokemon fallback:', e);
                this.pokemonError = 'No se pudo cargar el listado de pokémon.';
            } finally {
                this.pokemonLoading = false;
            }
        },

        // Solo última evolución (A.4): se muestra un pokémon si NO es pre-evolución de nadie,
        // es decir, si su species_id no aparece como evolves_from_species_id de otra fila.
        // Los que no evolucionan también se muestran.
        get pokemonFiltered() {
            if (!this.detail || this.detail.tipo == null) return [];
            const tipo = Number(this.detail.tipo);

            // A.3: filtro por el tipo del gym (aplicado por defecto).
            const deEsteTipo = (this.allPokemon || []).filter((p) => {
                const tiposPok = p.types || [];
                if (tiposPok.length > 0) {
                    return tiposPok.some(t => Number(t) === tipo);
                }
                // Sin datos de tipos en el payload, asumimos que el backend ya filtró por tipo.
                return true;
            });

            // A.4: solo última evolución.
            const predecesores = new Set();
            (this.allPokemon || []).forEach((p) => {
                const origen = p.evolves_from_species_id;
                if (origen != null) predecesores.add(Number(origen));
            });

            return deEsteTipo.filter((p) => {
                // Si el backend expone `es_ultima_evolucion`, tiene prioridad.
                if (typeof p.es_ultima_evolucion === 'boolean') {
                    return p.es_ultima_evolucion;
                }
                // Fallback: excluimos los species_id que son pre-evolución de alguien.
                return !predecesores.has(Number(p.species_id));
            });
        },

        async guardarGym() {
            if (!this.currentSlug || this.saving) return;
            this.saving = true;
            this.saveMsg = '';
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const headers = {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                };

                // 1. Guardar datos básicos del gimnasio.
                const basicBody = {
                    medalla: this.form.medalla,
                    tipo: this.form.tipo,
                    nivel_minimo: this.form.nivel_minimo,
                };
                const resp = await fetch('/api/admin/gyms/' + this.currentSlug, {
                    method: 'PUT',
                    headers,
                    body: JSON.stringify(basicBody),
                });
                if (!resp.ok) {
                    const err = await resp.json().catch(() => ({}));
                    throw new Error(err.message || 'Error al guardar los datos del gimnasio');
                }

                // 2. Guardar etapas (una a una; si falla una, los demás que ya se guardaron quedan).
                const etapasConCambios = [1, 2, 3, 4].map(etapa => ({
                    etapa,
                    vanguardia: this.idsDe(etapa, 'vanguardia'),
                    retaguardia: this.idsDe(etapa, 'retaguardia'),
                }));
                const etapasFallidas = [];
                for (const etapaData of etapasConCambios) {
                    try {
                        const stageResp = await fetch(
                            '/api/admin/gyms/' + this.currentSlug + '/stages/' + etapaData.etapa,
                            {
                                method: 'PUT',
                                headers,
                                body: JSON.stringify({
                                    vanguardia: etapaData.vanguardia,
                                    retaguardia: etapaData.retaguardia,
                                }),
                            }
                        );
                        if (!stageResp.ok) {
                            const stageErr = await stageResp.json().catch(() => ({}));
                            etapasFallidas.push({ etapa: etapaData.etapa, error: stageErr.message || 'Error desconocido' });
                        }
                    } catch (e) {
                        etapasFallidas.push({ etapa: etapaData.etapa, error: e.message || 'Error de red' });
                    }
                }

                if (etapasFallidas.length > 0) {
                    const detalle = etapasFallidas.map(e => 'Etapa ' + e.etapa + ': ' + e.error).join('; ');
                    this.saveMsgType = 'error';
                    this.saveMsg = 'Datos guardados, pero fallaron etapas — ' + detalle;
                } else {
                    this.saveMsgType = 'success';
                    this.saveMsg = 'Cambios guardados correctamente.';
                }

                // Refrescar lista de gyms por si cambió medalla/tipo/nivel.
                this.loadGyms();
            } catch (e) {
                this.saveMsgType = 'error';
                this.saveMsg = e.message || 'Error al guardar los cambios.';
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
@endsection
