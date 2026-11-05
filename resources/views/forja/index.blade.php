@extends('layouts.app')

@section('title', 'Forja')

@section('content')
<div x-data="forjaPage()">
    <header class="mb-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h1 class="text-2xl font-bold text-ink">Forja</h1>
            <p
                class="text-sm text-ink-muted"
                x-show="!cargando && totalRecetas > 0"
                x-cloak
            >
                <span x-text="etiquetaRecetas(totalRecetas)"></span>
            </p>
        </div>
        <p class="mt-1 text-sm text-ink-muted">
            Combina bayas, bonguris y recipientes para fabricar objetos.
        </p>
    </header>

    <div class="space-y-4" aria-live="polite">
        <x-ui.alert variant="success" class="mb-0" x-show="mensajeExito" x-cloak>
            <span x-text="mensajeExito"></span>
        </x-ui.alert>

        <x-ui.alert variant="danger" class="mb-0" x-show="errorAccion" x-cloak>
            <span x-text="errorAccion"></span>
        </x-ui.alert>
    </div>

    <div
        x-show="errorCarga"
        x-cloak
        role="alert"
        class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-control border border-danger bg-danger-soft px-4 py-3 text-sm text-danger-ink"
    >
        <p x-text="errorCarga"></p>
        <x-ui.button variant="secondary" size="sm" x-on:click="cargar()">
            Reintentar
        </x-ui.button>
    </div>

    <div
        x-show="cargando"
        x-cloak
        role="status"
        class="mt-6 flex items-center justify-center gap-3 rounded-card border border-line bg-surface px-4 py-10 text-sm text-ink-muted"
    >
        <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
        </svg>
        <span>Cargando recetas…</span>
    </div>

    <div
        x-show="!cargando && !errorCarga && totalRecetas === 0"
        x-cloak
        class="mt-6 rounded-card border border-line bg-surface px-4 py-10 text-center text-sm text-ink-muted"
    >
        No hay recetas disponibles por ahora.
    </div>

    <section
        x-show="!cargando && totalRecetas > 0"
        x-cloak
        class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4 items-start"
        aria-labelledby="recetas-title"
    >
        <h2 id="recetas-title" class="sr-only">Recetas disponibles</h2>

        <template x-for="(categoria, indiceCategoria) in categorias" :key="categoria.clave">
            <section
                class="rounded-card border border-line bg-surface min-w-0"
                x-bind:aria-labelledby="'categoria-titulo-' + categoria.clave"
            >
                <h3 x-bind:id="'categoria-titulo-' + categoria.clave">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-card px-4 py-3 text-left transition-colors hover:bg-surface-sunken focus-ring"
                        x-on:click="categoria.abierta = !categoria.abierta"
                        x-bind:aria-expanded="categoria.abierta.toString()"
                        x-bind:aria-controls="'categoria-' + categoria.clave"
                    >
                        <span class="min-w-0 flex-1 text-sm font-semibold text-ink" x-text="categoria.nombre"></span>
                        <span
                            class="shrink-0 rounded-full bg-surface-sunken px-2.5 py-0.5 text-2xs font-medium tabular-nums text-ink-muted"
                            x-bind:aria-label="etiquetaRecetas(contarRecetas(categoria))"
                            x-text="contarRecetas(categoria)"
                        ></span>
                        <svg
                            class="h-4 w-4 shrink-0 text-ink-muted transition-transform duration-200"
                            x-bind:class="categoria.abierta ? 'rotate-180' : ''"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </h3>

                <div
                    x-bind:id="'categoria-' + categoria.clave"
                    x-show="categoria.abierta"
                    x-transition
                >
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-3">
                        <template x-for="(grupo, indiceGrupo) in categoria.recetas" :key="grupo.clave">
                            <x-ui.card
                                as="article"
                                padding="none"
                                class="flex flex-col"
                                x-bind:class="[
                                    recetasAbiertas[grupo.clave] ? 'sm:col-span-2' : '',
                                    puedeFabricarGrupo(grupo) ? '' : 'opacity-60 grayscale',
                                ]"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 border-b border-line bg-surface-sunken px-3 py-2.5 text-left focus-ring"
                                    x-on:click="toggleReceta(grupo)"
                                    x-bind:aria-expanded="recetasAbiertas[grupo.clave] ? 'true' : 'false'"
                                    x-bind:aria-controls="'receta-' + indiceCategoria + '-' + indiceGrupo"
                                >
                                    <img
                                        :src="grupo.icono"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                        class="h-8 w-8 shrink-0 object-contain"
                                    >
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-xs font-semibold text-ink sm:text-sm" x-text="grupo.nombre"></span>
                                        <span class="block text-2xs text-ink-muted" x-text="'×' + grupo.cantidad + ' por fabricación'"></span>
                                    </span>
                                    <span
                                        class="shrink-0 whitespace-nowrap rounded-full bg-danger-soft px-2 py-0.5 text-2xs font-medium text-danger-ink"
                                        x-show="!puedeFabricarGrupo(grupo)"
                                    >Faltan materiales</span>
                                    <svg
                                        class="h-4 w-4 shrink-0 text-ink-muted transition-transform duration-200"
                                        x-bind:class="recetasAbiertas[grupo.clave] ? 'rotate-180' : ''"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                    </svg>
                                </button>

                                <div
                                    x-bind:id="'receta-' + indiceCategoria + '-' + indiceGrupo"
                                    x-show="recetasAbiertas[grupo.clave]"
                                    x-cloak
                                    class="flex flex-1 flex-col gap-3 p-4"
                                >
                                    <template x-if="grupo.clave === 'material:recipiente'">
                                        <ul class="space-y-2" aria-label="Bonguris para fabricar recipientes">
                                            <template x-for="variante in variantesRecipiente(grupo)" :key="'recipiente-' + variante.clave">
                                                <li class="rounded-control border border-line bg-surface-sunken px-2 py-2">
                                                    <div class="flex items-center gap-2">
                                                        <img
                                                            :src="variante.ingredientes[0].icono"
                                                            alt=""
                                                            loading="lazy"
                                                            decoding="async"
                                                            class="h-6 w-6 shrink-0 object-contain"
                                                        >
                                                        <div class="min-w-0 flex-1">
                                                            <p class="truncate text-xs font-semibold text-ink" x-text="variante.ingredientes[0].nombre"></p>
                                                            <p class="text-2xs text-ink-muted" x-show="variante.ingredientes[0].origen">
                                                                Origen: <span x-text="variante.ingredientes[0].origen"></span>
                                                            </p>
                                                        </div>
                                                        <span
                                                            class="shrink-0 tabular-nums text-2xs font-medium"
                                                            x-bind:class="variante.ingredientes[0].disponible < variante.ingredientes[0].requerido ? 'text-danger-ink' : 'text-ink-muted'"
                                                            x-bind:aria-label="'Disponible ' + variante.ingredientes[0].disponible + ' de ' + variante.ingredientes[0].requerido"
                                                        >
                                                            <span x-text="variante.ingredientes[0].disponible"></span>/<span x-text="variante.ingredientes[0].requerido"></span>
                                                        </span>
                                                    </div>
                                                    <div class="mt-2 flex items-center justify-between gap-2">
                                                        <p class="text-2xs text-ink-muted">
                                                            Tienes <span class="tabular-nums font-semibold text-ink" x-text="variante.ingredientes[0].disponible"></span>
                                                        </p>
                                                        <x-ui.button
                                                            variant="primary"
                                                            size="sm"
                                                            type="button"
                                                            x-on:click="seleccion[grupo.clave] = variante.clave; fabricar(grupo)"
                                                            x-bind:disabled="fabricando !== null || !variante.puede_fabricar"
                                                            x-bind:aria-disabled="fabricando !== null || !variante.puede_fabricar || null"
                                                            x-bind:class="fabricando !== null || !variante.puede_fabricar ? 'opacity-60 cursor-not-allowed' : ''"
                                                            x-bind:title="!variante.puede_fabricar ? textoMotivo(variante.motivo) : ''"
                                                        >
                                                            <span x-text="fabricando === variante.clave ? 'Fabricando…' : 'Fabricar'"></span>
                                                        </x-ui.button>
                                                    </div>
                                                </li>
                                            </template>
                                        </ul>
                                    </template>

                                    <template x-if="grupo.clave !== 'material:recipiente'">
                                        <div class="flex flex-1 flex-col gap-3">
                                            <div x-show="grupo.variantes.length > 1" x-cloak>
                                                <label
                                                    :for="'forja-variante-' + indiceCategoria + '-' + indiceGrupo"
                                                    class="mb-1 block text-2xs font-semibold uppercase tracking-wide text-ink-muted"
                                                >
                                                    Receta
                                                </label>
                                                <select
                                                    :id="'forja-variante-' + indiceCategoria + '-' + indiceGrupo"
                                                    x-model="seleccion[grupo.clave]"
                                                    class="w-full rounded-control border border-line bg-surface px-3 py-2 text-sm text-ink focus-ring"
                                                >
                                                    <template x-for="variante in grupo.variantes" :key="variante.clave">
                                                        <option :value="variante.clave" x-text="variante.etiqueta"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <p
                                                x-show="grupo.variantes.length === 1"
                                                class="text-2xs text-ink-muted"
                                                x-text="grupo.variantes.length === 1 ? grupo.variantes[0].etiqueta : ''"
                                            ></p>

                                            <ul class="space-y-1" aria-label="Ingredientes de la receta">
                                                <template x-for="ingrediente in varianteSeleccionada(grupo).ingredientes" :key="ingrediente.clave">
                                                    <li
                                                        class="flex items-center gap-2 rounded-control px-1 py-0.5 text-sm"
                                                        x-bind:class="ingrediente.disponible < ingrediente.requerido ? 'bg-danger-soft' : ''"
                                                    >
                                                        <img
                                                            :src="ingrediente.icono"
                                                            alt=""
                                                            loading="lazy"
                                                            decoding="async"
                                                            class="h-5 w-5 shrink-0 object-contain"
                                                        >
                                                        <span class="min-w-0 flex-1 truncate text-ink" x-text="ingrediente.nombre"></span>
                                                        <span
                                                            class="shrink-0 tabular-nums text-2xs font-medium"
                                                            x-bind:class="ingrediente.disponible < ingrediente.requerido ? 'text-danger-ink' : 'text-ink-muted'"
                                                            x-bind:aria-label="'Disponible ' + ingrediente.disponible + ' de ' + ingrediente.requerido"
                                                        >
                                                            <span x-text="ingrediente.disponible"></span>/<span x-text="ingrediente.requerido"></span>
                                                        </span>
                                                        <span
                                                            class="sr-only"
                                                            x-show="ingrediente.disponible < ingrediente.requerido"
                                                        >Falta stock</span>
                                                    </li>
                                                </template>
                                            </ul>

                                            <div class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-2">
                                                <p
                                                    class="text-2xs text-danger-ink"
                                                    x-show="!varianteSeleccionada(grupo).puede_fabricar"
                                                    x-text="textoMotivo(varianteSeleccionada(grupo).motivo)"
                                                ></p>

                                                <x-ui.button
                                                    variant="primary"
                                                    size="sm"
                                                    type="button"
                                                    class="ml-auto"
                                                    x-on:click="fabricar(grupo)"
                                                    x-bind:disabled="fabricando !== null || !varianteSeleccionada(grupo).puede_fabricar"
                                                    x-bind:aria-disabled="fabricando !== null || !varianteSeleccionada(grupo).puede_fabricar || null"
                                                    x-bind:class="fabricando !== null || !varianteSeleccionada(grupo).puede_fabricar ? 'opacity-60 cursor-not-allowed' : ''"
                                                    x-bind:title="!varianteSeleccionada(grupo).puede_fabricar ? textoMotivo(varianteSeleccionada(grupo).motivo) : ''"
                                                >
                                                    <span x-text="fabricando === varianteSeleccionada(grupo).clave ? 'Fabricando…' : 'Fabricar'"></span>
                                                </x-ui.button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </x-ui.card>
                        </template>
                    </div>

                    <p
                        x-show="categoria.recetas.length === 0"
                        class="px-4 pb-4 text-sm text-ink-muted"
                    >
                        No hay recetas en esta categoría.
                    </p>
                </div>
            </section>
        </template>
    </section>
