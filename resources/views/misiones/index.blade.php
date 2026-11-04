@extends('layouts.app')

@section('title', 'Misiones')

@section('content')
<div x-data="misionesPage()">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-ink">Misiones</h1>
        <p class="text-sm text-ink-muted mt-1">Acepta misiones y cumple los objetivos para ganar recompensas</p>
    </div>

    <!-- Avance de carga global -->
    <x-ui.alert variant="info" x-show="misionesLoading || activaLoading" x-cloak class="mb-4">
        <span class="inline-flex items-center gap-2">
            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Cargando misiones...
        </span>
    </x-ui.alert>

    <!-- Aviso de error (tolerante: el backend puede estar en preparación) -->
    <x-ui.alert variant="warning" x-show="misionesError" x-cloak class="mb-4">
        <span x-text="misionesError"></span>
    </x-ui.alert>

    <!-- Aviso puntual (resultado de una acción) -->
    <x-ui.alert variant="info" x-show="aviso" x-cloak x-transition class="mb-4">
        <span x-text="aviso"></span>
    </x-ui.alert>

    <!-- Misión activa -->
    <template x-if="activa">
        <section class="mb-8" aria-labelledby="activa-title">
            <div class="flex items-center justify-between bg-surface-sunken border border-line rounded-control px-4 py-2 mb-4">
                <h2 id="activa-title" class="text-sm font-bold uppercase tracking-wide text-ink">Misión activa</h2>
                <span class="px-2 py-0.5 bg-success-soft text-success-ink border border-success text-2xs font-bold rounded-full uppercase" x-text="estadoLabel(activa.estado)"></span>
            </div>

            <div class="bg-surface rounded-card shadow-card border border-line overflow-hidden">
                <div class="px-4 py-3 border-b border-line bg-surface-sunken">
                    <h3 class="text-sm font-semibold text-ink" x-text="activa.titulo"></h3>
                    <p class="text-xs text-ink-muted mt-0.5" x-text="activa.objetivo_texto || progresoTexto(activa)"></p>
                </div>
                <div class="p-4 space-y-4">
                    <!-- Barra de progreso -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs text-ink-muted" id="activa-progreso-label">Progreso</span>
                            <span class="text-xs font-medium text-ink" x-text="progresoTexto(activa) || progresoPorcentaje(activa) + '%'"></span>
                        </div>
                        <div
                            class="h-2 bg-surface-sunken rounded-full overflow-hidden"
                            role="progressbar"
                            aria-labelledby="activa-progreso-label"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-valuenow="progresoPorcentaje(activa)"
                        >
                            <div class="h-2 bg-success rounded-full transition-all" :style="'width: ' + progresoPorcentaje(activa) + '%'"></div>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            type="button"
                            @click="cancelarMision()"
                            :disabled="accionando"
                            class="px-4 py-2 bg-surface-sunken text-ink rounded-control text-sm font-medium hover:bg-line transition-colors disabled:opacity-50 disabled:cursor-not-allowed focus-ring"
                        >
                            Cancelar misión
                        </button>
                        <button
                            type="button"
                            x-show="activa.estado === 'listo_para_pelea'"
                            @click="pelearMision()"
                            class="px-4 py-2 bg-danger text-on-primary rounded-control text-sm font-bold hover:bg-danger/90 transition-colors focus-ring"
                        >
                            ¡Pelea!
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </template>

    <!-- Listado de misiones disponibles (no se muestra la ya aceptada) -->
    <section aria-labelledby="misiones-disponibles-title">
        <div class="flex items-center justify-between bg-surface-sunken border border-line rounded-control px-4 py-2 mb-4">
            <h2 id="misiones-disponibles-title" class="text-sm font-bold uppercase tracking-wide text-ink">Disponibles</h2>
            <span x-show="misionesDisponibles().length > 0" class="px-2 py-0.5 bg-primary-soft text-primary-ink text-xs font-bold rounded-full" x-text="misionesDisponibles().length"></span>
        </div>

        <template x-if="!misionesLoading && !misionesError && misionesDisponibles().length === 0">
            <div class="bg-surface rounded-card shadow-card border border-line p-8 text-center" role="status">
                <svg class="w-12 h-12 mx-auto text-ink-subtle mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2m-6 0a2 2 0 002 2h2a2 2 0 002-2m-6 0a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-sm text-ink-muted mb-1">No hay misiones disponibles ahora</p>
                <p class="text-xs text-ink-subtle">Vuelve pronto o revisa tu misión activa</p>
            </div>
        </template>

        <template x-for="mision in misionesDisponibles()" :key="mision.id">
            <div class="bg-surface rounded-card shadow-card border border-line overflow-hidden mb-4">
                <div class="px-4 py-3 border-b border-line bg-surface-sunken flex items-center justify-between flex-wrap gap-2">
                    <h3 class="text-sm font-semibold text-ink" x-text="mision.titulo"></h3>
                    <template x-if="mision.nivel_requerido">
                        <span class="px-2 py-0.5 bg-warning-soft text-warning-ink border border-warning text-2xs font-bold rounded-full uppercase">Nv <span x-text="mision.nivel_requerido"></span></span>
                    </template>
                </div>
                <div class="p-4 space-y-3">
                    <p class="text-sm text-ink-muted" x-text="objetivoTexto(mision)"></p>

                    <template x-if="recompensasLista(mision).length > 0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs text-ink-muted">Recompensa:</span>
                            <template x-for="(recompensa, i) in recompensasLista(mision)" :key="i">
                                <span class="px-2 py-0.5 bg-info-soft text-info-ink border border-info text-2xs font-bold rounded-full" x-text="recompensa"></span>
                            </template>
                        </div>
                    </template>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="abrirAceptar(mision)"
                            :disabled="accionando"
                            class="px-4 py-2 bg-primary text-on-primary rounded-control text-sm font-medium hover:bg-primary-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed focus-ring"
                        >
                            Aceptar
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </section>

    <!-- Modal: aceptar misión (elegir reclutado y hábitat) -->
    <template x-if="acceptMissionId !== null">
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="aceptar-mision-title"
            @keydown.escape.window="cerrarAceptar()"
        >
            <div class="absolute inset-0 scrim" @click="cerrarAceptar()"></div>
            <div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-md p-6">
                <h3 id="aceptar-mision-title" class="text-lg font-bold text-ink mb-1">Aceptar misión</h3>
                <p class="text-sm text-ink-muted mb-4">
                    Elige un reclutado y la zona donde cumplirás el objetivo de
                    <strong class="text-ink" x-text="misionTitulo(acceptMissionId)"></strong>
                </p>

                <!-- Select reclutado -->
                <div class="mb-4">
                    <label class="block text-xs font-medium text-ink-muted mb-1" for="mision-reclutado">Reclutado</label>
                    <template x-if="reclutados.length === 0 && !reclutadosError">
                        <p class="text-sm text-ink-subtle">Cargando reclutados...</p>
                    </template>
                    <template x-if="reclutadosError">
                        <p class="text-sm text-danger-ink" role="alert"><span x-text="reclutadosError"></span></p>
                    </template>
                    <template x-if="reclutados.length > 0">
                        <select id="mision-reclutado" x-model="acceptReclutadoId" class="w-full px-3 py-2 bg-surface-sunken border border-line rounded-control text-sm text-ink focus-ring">
                            <option value="">Selecciona un reclutado</option>
                            <template x-for="r in reclutados" :key="r.id">
                                <option :value="r.id" x-text="nombreDe(r)"></option>
                            </template>
                        </select>
                    </template>
                </div>

                <!-- Select hábitat (el contrato de aceptar requiere habitat_id) -->
                <div class="mb-4">
                    <label class="block text-xs font-medium text-ink-muted mb-1" for="mision-habitat">Zona</label>
                    <template x-if="habitats.length === 0 && !habitatsError">
                        <p class="text-sm text-ink-subtle">Cargando zonas...</p>
                    </template>
                    <template x-if="habitatsError">
                        <p class="text-sm text-danger-ink" role="alert"><span x-text="habitatsError"></span></p>
                    </template>
                    <template x-if="habitats.length > 0">
                        <select id="mision-habitat" x-model="acceptHabitatId" class="w-full px-3 py-2 bg-surface-sunken border border-line rounded-control text-sm text-ink focus-ring">
                            <option value="">Selecciona una zona</option>
                            <template x-for="h in habitats" :key="h.id">
                                <option :value="h.id" x-text="h.name"></option>
                            </template>
                        </select>
                    </template>
                </div>

                <!-- Acciones -->
                <div class="flex gap-3">
                    <button
                        type="button"
                        @click="cerrarAceptar()"
                        :disabled="accionando"
                        class="flex-1 px-4 py-2.5 bg-surface-sunken text-ink rounded-control text-sm font-medium hover:bg-line transition-colors disabled:opacity-50 focus-ring"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="aceptarMision()"
                        :disabled="accionando || !acceptReclutadoId || !acceptHabitatId"
                        class="flex-1 px-4 py-2.5 bg-success text-on-primary rounded-control text-sm font-bold hover:bg-success/90 transition-colors disabled:bg-surface-sunken disabled:text-ink-subtle disabled:cursor-not-allowed focus-ring"
                    >
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection

