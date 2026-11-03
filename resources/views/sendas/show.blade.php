@extends('layouts.app')

@section('title', 'Senda de ' . $arbol['tipo']['nombre'])

@section('content')
<div x-data="sendasApp(@js($arbol))" x-cloak>
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <a href="/sendas" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-control border border-line text-ink-muted transition-colors hover:bg-surface-sunken hover:text-ink focus-ring" aria-label="Volver a Sendas">←</a>
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-card" :style="'background-color: color-mix(in oklab, var(--type-' + arbol.tipo.slug + ') 14%, var(--color-surface))'">
                <img :src="'/images/type/' + arbol.tipo.slug + '.webp'" :alt="arbol.tipo.nombre" loading="lazy" decoding="async" class="h-9 w-9 object-contain">
            </span>
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-bold text-ink">Senda de <span x-text="arbol.tipo.nombre"></span></h1>
                <p class="text-sm text-ink-muted">
                    Nivel <span x-text="arbol.nivel"></span> / <span x-text="arbol.nivel_maximo"></span>
                    · <span x-text="arbol.desbloqueados + ' / ' + arbol.total_nodos + ' movimientos desbloqueados'"></span>
                </p>
            </div>
        </div>
        <div class="flex flex-col items-end gap-2">
            <span class="inline-flex items-center gap-1 rounded-full border border-warning bg-warning-soft px-3 py-1.5 text-sm font-bold text-warning-ink" title="Caramelos de este tipo">
                <img :src="'/images/candy_type/' + arbol.tipo.slug + '.webp'" :alt="'Caramelos de ' + arbol.tipo.nombre" loading="lazy" decoding="async" class="h-5 w-5 object-contain" onerror="this.style.display='none'">
                <span x-text="arbol.caramelos"></span>
            </span>
            <div class="flex items-center gap-1.5" role="group" aria-label="Consumir caramelos para ganar experiencia">
                <button
                    type="button"
                    @click="consumir(1)"
                    :disabled="saving || savingExp || arbol.caramelos <= 0"
                    :aria-label="'Consumir 1 caramelo de ' + arbol.tipo.nombre + ' para ganar experiencia'"
                    class="rounded-control border border-warning px-2.5 py-1 text-xs font-bold text-warning-ink transition-colors hover:bg-warning-soft disabled:cursor-not-allowed disabled:border-line disabled:text-ink-subtle focus-ring"
                >x1</button>
                <button
                    type="button"
                    @click="consumir(10)"
                    :disabled="saving || savingExp || arbol.caramelos <= 0"
                    :aria-label="'Consumir 10 caramelos de ' + arbol.tipo.nombre + ' para ganar experiencia'"
                    class="rounded-control border border-warning px-2.5 py-1 text-xs font-bold text-warning-ink transition-colors hover:bg-warning-soft disabled:cursor-not-allowed disabled:border-line disabled:text-ink-subtle focus-ring"
                >x10</button>
                <button
                    type="button"
                    @click="consumir(100)"
                    :disabled="saving || savingExp || arbol.caramelos <= 0"
                    :aria-label="'Consumir 100 caramelos de ' + arbol.tipo.nombre + ' para ganar experiencia'"
                    class="rounded-control border border-warning px-2.5 py-1 text-xs font-bold text-warning-ink transition-colors hover:bg-warning-soft disabled:cursor-not-allowed disabled:border-line disabled:text-ink-subtle focus-ring"
                >x100</button>
            </div>
            <p class="text-2xs font-semibold text-ink-subtle">+<span x-text="arbol.exp_por_caramelo"></span> exp por caramelo</p>
        </div>
    </div>

    <template x-if="feedback.mensaje && feedback.tipo === 'error'">
        <x-ui.alert variant="danger" class="mb-4"><span x-text="feedback.mensaje"></span></x-ui.alert>
    </template>

    <template x-if="feedback.mensaje && feedback.tipo !== 'error'">
        <x-ui.alert variant="success" class="mb-4"><span x-text="feedback.mensaje"></span></x-ui.alert>
    </template>

    <!-- Filtro de movimientos -->
    <div class="mb-4 rounded-panel border border-line bg-surface p-3 shadow-card">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative w-full sm:max-w-sm">
                <label for="filtro-movimientos" class="sr-only">Filtrar movimientos por nombre</label>
                <input
                    id="filtro-movimientos"
                    type="search"
                    x-model="filtro"
                    @search="alBuscar($event)"
                    placeholder="Buscar movimiento por nombre…"
                    autocomplete="off"
                    aria-describedby="filtro-movimientos-total"
                    class="w-full rounded-control border border-line bg-surface py-1.5 pl-9 pr-3 text-xs text-ink placeholder-ink-subtle transition-colors focus-ring"
                >
                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <button
                type="button"
                x-show="hayFiltro()"
                @click="limpiarFiltro()"
                class="rounded-control text-xs font-medium text-primary-ink hover:underline focus-ring"
            >Limpiar</button>
            <p id="filtro-movimientos-total" class="ml-auto text-xs text-ink-muted">
                <span x-text="totalVisible()"></span> movimientos visibles
            </p>
        </div>
    </div>

    <!-- Progreso -->
    <div class="mb-4 rounded-panel border border-line bg-surface p-4 shadow-card">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-sm font-bold text-ink">Progreso de la Senda</p>
                <p class="mt-0.5 text-xs text-ink-muted">
                    <span x-text="arbol.exp.toLocaleString('es-ES')"></span> / <span x-text="arbol.exp_siguiente.toLocaleString('es-ES')"></span> exp
                </p>
            </div>
            <p class="text-xs font-bold" :style="'color: var(--type-' + arbol.tipo.slug + ')'" x-text="arbol.porcentaje + '%'"></p>
        </div>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-surface-sunken" role="progressbar" :aria-valuenow="arbol.porcentaje" aria-valuemin="0" aria-valuemax="100" aria-label="Progreso de la Senda">
            <div class="h-full rounded-full transition-all duration-500" :style="'width: ' + arbol.porcentaje + '%; background-color: var(--type-' + arbol.tipo.slug + ')'"></div>
        </div>
        <template x-if="arbol.nivel >= arbol.nivel_maximo">
            <p class="mt-2 text-2xs font-semibold text-warning-ink">
                Tramo final: hasta <span x-text="arbol.exp_maxima.toLocaleString('es-ES')"></span> exp. La experiencia sigue acumulándose.
            </p>
        </template>
    </div>

    <!-- Beneficios pasivos -->
    <section class="mb-4 rounded-panel border border-line bg-surface p-4 shadow-card" aria-labelledby="beneficios-senda-titulo">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 id="beneficios-senda-titulo" class="text-sm font-bold text-ink">Beneficios pasivos</h2>
                <p class="mt-0.5 text-xs text-ink-muted">Se aplican a tus combatientes y capturas; suben con el nivel de la Senda.</p>
            </div>
            <p class="max-w-md text-3xs font-semibold text-ink-muted">Niveles 1-3: desbloquean ataques. Niveles 4-5: maestrías pasivas.</p>
        </div>

        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-card border border-line bg-surface-sunken p-3">
                <p class="text-2xs font-bold uppercase tracking-wide text-ink-muted">Nivel actual</p>
                <p class="text-sm font-bold text-ink">Nivel <span x-text="arbol.nivel"></span></p>
                <dl class="mt-2 space-y-1.5">
                    <template x-for="fila in beneficiosFilas(arbol.beneficios)" :key="'beneficio-actual-' + fila.clave">
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-xs text-ink-muted">
                                <span x-text="fila.etiqueta"></span>
                                <span x-show="fila.reduccion" class="text-2xs">(reducción)</span>
                            </dt>
                            <dd class="text-xs font-bold tabular-nums text-ink" x-text="formatearBeneficio(fila.valor, fila.reduccion)"></dd>
                        </div>
                    </template>
                </dl>
            </div>

            <div class="rounded-card border border-line bg-surface-sunken p-3">
                <p class="text-2xs font-bold uppercase tracking-wide text-ink-muted" x-text="arbol.nivel < arbol.nivel_maximo ? 'Siguiente nivel' : 'Nivel máximo'"></p>
                <p class="text-sm font-bold text-ink">Nivel <span x-text="arbol.siguiente_nivel"></span></p>
                <dl class="mt-2 space-y-1.5">
                    <template x-for="fila in beneficiosFilas(arbol.beneficios_siguiente)" :key="'beneficio-siguiente-' + fila.clave">
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-xs text-ink-muted">
                                <span x-text="fila.etiqueta"></span>
                                <span x-show="fila.reduccion" class="text-2xs">(reducción)</span>
                            </dt>
                            <dd class="text-xs font-bold tabular-nums text-ink" x-text="formatearBeneficio(fila.valor, fila.reduccion)"></dd>
                        </div>
                    </template>
                </dl>
                <p class="mt-2 text-2xs text-ink-subtle" x-show="arbol.nivel >= arbol.nivel_maximo">Nivel máximo: no hay mejoras adicionales.</p>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <template x-for="categoria in ['fisico', 'especial', 'estado']" :key="'senda-rama-' + categoria">
            <section class="rounded-panel border border-line bg-surface p-3 shadow-card">
                <div class="mb-3 flex items-center justify-between gap-2 border-b border-line pb-2">
                    <div class="flex items-center gap-2">
                        <img :src="categoriaIcono(categoria)" :alt="etiquetaCategoria(categoria)" loading="lazy" decoding="async" class="h-6 w-6 object-contain">
                        <h2 class="text-xs font-bold uppercase tracking-wide text-ink" x-text="etiquetaCategoria(categoria)"></h2>
                    </div>
                    <span class="text-2xs font-semibold text-ink-subtle" x-text="contadorCategoria(categoria) + ' movimientos'"></span>
                </div>

                <div class="space-y-2">
                    <template x-for="grupo in gruposDe(categoria)" :key="categoria + '-grupo-' + grupo.nivel">
                        <div>
                            <button
                                type="button"
                                @click="toggleGrupo(categoria, grupo.nivel)"
                                :aria-expanded="estaAbierto(categoria, grupo.nivel)"
                                :aria-controls="'grupo-' + categoria + '-' + grupo.nivel"
                                class="flex w-full items-center gap-2 rounded-control bg-surface-sunken px-2 py-1.5 text-left transition-colors hover:bg-line/70 focus-ring"
                            >
                                <svg class="h-3 w-3 shrink-0 text-ink-subtle transition-transform" :class="estaAbierto(categoria, grupo.nivel) ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="text-2xs font-bold uppercase tracking-wide text-ink-muted" x-text="'Nv Senda ' + grupo.nivel"></span>
                                <template x-if="grupoTieneMaestrias(grupo)">
                                    <span class="rounded-full border border-info bg-info-soft px-1.5 py-0.5 text-2xs font-bold uppercase tracking-wide text-info-ink">Maestrías</span>
                                </template>
                                <span class="ml-auto text-2xs font-semibold text-ink-subtle" x-text="contadorGrupo(grupo) + ' movimientos'"></span>
                            </button>

                            <div :id="'grupo-' + categoria + '-' + grupo.nivel" x-show="estaAbierto(categoria, grupo.nivel)" x-cloak class="mt-2 space-y-2">
                                <template x-for="nodo in grupo.nodos" :key="'senda-nodo-' + nodo.slug">
                                    <article
                                        class="rounded-card border p-2.5 transition-colors"
                                        :class="nodo.desbloqueado
                                            ? 'border-success bg-success-soft'
                                            : (nodo.puede_desbloquear
                                                ? 'border-warning bg-warning-soft'
                                                : 'border-line bg-surface-sunken opacity-70')"
                                    >
                                        <div class="flex items-start justify-between gap-1.5">
                                            <p class="min-w-0 text-sm font-semibold leading-tight text-ink" x-text="nodo.name"></p>
                                            <img :src="'/images/type/' + nodo.tipo_slug + '.webp'" :alt="nodo.tipo_nombre" loading="lazy" decoding="async" class="h-6 w-6 shrink-0 object-contain">
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center gap-x-1.5 text-3xs text-ink-subtle">
                                            <span x-text="etiquetaCategoria(nodo.category)"></span>
                                            <span x-show="nodo.base_power > 0" x-text="'· ' + nodo.base_power + ' pot.'"></span>
                                            <template x-if="nodo.es_maestria">
                                                <span class="rounded-full border border-info bg-info-soft px-1.5 py-0.5 text-2xs font-bold text-info-ink" x-text="'Maestría · tramo ' + nodo.tramo"></span>
                                            </template>
                                            <span x-text="'· Nv Senda ' + nodo.nivel_requerido"></span>
                                            <span x-text="'· ' + nodo.coste + ' caramelos'"></span>
                                        </div>
                                        <p class="mt-1 text-3xs leading-snug text-ink-muted" x-text="nodo.description || 'Sin descripción.'"></p>

                                        <template x-if="nodo.desbloqueado">
                                            <span class="mt-2 block w-full rounded-control border border-success bg-success-soft px-2 py-1 text-center text-3xs font-bold text-success-ink">Desbloqueado</span>
                                        </template>
                                        <template x-if="!nodo.desbloqueado">
                                            <button
                                                type="button"
                                                @click="desbloquear(nodo)"
                                                :disabled="!nodo.puede_desbloquear || saving || savingExp"
                                                class="mt-2 w-full rounded-control border border-warning px-2 py-1 text-3xs font-bold text-warning-ink transition-colors hover:bg-warning-soft disabled:cursor-not-allowed disabled:border-line disabled:text-ink-subtle focus-ring"
                                                x-text="textoBoton(nodo)"
                                            ></button>
                                        </template>
                                    </article>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <p x-show="contadorCategoria(categoria) === 0 && !hayFiltro()" class="py-4 text-center text-xs text-ink-subtle">Sin movimientos en esta categoría.</p>
            </section>
        </template>
    </div>

    <!-- Estado vacío global -->
    <div x-show="totalVisible() === 0 && hayFiltro()" class="mt-4 rounded-panel border border-dashed border-line p-6 text-center">
        <p class="text-sm text-ink-muted">No hay movimientos que coincidan con el filtro.</p>
    </div>
