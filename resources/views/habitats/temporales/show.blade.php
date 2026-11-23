@extends('layouts.app')

@section('title', 'Hábitat temporal')

@section('content')
<div x-data="habitatTemporalShow(@js($slug))" x-init="init()">
    <div class="max-w-6xl mx-auto space-y-6">
        <a href="{{ route('habitats.index') }}" aria-label="Volver a hábitats"
           class="inline-flex items-center gap-2 rounded-control text-sm font-medium text-ink-muted transition-colors hover:text-primary-ink focus-ring">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Volver a hábitats
        </a>

        <div x-show="loading" x-cloak role="status" class="flex items-center gap-2 text-sm text-ink-muted">
            <svg class="h-5 w-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Cargando hábitat...
        </div>

        <div x-show="error" x-cloak role="alert"
             class="rounded-control border border-danger bg-danger-soft px-3 py-2 text-sm text-danger-ink">
            <span x-text="error"></span>
        </div>

        <template x-if="!loading && !error && faccion">
            <div class="space-y-6">
                {{-- Cabecera de facción --}}
                <section class="card-surface overflow-hidden" :style="'border-top: 4px solid ' + faccion.color">
                    <div class="flex flex-wrap items-center justify-between gap-4 p-5">
                        <div class="flex min-w-0 items-center gap-4">
                            <div class="h-24 w-36 shrink-0 overflow-hidden rounded-card bg-surface-sunken p-2">
                                <img :src="faccion.imagen_url" :alt="faccion.nombre"
                                     class="h-full w-full object-contain"
                                     onerror="this.onerror=null;this.src='/images/misc/raid.webp'">
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h1 class="text-xl font-bold text-ink" x-text="faccion.nombre"></h1>
                                    <span class="rounded-full px-2 py-0.5 text-2xs font-bold uppercase"
                                          :class="faccion.estado === 'disponible'
                                              ? 'bg-success-soft text-success-ink'
                                              : 'bg-warning-soft text-warning-ink'"
                                          x-text="faccion.estado"></span>
                                </div>
                                <p class="mt-1 text-sm text-ink-muted" x-text="faccion.descripcion"></p>
                            </div>
                        </div>

                        <div class="text-left sm:text-right">
                            <p class="text-xs uppercase tracking-wide text-ink-muted">Ahora</p>
                            <p class="text-2xl font-bold text-primary-ink"
                               x-text="'Combate ' + faccion.combate_actual + ' de ' + faccion.combates_por_oleada"></p>
                        </div>
                    </div>
                </section>

                {{-- Oleada actual: 4 entrenadores, estilo gimnasio --}}
                <section class="card-surface p-5">
                    <header class="mb-4">
                        <h2 class="text-sm font-semibold text-ink">Entrenadores</h2>
                    </header>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <template x-for="combate in combates" :key="combate.numero">
                            <div class="card-surface flex h-full flex-col gap-3 p-4"
                                 :class="combate.estado === 'disponible'
                                     ? 'border-primary ring-1 ring-primary'
                                     : ''">
                                <div class="flex min-w-0 items-start gap-3">
                                    <template x-if="combate.estado === 'superado'">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-success-soft">
                                            <svg class="h-4 w-4 text-success-ink" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                    </template>
                                    <template x-if="combate.estado === 'disponible'">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-soft">
                                            <span class="h-3 w-3 animate-pulse rounded-full bg-primary"></span>
                                        </span>
                                    </template>
                                    <template x-if="combate.estado === 'bloqueado'">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-sunken">
                                            <svg class="h-4 w-4 text-ink-muted" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                    </template>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-semibold text-ink" x-text="combate.nombre"></p>
                                            <span x-show="combate.es_jefe"
                                                  class="rounded-full bg-danger-soft px-2 py-0.5 text-2xs font-bold uppercase text-danger-ink">
                                                Jefe
                                            </span>
                                        </div>
                                        <p class="mt-0.5 min-h-[2.5rem] text-xs text-ink-muted" x-text="combate.descripcion"></p>
                                    </div>
                                </div>

                                <div class="mt-auto flex items-center justify-between gap-2">
                                    <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-card bg-surface-sunken"
                                         :class="combate.estado === 'disponible' ? 'bg-primary-soft' : ''"
                                         :title="combate.es_jefe ? 'Jefe: ' + combate.nombre : 'Rival: ' + combate.nombre">
                                        <img :src="combate.sprite_url" :alt="combate.entrenador"
                                             width="80" height="80" loading="lazy" decoding="async"
                                             style="image-rendering: pixelated"
                                             class="h-20 w-20 object-contain"
                                             onerror="this.onerror=null;this.src='/images/misc/trainer.webp'">
                                    </div>

                                    <template x-if="combate.estado === 'disponible'">
                                        <button type="button" @click="openFormacionCombate()"
                                                :disabled="combating"
                                                class="shrink-0 rounded-control bg-danger px-4 py-2 text-sm font-bold uppercase tracking-wide text-on-primary transition-colors hover:bg-danger/90 focus-ring disabled:cursor-not-allowed disabled:opacity-50">
                                            ¡Combatir!
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>
            </div>
        </template>

        @include('partials.formacion-combate')
    </div>
</div>

@push('scripts')
@include('partials.comparacion-pc-script')
@include('partials.formacion-combate-script')
<script>
function habitatTemporalShow(slug) {
    return {
        ...comparadorPcState(),
        ...formacionCombateState(),
        slug: slug,
        faccion: null,
        oleada: null,
        combate: null,
        combates: [],
        teams: @json($teams),
        loading: true,
        error: '',

        get selectedTeamMembers() {
            if (!this.selectedTeamId || !this.teams) {
                return [];
            }

            const team = this.teams.find(t => t.id === this.selectedTeamId);
            if (!team || !team.members) {
                return [];
            }

            return team.members
                .slice()
                .sort((a, b) => a.slot - b.slot)
                .filter(m => m.reclutado && m.reclutado.pokemon);
        },

        async init() {
            try {
                const response = await fetch('/api/habitats-temporales/' + this.slug, {
                    headers: { 'Accept': 'application/json' },
                });

                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || 'No se pudo cargar el hábitat.');
                }

                const data = await response.json();
                this.faccion = data.faccion || null;
                this.oleada = data.oleada || null;
                this.combate = data.combate || null;
                this.combates = data.combates || [];
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        openFormacionCombate() {
            if (!this.combate || this.combate.estado !== 'disponible') {
                return;
            }

            this.formacionObjetivo = this.combate.nombre || '';
            this.formacionPcUrl = '/api/habitats-temporales/' + encodeURIComponent(this.slug) + '/pc';
            this.formacionCombatUrl = '/api/habitats-temporales/' + this.slug
                + '/oleadas/' + this.oleada.numero
                + '/combates/' + this.combate.numero
                + '/combatir';
            this.formacionPuedeCombatir = true;
            this.openFormacionPopup();
        },
    };
}
</script>
@endpush
@endsection