</div>
@endsection

@push('scripts')
<script>
const FORJA_CATEGORIAS = [
    { clave: 'medicinas', nombre: 'Medicinas' },
    { clave: 'mejoras', nombre: 'Mejoras' },
    { clave: 'reductores', nombre: 'Reductores' },
    { clave: 'resistencias', nombre: 'Resistencias' },
];

function forjaCategoriaDeResultado(clave) {
    if (typeof clave !== 'string') {
        return null;
    }

    if (clave === 'material:recipiente' || clave.startsWith('pocion:')) {
        return 'medicinas';
    }

    if (clave.startsWith('mejora:')) {
        return 'mejoras';
    }

    if (clave.startsWith('reductor:')) {
        return 'reductores';
    }

    if (clave.startsWith('resistencia:')) {
        return 'resistencias';
    }

    return null;
}

function forjaPage() {
    return {
        recetas: [],
        categorias: [],
        totalRecetas: 0,
        seleccion: {},
        recetasAbiertas: {},
        cargando: true,
        errorCarga: null,
        fabricando: null,
        mensajeExito: null,
        errorAccion: null,

        init() {
            window.addEventListener('inventario:actualizado', () => this.cargar(false));
            this.cargar();
        },

        async cargar(mostrarCarga = true) {
            if (mostrarCarga) {
                this.cargando = true;
            }

            this.errorCarga = null;

            try {
                const response = await fetch('/api/forja/recetas', {
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('No se pudieron cargar las recetas.');
                }

                const data = await response.json();

                this.recetas = Array.isArray(data.recetas) ? data.recetas : [];
                this.agrupar();
            } catch (error) {
                this.errorCarga = error instanceof Error
                    ? error.message
                    : 'No se pudieron cargar las recetas.';
            } finally {
                if (mostrarCarga) {
                    this.cargando = false;
                }
            }
        },

        agrupar() {
            const grupos = new Map();

            this.recetas.forEach((receta) => {
                if (!grupos.has(receta.resultado_clave)) {
                    grupos.set(receta.resultado_clave, {
                        clave: receta.resultado_clave,
                        nombre: receta.resultado_nombre,
                        icono: receta.resultado_icono,
                        cantidad: receta.resultado_cantidad,
                        variantes: [],
                    });
                }

                grupos.get(receta.resultado_clave).variantes.push(receta);
            });

            const abiertas = new Map(
                this.categorias.map((categoria) => [categoria.clave, categoria.abierta]),
            );

            const categorias = FORJA_CATEGORIAS.map((categoria) => ({
                clave: categoria.clave,
                nombre: categoria.nombre,
                abierta: abiertas.has(categoria.clave) ? abiertas.get(categoria.clave) : false,
                recetas: [],
            }));

            const categoriasPorClave = new Map(
                categorias.map((categoria) => [categoria.clave, categoria]),
            );

            grupos.forEach((grupo) => {
                const categoria = categoriasPorClave.get(forjaCategoriaDeResultado(grupo.clave));

                if (categoria) {
                    categoria.recetas.push(grupo);
                }
            });

            this.categorias = categorias;
            this.totalRecetas = categorias.reduce(
                (total, categoria) => total + categoria.recetas.reduce(
                    (subtotal, grupo) => subtotal + grupo.variantes.length,
                    0,
                ),
                0,
            );

            this.categorias
                .flatMap((categoria) => categoria.recetas)
                .forEach((grupo) => {
                    const seleccionActual = this.seleccion[grupo.clave];
                    const sigueDisponible = grupo.variantes.some(
                        (variante) => variante.clave === seleccionActual,
                    );

                    if (!sigueDisponible) {
                        const preferida = grupo.variantes.find((variante) => variante.puede_fabricar)
                            || grupo.variantes[0];

                        this.seleccion[grupo.clave] = preferida.clave;
                    }
                });
        },

        contarRecetas(categoria) {
            return categoria.recetas.reduce(
                (total, grupo) => total + grupo.variantes.length,
                0,
            );
        },

        etiquetaRecetas(total) {
            return total === 1 ? '1 receta' : total + ' recetas';
        },

        puedeFabricarGrupo(grupo) {
            const variante = this.varianteSeleccionada(grupo);

            return variante !== null && variante.puede_fabricar === true;
        },

        toggleReceta(grupo) {
            if (!grupo || typeof grupo.clave !== 'string') {
                return;
            }

            this.recetasAbiertas[grupo.clave] = !this.recetasAbiertas[grupo.clave];
        },

        varianteSeleccionada(grupo) {
            if (!grupo || !Array.isArray(grupo.variantes) || grupo.variantes.length === 0) {
                return {
                    clave: null,
                    puede_fabricar: false,
                    motivo: 'faltan_materiales',
                    ingredientes: [],
                    resultado_nombre: grupo ? grupo.nombre : '',
                    resultado_cantidad: 1,
                };
            }

            const clave = this.seleccion[grupo.clave];

            return grupo.variantes.find((variante) => variante.clave === clave)
                || grupo.variantes[0];
        },

        /** Bonguris del grupo recipiente: primero los que tienen existencias (orden estable). */
        variantesRecipiente(grupo) {
            if (!grupo || !Array.isArray(grupo.variantes)) {
                return [];
            }

            return [...grupo.variantes].sort((a, b) => {
                const disponibleA = Number(a?.ingredientes?.[0]?.disponible ?? 0);
                const disponibleB = Number(b?.ingredientes?.[0]?.disponible ?? 0);

                if ((disponibleA > 0) === (disponibleB > 0)) {
                    return 0;
                }

                return disponibleA > 0 ? -1 : 1;
            });
        },

        textoMotivo(motivo) {
            if (!motivo) {
                return '';
            }

            const textos = {
                faltan_materiales: 'Faltan materiales',
            };

            return textos[motivo] || motivo;
        },

        async fabricar(grupo) {
            const variante = this.varianteSeleccionada(grupo);

            if (!variante || !variante.puede_fabricar || this.fabricando !== null) {
                return;
            }

            this.fabricando = variante.clave;
            this.mensajeExito = null;
            this.errorAccion = null;

            try {
                const meta = document.querySelector('meta[name="csrf-token"]');

                const response = await fetch('/api/forja/fabricar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': meta ? meta.content : '',
                    },
                    body: JSON.stringify({
                        receta: variante.clave,
                        cantidad: 1,
                    }),
                });

                if (!response.ok) {
                    const body = await response.json().catch(() => ({}));

                    throw new Error(body.message || 'No se pudo fabricar la receta.');
                }

                const data = await response.json().catch(() => ({}));
                const cantidad = data.cantidad || variante.resultado_cantidad;

                this.mensajeExito = 'Has fabricado ' + cantidad + ' × ' + variante.resultado_nombre + '.';

                await this.cargar(false);
            } catch (error) {
                this.errorAccion = error instanceof Error
                    ? error.message
                    : 'No se pudo fabricar la receta.';
            } finally {
                this.fabricando = null;
            }
        },
    };
}
</script>
@endpush