</div>

@push('scripts')
<script>
function sendasApp(arbolInicial) {
    return {
        arbol: arbolInicial,
        filtro: '',
        gruposCerrados: {},
        saving: false,
        savingExp: false,
        feedback: { tipo: '', mensaje: '' },

        etiquetaCategoria(categoria) {
            return { fisico: 'Físico', especial: 'Especial', estado: 'Estado' }[categoria] || categoria;
        },

        categoriaIcono(categoria) {
            return '/images/moves/' + categoria + '.png';
        },

        beneficiosFilas(beneficios) {
            if (!beneficios) {
                return [];
            }
            return [
                { clave: 'captura', etiqueta: 'Captura', valor: beneficios.captura, reduccion: false },
                { clave: 'dano_infligido', etiqueta: 'Daño infligido', valor: beneficios.dano_infligido, reduccion: false },
                { clave: 'dano_recibido', etiqueta: 'Daño recibido', valor: beneficios.dano_recibido, reduccion: true },
                { clave: 'caramelo_tipo', etiqueta: 'Caramelos de tipo', valor: beneficios.caramelo_tipo, reduccion: false },
            ];
        },

        formatearBeneficio(valor, reduccion = false) {
            const numero = Math.round((Number(valor) || 0) * 100);
            if (numero === 0) {
                return '0 %';
            }
            return (reduccion ? '−' : '+') + numero + ' %';
        },

        grupoTieneMaestrias(grupo) {
            return (grupo?.nodos || []).some((nodo) => nodo.es_maestria === true);
        },

        normalizarTexto(valor) {
            return String(valor ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        },

        filtroNormalizado() {
            return this.normalizarTexto(this.filtro).trim();
        },

        hayFiltro() {
            return this.filtroNormalizado().length > 0;
        },

        limpiarFiltro() {
            this.filtro = '';
        },

        alBuscar(event) {
            if (event.target.value.trim() === '') {
                this.limpiarFiltro();
            }
        },

        ramasFiltradas(categoria) {
            const nodos = this.arbol.ramas[categoria] || [];
            if (!this.hayFiltro()) {
                return nodos;
            }
            const busqueda = this.filtroNormalizado();
            return nodos.filter((nodo) => this.normalizarTexto(nodo.name).includes(busqueda));
        },

        gruposDe(categoria) {
            const grupos = [];
            const indice = new Map();
            for (const nodo of this.ramasFiltradas(categoria)) {
                if (!indice.has(nodo.nivel_requerido)) {
                    indice.set(nodo.nivel_requerido, grupos.length);
                    grupos.push({ nivel: nodo.nivel_requerido, nodos: [] });
                }
                grupos[indice.get(nodo.nivel_requerido)].nodos.push(nodo);
            }
            return grupos;
        },

        contadorCategoria(categoria) {
            return this.ramasFiltradas(categoria).length;
        },

        contadorGrupo(grupo) {
            return grupo.nodos.length;
        },

        totalVisible() {
            return ['fisico', 'especial', 'estado'].reduce((total, categoria) => total + this.contadorCategoria(categoria), 0);
        },

        estaAbierto(categoria, nivel) {
            if (this.hayFiltro()) {
                return true;
            }
            return !this.gruposCerrados[categoria + ':' + nivel];
        },

        toggleGrupo(categoria, nivel) {
            const clave = categoria + ':' + nivel;
            this.gruposCerrados[clave] = !this.gruposCerrados[clave];
        },

        textoBoton(nodo) {
            if (!nodo.disponible) {
                return 'Nivel de Senda ' + nodo.nivel_requerido;
            }
            if (this.arbol.caramelos < nodo.coste) {
                return 'Faltan ' + (nodo.coste - this.arbol.caramelos) + ' caramelos';
            }
            return (nodo.es_maestria ? 'Desbloquear maestría' : 'Desbloquear') + ' · ' + nodo.coste + ' caramelos';
        },

        async consumir(cantidad) {
            if (this.saving || this.savingExp) {
                return;
            }
            this.savingExp = true;
            this.feedback = { tipo: '', mensaje: '' };
            try {
                const response = await fetch('/sendas/experiencia', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ tipo: this.arbol.tipo.slug, cantidad }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.feedback = { tipo: 'error', mensaje: data.error || data.message || 'No se pudieron consumir los caramelos.' };
                    return;
                }
                this.arbol = data.arbol || this.arbol;
                this.feedback = {
                    tipo: 'ok',
                    mensaje: 'Has usado ' + data.cantidad_consumida + ' caramelo(s): +' + data.exp_ganada + ' exp.',
                };
            } catch (err) {
                console.error('Error consumiendo caramelos de Senda:', err);
                this.feedback = { tipo: 'error', mensaje: 'No se pudieron consumir los caramelos.' };
            } finally {
                this.savingExp = false;
            }
        },

        async desbloquear(nodo) {
            if (!nodo || this.saving || this.savingExp) {
                return;
            }
            this.saving = true;
            this.feedback = { tipo: '', mensaje: '' };
            try {
                const response = await fetch('/sendas/desbloquear', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ slug: nodo.slug, tipo: this.arbol.tipo.slug }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.feedback = { tipo: 'error', mensaje: data.error || data.message || 'No se pudo desbloquear el movimiento.' };
                    return;
                }
                this.arbol = data.arbol || this.arbol;
                this.feedback = { tipo: 'ok', mensaje: 'Movimiento desbloqueado para toda la cuenta.' };
            } catch (err) {
                console.error('Error desbloqueando movimiento de Senda:', err);
                this.feedback = { tipo: 'error', mensaje: 'No se pudo desbloquear el movimiento.' };
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
@endsection
