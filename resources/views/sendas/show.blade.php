@extends('layouts.app')

@section('title', 'Senda de ' . $arbol['tipo']['nombre'])

@section('content')
<div x-data="sendasApp(@js($arbol))" x-cloak>
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <a href="/sendas" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Volver a Sendas">←</a>
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl" :style="'background-color: ' + arbol.tipo.color + '22'">
                <img :src="'/images/type/' + arbol.tipo.slug + '.webp'" :alt="arbol.tipo.nombre" loading="lazy" decoding="async" class="h-9 w-9 object-contain">
            </span>
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-bold text-gray-900 dark:text-white">Senda de <span x-text="arbol.tipo.nombre"></span></h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Nivel <span x-text="arbol.nivel"></span> / <span x-text="arbol.nivel_maximo"></span>
                    · <span x-text="arbol.desbloqueados + ' / ' + arbol.total_nodos + ' movimientos desbloqueados'"></span>
                </p>
            </div>
        </div>
        <div class="flex flex-col items-end gap-2">
            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1.5 text-sm font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300" title="Caramelos de este tipo">
                <img :src="'/images/candy_type/' + arbol.tipo.slug + '.webp'" :alt="'Caramelos de ' + arbol.tipo.nombre" loading="lazy" decoding="async" class="h-5 w-5 object-contain" onerror="this.style.display='none'">
                <span x-text="arbol.caramelos"></span>
            </span>
            <div class="flex items-center gap-1.5" role="group" aria-label="Consumir caramelos para ganar experiencia">
                <button
                    type="button"
                    @click="consumir(1)"
                    :disabled="saving || savingExp || arbol.caramelos <= 0"
                    :aria-label="'Consumir 1 caramelo de ' + arbol.tipo.nombre + ' para ganar experiencia'"
                    class="rounded-lg border border-amber-300 px-2.5 py-1 text-xs font-bold text-amber-700 transition-colors hover:bg-amber-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400 dark:border-amber-700 dark:text-amber-300 dark:hover:bg-amber-900/20 dark:disabled:border-gray-700 dark:disabled:text-gray-500"
                >x1</button>
                <button
                    type="button"
                    @click="consumir(10)"
                    :disabled="saving || savingExp || arbol.caramelos <= 0"
                    :aria-label="'Consumir 10 caramelos de ' + arbol.tipo.nombre + ' para ganar experiencia'"
                    class="rounded-lg border border-amber-300 px-2.5 py-1 text-xs font-bold text-amber-700 transition-colors hover:bg-amber-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400 dark:border-amber-700 dark:text-amber-300 dark:hover:bg-amber-900/20 dark:disabled:border-gray-700 dark:disabled:text-gray-500"
                >x10</button>
                <button
                    type="button"
                    @click="consumir(100)"
                    :disabled="saving || savingExp || arbol.caramelos <= 0"
                    :aria-label="'Consumir 100 caramelos de ' + arbol.tipo.nombre + ' para ganar experiencia'"
                    class="rounded-lg border border-amber-300 px-2.5 py-1 text-xs font-bold text-amber-700 transition-colors hover:bg-amber-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400 dark:border-amber-700 dark:text-amber-300 dark:hover:bg-amber-900/20 dark:disabled:border-gray-700 dark:disabled:text-gray-500"
                >x100</button>
            </div>
            <p class="text-[10px] font-semibold text-gray-400 dark:text-gray-500">+<span x-text="arbol.exp_por_caramelo"></span> exp por caramelo</p>
        </div>
    </div>

    <template x-if="feedback.mensaje">
        <div
            class="mb-4 rounded-lg px-3 py-2 text-xs"
            :role="feedback.tipo === 'error' ? 'alert' : 'status'"
            :class="feedback.tipo === 'error' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'"
            x-text="feedback.mensaje"
        ></div>
    </template>

    <!-- Filtro de movimientos -->
    <div class="mb-4 rounded-2xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
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
                    class="w-full rounded-lg border border-gray-200 bg-white py-1.5 pl-9 pr-3 text-xs text-gray-900 placeholder-gray-400 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                >
                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <button
                type="button"
                x-show="hayFiltro()"
                @click="limpiarFiltro()"
                class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
            >Limpiar</button>
            <p id="filtro-movimientos-total" class="ml-auto text-xs text-gray-500 dark:text-gray-400">
                <span x-text="totalVisible()"></span> movimientos visibles
            </p>
        </div>
    </div>

    <!-- Progreso -->
    <div class="mb-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-sm font-bold text-gray-900 dark:text-white">Progreso de la Senda</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    <span x-text="arbol.exp.toLocaleString('es-ES')"></span> / <span x-text="arbol.exp_siguiente.toLocaleString('es-ES')"></span> exp
                </p>
            </div>
            <p class="text-xs font-bold" :style="'color: ' + arbol.tipo.color" x-text="arbol.porcentaje + '%'"></p>
        </div>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700" role="progressbar" :aria-valuenow="arbol.porcentaje" aria-valuemin="0" aria-valuemax="100" aria-label="Progreso de la Senda">
            <div class="h-full rounded-full transition-all duration-500" :style="'width: ' + arbol.porcentaje + '%; background-color: ' + arbol.tipo.color"></div>
        </div>
        <template x-if="arbol.nivel >= arbol.nivel_maximo">
            <p class="mt-2 text-[10px] font-semibold text-amber-600 dark:text-amber-400">
                Tramo final: hasta <span x-text="arbol.exp_maxima.toLocaleString('es-ES')"></span> exp. La experiencia sigue acumulándose.
            </p>
        </template>
    </div>

    <!-- Beneficios pasivos -->
    <section class="mb-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800" aria-labelledby="beneficios-senda-titulo">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 id="beneficios-senda-titulo" class="text-sm font-bold text-gray-900 dark:text-white">Beneficios pasivos</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Se aplican a tus combatientes y capturas; suben con el nivel de la Senda.</p>
            </div>
            <p class="max-w-md text-[11px] font-semibold text-gray-500 dark:text-gray-400">Niveles 1-3: desbloquean ataques. Niveles 4-5: maestrías pasivas.</p>
        </div>

        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nivel actual</p>
                <p class="text-sm font-bold text-gray-900 dark:text-white">Nivel <span x-text="arbol.nivel"></span></p>
                <dl class="mt-2 space-y-1.5">
                    <template x-for="fila in beneficiosFilas(arbol.beneficios)" :key="'beneficio-actual-' + fila.clave">
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-xs text-gray-500 dark:text-gray-400">
                                <span x-text="fila.etiqueta"></span>
                                <span x-show="fila.reduccion" class="text-[10px]">(reducción)</span>
                            </dt>
                            <dd class="text-xs font-bold tabular-nums text-gray-900 dark:text-white" x-text="formatearBeneficio(fila.valor, fila.reduccion)"></dd>
                        </div>
                    </template>
                </dl>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/30">
                <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400" x-text="arbol.nivel < arbol.nivel_maximo ? 'Siguiente nivel' : 'Nivel máximo'"></p>
                <p class="text-sm font-bold text-gray-900 dark:text-white">Nivel <span x-text="arbol.siguiente_nivel"></span></p>
                <dl class="mt-2 space-y-1.5">
                    <template x-for="fila in beneficiosFilas(arbol.beneficios_siguiente)" :key="'beneficio-siguiente-' + fila.clave">
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-xs text-gray-500 dark:text-gray-400">
                                <span x-text="fila.etiqueta"></span>
                                <span x-show="fila.reduccion" class="text-[10px]">(reducción)</span>
                            </dt>
                            <dd class="text-xs font-bold tabular-nums text-gray-900 dark:text-white" x-text="formatearBeneficio(fila.valor, fila.reduccion)"></dd>
                        </div>
                    </template>
                </dl>
                <p class="mt-2 text-[10px] text-gray-400 dark:text-gray-500" x-show="arbol.nivel >= arbol.nivel_maximo">Nivel máximo: no hay mejoras adicionales.</p>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <template x-for="categoria in ['fisico', 'especial', 'estado']" :key="'senda-rama-' + categoria">
            <section class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-3 flex items-center justify-between gap-2 border-b border-gray-100 pb-2 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <img :src="categoriaIcono(categoria)" :alt="etiquetaCategoria(categoria)" loading="lazy" decoding="async" class="h-6 w-6 object-contain">
                        <h2 class="text-xs font-bold uppercase tracking-wide text-gray-600 dark:text-gray-300" x-text="etiquetaCategoria(categoria)"></h2>
                    </div>
                    <span class="text-[10px] font-semibold text-gray-400 dark:text-gray-500" x-text="contadorCategoria(categoria) + ' movimientos'"></span>
                </div>

                <div class="space-y-2">
                    <template x-for="grupo in gruposDe(categoria)" :key="categoria + '-grupo-' + grupo.nivel">
                        <div>
                            <button
                                type="button"
                                @click="toggleGrupo(categoria, grupo.nivel)"
                                :aria-expanded="estaAbierto(categoria, grupo.nivel)"
                                :aria-controls="'grupo-' + categoria + '-' + grupo.nivel"
                                class="flex w-full items-center gap-2 rounded-lg bg-gray-50 px-2 py-1.5 text-left transition-colors hover:bg-gray-100 dark:bg-gray-900/40 dark:hover:bg-gray-900/70"
                            >
                                <svg class="h-3 w-3 shrink-0 text-gray-400 transition-transform" :class="estaAbierto(categoria, grupo.nivel) ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400" x-text="'Nv Senda ' + grupo.nivel"></span>
                                <template x-if="grupoTieneMaestrias(grupo)">
                                    <span class="rounded-full bg-purple-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">Maestrías</span>
                                </template>
                                <span class="ml-auto text-[10px] font-semibold text-gray-400 dark:text-gray-500" x-text="contadorGrupo(grupo) + ' movimientos'"></span>
                            </button>

                            <div :id="'grupo-' + categoria + '-' + grupo.nivel" x-show="estaAbierto(categoria, grupo.nivel)" x-cloak class="mt-2 space-y-2">
                                <template x-for="nodo in grupo.nodos" :key="'senda-nodo-' + nodo.slug">
                                    <article
                                        class="rounded-xl border p-2.5 transition-colors"
                                        :class="nodo.desbloqueado
                                            ? 'border-emerald-300 bg-emerald-50/50 dark:border-emerald-700 dark:bg-emerald-900/10'
                                            : (nodo.puede_desbloquear
                                                ? 'border-amber-300 bg-white dark:border-amber-700 dark:bg-gray-900/30'
                                                : 'border-gray-200 bg-white opacity-70 dark:border-gray-700 dark:bg-gray-900/30')"
                                    >
                                        <div class="flex items-start justify-between gap-1.5">
                                            <p class="min-w-0 text-sm font-semibold leading-tight text-gray-900 dark:text-white" x-text="nodo.name"></p>
                                            <img :src="'/images/type/' + nodo.tipo_slug + '.webp'" :alt="nodo.tipo_nombre" loading="lazy" decoding="async" class="h-6 w-6 shrink-0 object-contain">
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center gap-x-1.5 text-[11px] text-gray-400 dark:text-gray-500">
                                            <span x-text="etiquetaCategoria(nodo.category)"></span>
                                            <span x-show="nodo.base_power > 0" x-text="'· ' + nodo.base_power + ' pot.'"></span>
                                            <template x-if="nodo.es_maestria">
                                                <span class="rounded-full bg-purple-100 px-1.5 py-0.5 text-[10px] font-bold text-purple-700 dark:bg-purple-900/30 dark:text-purple-300" x-text="'Maestría · tramo ' + nodo.tramo"></span>
                                            </template>
                                            <span x-text="'· Nv Senda ' + nodo.nivel_requerido"></span>
                                            <span x-text="'· ' + nodo.coste + ' caramelos'"></span>
                                        </div>
                                        <p class="mt-1 text-[11px] leading-snug text-gray-500 dark:text-gray-400" x-text="nodo.description || 'Sin descripción.'"></p>

                                        <template x-if="nodo.desbloqueado">
                                            <span class="mt-2 block w-full rounded-md bg-emerald-100 px-2 py-1 text-center text-[11px] font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Desbloqueado</span>
                                        </template>
                                        <template x-if="!nodo.desbloqueado">
                                            <button
                                                type="button"
                                                @click="desbloquear(nodo)"
                                                :disabled="!nodo.puede_desbloquear || saving || savingExp"
                                                class="mt-2 w-full rounded-md border border-amber-300 px-2 py-1 text-[11px] font-bold text-amber-700 transition-colors hover:bg-amber-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400 dark:border-amber-700 dark:text-amber-300 dark:hover:bg-amber-900/20 dark:disabled:border-gray-700 dark:disabled:text-gray-500"
                                                x-text="textoBoton(nodo)"
                                            ></button>
                                        </template>
                                    </article>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <p x-show="contadorCategoria(categoria) === 0 && !hayFiltro()" class="py-4 text-center text-xs text-gray-400 dark:text-gray-500">Sin movimientos en esta categoría.</p>
            </section>
        </template>
    </div>

    <!-- Estado vacío global -->
    <div x-show="totalVisible() === 0 && hayFiltro()" class="mt-4 rounded-2xl border border-dashed border-gray-200 p-6 text-center dark:border-gray-700">
        <p class="text-sm text-gray-500 dark:text-gray-400">No hay movimientos que coincidan con el filtro.</p>
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