@push('scripts')
<script>
function misionesPage() {
    return {
        misiones: [],
        misionesLoading: true,
        misionesError: '',
        activa: null,
        activaLoading: true,
        reclutados: [],
        reclutadosError: '',
        habitats: [],
        habitatsError: '',
        acceptMissionId: null,
        acceptReclutadoId: '',
        acceptHabitatId: '',
        accionando: false,
        aviso: '',

        init() {
            this.cargarTodo();
        },

        async cargarTodo() {
            await Promise.all([this.loadMisiones(), this.loadActiva(), this.loadReclutados(), this.loadHabitats()]);
        },

        async loadMisiones() {
            this.misionesLoading = true;
            this.misionesError = '';
            try {
                const response = await fetch('/misiones', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('No se pudieron cargar las misiones.');
                this.misiones = await response.json();
            } catch (e) {
                console.error('Error loading missions:', e);
                this.misionesError = 'Función en preparación.';
                this.misiones = [];
            } finally {
                this.misionesLoading = false;
            }
        },

        async loadActiva() {
            this.activaLoading = true;
            try {
                const response = await fetch('/misiones/activa', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('Sin misión activa.');
                const data = await response.json();
                this.activa = data && data.id ? data : null;
            } catch (e) {
                console.error('Error loading active mission:', e);
                this.activa = null;
            } finally {
                this.activaLoading = false;
            }
        },

        async loadReclutados() {
            this.reclutados = [];
            this.reclutadosError = '';
            try {
                const response = await fetch('/api/reclutados', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('No se pudieron cargar los reclutados.');
                this.reclutados = await response.json();
            } catch (e) {
                console.error('Error loading reclutados:', e);
                this.reclutadosError = 'Función en preparación.';
            }
        },

        async loadHabitats() {
            this.habitats = [];
            this.habitatsError = '';
            try {
                const response = await fetch('/datagrid/habitat?per_page=200', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('No se pudieron cargar las zonas.');
                const data = await response.json();
                this.habitats = data.data || [];
            } catch (e) {
                console.error('Error loading habitats:', e);
                this.habitatsError = 'Función en preparación.';
            }
        },

        misionesDisponibles() {
            if (!this.activa) return this.misiones;
            return this.misiones.filter(m => m.id !== this.activa.id);
        },

        objetivoTexto(mision) {
            if (mision.objetivo_texto) return mision.objetivo_texto;
            const n = mision.objetivo_cantidad ?? 0;
            if (mision.objetivo_tipo === 'enfrentar' || mision.objetivo_tipo === 'pelear') {
                return `Enfrenta ${n} Pokémon`;
            }
            if (mision.objetivo_tipo === 'recuperar' || mision.objetivo_tipo === 'caramelos') {
                return `Recupera ${n} caramelos`;
            }
            return mision.titulo || 'Misión';
        },

        recompensasLista(mision) {
            const r = mision.recompensa_cerrada;
            if (Array.isArray(r)) return r;
            if (typeof r === 'string' && r !== '') return [r];
            return [];
        },

        misionTitulo(id) {
            const mision = this.misiones.find(m => m.id === id);
            return mision ? mision.titulo : '';
        },

        nombreDe(r) {
            return r.nombre || r.pokemon?.name || 'Reclutado #' + r.id;
        },

        progresoPorcentaje(a) {
            const p = a.progreso;
            if (p == null) return 0;
            if (typeof p === 'number') return Math.min(100, Math.max(0, p));
            if (typeof p === 'object') {
                const total = Number(p.total || 0);
                if (total > 0) return Math.min(100, Math.round((Number(p.actual || 0) / total) * 100));
            }
            return 0;
        },

        progresoTexto(a) {
            const p = a.progreso;
            if (typeof p === 'object' && Number(p.total || 0) > 0) {
                return `Enfrentados ${p.actual}/${p.total}`;
            }
            return '';
        },

        estadoLabel(estado) {
            const labels = {
                'en_progreso': 'En progreso',
                'listo_para_pelea': '¡Listo para pelear!',
                'completada': 'Completada',
            };
            return labels[estado] || (estado || '').replace(/_/g, ' ');
        },

        abrirAceptar(mision) {
            this.acceptMissionId = mision.id;
            this.acceptReclutadoId = '';
            this.acceptHabitatId = '';
        },

        cerrarAceptar() {
            if (this.accionando) return;
            this.acceptMissionId = null;
            this.acceptReclutadoId = '';
            this.acceptHabitatId = '';
        },

        async aceptarMision() {
            if (!this.acceptMissionId || !this.acceptReclutadoId || !this.acceptHabitatId || this.accionando) return;
            this.accionando = true;
            this.aviso = '';
            try {
                const response = await fetch('/misiones/' + this.acceptMissionId + '/aceptar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        reclutado_id: this.acceptReclutadoId,
                        habitat_id: this.acceptHabitatId,
                    }),
                });
                if (response.ok || response.status === 201) {
                    this.cerrarAceptar();
                    await this.loadActiva();
                    await this.loadMisiones();
                    this.aviso = 'Misión aceptada. ¡A por ella!';
                } else {
                    const body = await response.json().catch(() => ({}));
                    alert(body.message || 'No se pudo aceptar la misión.');
                }
            } catch (e) {
                console.error('Error accepting mission:', e);
                alert('Error de conexión al aceptar la misión.');
            } finally {
                this.accionando = false;
            }
        },

        async cancelarMision() {
            if (!this.activa || this.accionando) return;
            if (!window.confirm('¿Cancelar la misión activa? Perderás el progreso.')) return;
            this.accionando = true;
            try {
                const response = await fetch('/misiones/' + this.activa.id + '/cancelar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });
                if (response.ok) {
                    this.activa = null;
                    await this.loadMisiones();
                    this.aviso = 'Misión cancelada.';
                } else {
                    const body = await response.json().catch(() => ({}));
                    alert(body.message || 'No se pudo cancelar la misión.');
                }
            } catch (e) {
                console.error('Error cancelling mission:', e);
                alert('Error de conexión al cancelar la misión.');
            } finally {
                this.accionando = false;
            }
        },

        pelearMision() {
            // Ruta de batalla de misión que exponga el backend (aún pendiente de contrato).
            const rutaPelea = @json($misionPeleaRuta ?? null);
            if (rutaPelea && this.activa) {
                window.location.href = rutaPelea.replace(':id', String(this.activa.id));
                return;
            }
            alert('La pelea de misiones está en preparación. Vuelve pronto.');
        },
    };
}
</script>
@endpush