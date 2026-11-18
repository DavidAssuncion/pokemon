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
                x-bind:class="categoria.clave === 'piezas' ? 'md:col-span-2 xl:col-span-4' : ''"
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
                    <p
                        x-show="categoria.respaldo"
                        x-cloak
                        class="px-4 pt-3 text-2xs text-ink-muted"
                    >
                        Estas recetas no tienen sección asignada por el servidor.
                    </p>

                    <div
                        class="grid grid-cols-1 gap-2 p-3 sm:grid-cols-2 xl:grid-cols-3"
                        x-show="categoria.clave === 'piezas'"
                    >
                        <template x-for="familia in categoria.recetas" :key="'familia-' + familia.clave">
                            <x-ui.card
                                as="article"
                                padding="none"
                                class="min-w-0"
                                x-bind:class="familia.fabricables > 0 || familia.completa ? '' : 'opacity-60'"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 px-3 py-3 text-left transition-colors hover:bg-surface-sunken focus-ring"
                                    x-on:click="abrirPiezaModal(familia, $el)"
                                    aria-haspopup="dialog"
                                    x-bind:aria-expanded="(piezaModalAbierta && piezaFamiliaClave === familia.clave).toString()"
                                >
                                    <img
                                        x-bind:src="familia.icono"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                        class="h-10 w-10 shrink-0 object-contain"
                                        onerror="this.onerror=null; this.src='/images/iconos_webp/0.webp';"
                                    >
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-ink" x-text="familia.nombre"></span>
                                        <span class="block truncate text-2xs text-ink-muted" x-text="resumenFamilia(familia)"></span>
                                    </span>
                                    <span
                                        class="shrink-0 whitespace-nowrap rounded-full px-2 py-0.5 text-2xs font-medium"
                                        x-bind:class="claseBadgeFamilia(familia)"
                                        x-text="etiquetaFamilia(familia)"
                                    ></span>
                                </button>
                            </x-ui.card>
                        </template>
                    </div>

                    <div
                        class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-3"
                        x-show="categoria.clave !== 'piezas'"
                    >
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
                                        x-show="!puedeFabricarGrupo(grupo) && motivoTexto(varianteSeleccionada(grupo)) !== ''"
                                        x-text="motivoTexto(varianteSeleccionada(grupo))"
                                    ></span>
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
                                                            x-on:click="seleccion[grupo.clave] = variante.clave; fabricar(variante)"
                                                            x-bind:disabled="fabricando !== null || !variante.puede_fabricar"
                                                            x-bind:aria-disabled="fabricando !== null || !variante.puede_fabricar || null"
                                                            x-bind:class="fabricando !== null || !variante.puede_fabricar ? 'opacity-60 cursor-not-allowed' : ''"
                                                            x-bind:title="!variante.puede_fabricar ? motivoTexto(variante) : ''"
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
                                                    x-text="motivoTexto(varianteSeleccionada(grupo))"
                                                ></p>

                                                <x-ui.button
                                                    variant="primary"
                                                    size="sm"
                                                    type="button"
                                                    class="ml-auto"
                                                    x-on:click="fabricar(varianteSeleccionada(grupo))"
                                                    x-bind:disabled="fabricando !== null || !varianteSeleccionada(grupo).puede_fabricar"
                                                    x-bind:aria-disabled="fabricando !== null || !varianteSeleccionada(grupo).puede_fabricar || null"
                                                    x-bind:class="fabricando !== null || !varianteSeleccionada(grupo).puede_fabricar ? 'opacity-60 cursor-not-allowed' : ''"
                                                    x-bind:title="!varianteSeleccionada(grupo).puede_fabricar ? motivoTexto(varianteSeleccionada(grupo)) : ''"
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
                        x-show="categoria.recetas.length === 0 && categoria.clave === 'piezas'"
                        class="px-4 pb-4 text-sm text-ink-muted"
                    >
                        Aún no tienes nidos construidos. Mejora un nido en cualquier hábitat para desbloquear sus piezas.
                    </p>

                    <p
                        x-show="categoria.recetas.length === 0 && categoria.clave !== 'piezas'"
                        class="px-4 pb-4 text-sm text-ink-muted"
                    >
                        No hay recetas en esta categoría.
                    </p>
                </div>
            </section>
        </template>
    </section>

    <div
        x-show="piezaModalAbierta"
        x-cloak
        x-on:keydown.escape.window="cerrarPiezaModal()"
        x-on:keydown.tab="trampaDeFoco($event, $refs.piezaDialogo)"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pieza-modal-title"
    >
        <div class="absolute inset-0 scrim" x-on:click="cerrarPiezaModal()" aria-hidden="true"></div>

        <div
            x-ref="piezaDialogo"
            tabindex="-1"
            class="relative flex max-h-[85vh] w-full max-w-3xl flex-col rounded-panel bg-surface-raised shadow-modal"
        >
            <header class="flex shrink-0 items-start justify-between gap-3 border-b border-line px-4 py-3">
                <div class="min-w-0">
                    <h2 id="pieza-modal-title" class="truncate text-lg font-semibold text-ink">
                        <span x-text="piezaFamilia() ? piezaFamilia().nombre : ''"></span>
                    </h2>
                    <p
                        class="text-2xs text-ink-muted"
                        x-show="piezaFamilia()"
                        x-text="piezaFamilia() ? resumenFamilia(piezaFamilia()) : ''"
                    ></p>
                </div>
                <button
                    type="button"
                    x-on:click="cerrarPiezaModal()"
                    aria-label="Cerrar"
                    class="shrink-0 rounded-control p-1.5 text-ink-muted transition-colors hover:bg-surface-sunken hover:text-ink focus-ring"
                >
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>

            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4">
                <p
                    x-show="errorAccion"
                    x-cloak
                    role="alert"
                    class="rounded-control border border-danger bg-danger-soft px-3 py-2 text-xs text-danger-ink"
                    x-text="errorAccion"
                ></p>

                <p
                    x-show="mensajeExito"
                    x-cloak
                    role="status"
                    class="rounded-control border border-line bg-success-soft px-3 py-2 text-xs text-success-ink"
                    x-text="mensajeExito"
                ></p>

                <div x-show="nidosModal().length > 1" x-cloak>
                    <label
                        for="pieza-nido"
                        class="mb-1 block text-2xs font-semibold uppercase tracking-wide text-ink-muted"
                    >
                        Bioma y bonguri
                    </label>
                    <select
                        id="pieza-nido"
                        x-model="piezaNidoSeleccionado"
                        x-on:change="recordarNido()"
                        class="w-full rounded-control border border-line bg-surface px-3 py-2 text-sm text-ink focus-ring"
                    >
                        <template x-for="nido in nidosModal()" :key="'nido-' + nido.id">
                            <option x-bind:value="nido.id" x-text="nido.etiqueta"></option>
                        </template>
                    </select>
                </div>

                <div role="tablist" aria-label="Etapas de la familia" class="flex flex-wrap gap-2">
                    <template x-for="especie in especiesModal()" :key="'pieza-tab-' + especie.id">
                        <button
                            type="button"
                            role="tab"
                            x-bind:id="'pieza-tab-' + especie.id"
                            x-bind:aria-selected="(piezaEspecieId === especie.id).toString()"
                            x-bind:aria-controls="'pieza-panel-' + especie.id"
                            x-bind:tabindex="piezaEspecieId === especie.id ? 0 : -1"
                            x-on:click="seleccionarPestana(especie.id)"
                            x-on:keydown.arrow-right.prevent="moverPestana(1)"
                            x-on:keydown.arrow-left.prevent="moverPestana(-1)"
                            x-on:keydown.home.prevent="extremoPestana(false)"
                            x-on:keydown.end.prevent="extremoPestana(true)"
                            class="rounded-control border px-3 py-1.5 text-sm font-medium focus-ring"
                            x-bind:class="piezaEspecieId === especie.id
                                ? 'border-primary bg-primary-soft text-primary-ink'
                                : 'border-line bg-surface text-ink hover:bg-surface-sunken'"
                        >
                            <span x-text="especie.nombre"></span>
                            <span class="text-2xs text-ink-muted" x-text="' · etapa ' + especie.etapa"></span>
                        </button>
                    </template>
                </div>

                <template x-for="especie in especiesModal()" :key="'pieza-panel-' + especie.id">
                    <div
                        role="tabpanel"
                        x-bind:id="'pieza-panel-' + especie.id"
                        x-bind:aria-labelledby="'pieza-tab-' + especie.id"
                        x-show="piezaEspecieId === especie.id"
                        x-cloak
                        tabindex="0"
                        class="grid grid-cols-1 gap-3 lg:grid-cols-3"
                    >
                        <template x-for="celda in recetasEspecie(especie.id)" :key="'pieza-' + especie.id + '-' + celda.tipo">
                            <div class="flex min-w-0 flex-col gap-2 rounded-control border border-line bg-surface p-3">
                                <template x-if="celda.receta">
                                    <div class="flex h-full flex-col gap-2">
                                        <div class="flex flex-wrap items-start justify-between gap-1.5">
                                            <p
                                                class="min-w-0 flex-1 text-sm font-semibold text-ink"
                                                x-text="celda.receta.resultado_nombre"
                                            ></p>
                                            <span
                                                class="shrink-0 rounded-full border px-2 py-0.5 text-2xs font-semibold"
                                                x-bind:class="claseTipoPieza(celda.receta.tipo_pieza)"
                                                x-text="etiquetaTipoPieza(celda.receta.tipo_pieza)"
                                            ></span>
                                        </div>

                                        <ul class="flex flex-wrap gap-1" aria-label="Estadísticas de la pieza">
                                            <template x-for="stat in statsPieza(celda.receta)" :key="'stat-' + especie.id + '-' + celda.tipo + '-' + stat.clave">
                                                <li
                                                    class="rounded-full bg-surface-sunken px-2 py-0.5 text-2xs font-medium tabular-nums text-ink"
                                                    x-bind:aria-label="stat.larga + ': ' + stat.valor"
                                                >
                                                    <span class="text-ink-muted" x-text="stat.corta"></span>
                                                    <span x-text="stat.valor"></span>
                                                </li>
                                            </template>
                                        </ul>

                                        <div
                                            x-show="celda.receta.habilidad"
                                            x-cloak
                                            class="rounded-control border border-line bg-surface-sunken px-2 py-1.5"
                                        >
                                            <p class="text-2xs font-semibold text-ink" x-text="celda.receta.habilidad?.nombre || ''"></p>
                                            <p class="text-2xs text-ink-muted" x-text="celda.receta.habilidad?.descripcion || ''"></p>
                                        </div>

                                        <p x-show="celda.receta.pieza_anterior" x-cloak class="text-2xs text-ink-muted">
                                            Mejora de
                                            <span class="font-semibold text-ink" x-text="celda.receta.pieza_anterior?.nombre || ''"></span>
                                        </p>

                                        <ul class="space-y-1" aria-label="Ingredientes de la receta">
                                            <template x-for="ingrediente in celda.receta.ingredientes" :key="'ing-' + especie.id + '-' + celda.tipo + '-' + ingrediente.clave">
                                                <li
                                                    class="flex items-center gap-1.5 rounded-control px-1 py-0.5"
                                                    x-bind:class="ingrediente.disponible < ingrediente.requerido ? 'bg-danger-soft' : ''"
                                                >
                                                    <img
                                                        x-bind:src="ingrediente.icono"
                                                        alt=""
                                                        loading="lazy"
                                                        decoding="async"
                                                        class="h-4 w-4 shrink-0 object-contain"
                                                    >
                                                    <span class="min-w-0 flex-1 truncate text-2xs text-ink" x-text="ingrediente.nombre"></span>
                                                    <span
                                                        class="shrink-0 tabular-nums text-2xs font-medium"
                                                        x-bind:class="ingrediente.disponible < ingrediente.requerido ? 'text-danger-ink' : 'text-ink-muted'"
                                                        x-bind:aria-label="'Disponible ' + ingrediente.disponible + ' de ' + ingrediente.requerido"
                                                    >
                                                        <span x-text="ingrediente.disponible"></span>/<span x-text="ingrediente.requerido"></span>
                                                    </span>
                                                </li>
                                            </template>
                                        </ul>

                                        <div class="mt-auto flex flex-col gap-2 pt-1">
                                            <p
                                                class="text-2xs text-danger-ink"
                                                x-show="!celda.receta.puede_fabricar"
                                                x-text="motivoTexto(celda.receta)"
                                            ></p>

                                            <x-ui.button
                                                variant="primary"
                                                size="sm"
                                                type="button"
                                                x-on:click="fabricar(celda.receta)"
                                                x-bind:disabled="fabricando !== null || !celda.receta.puede_fabricar"
                                                x-bind:aria-disabled="fabricando !== null || !celda.receta.puede_fabricar ? 'true' : null"
                                                x-bind:class="fabricando !== null || !celda.receta.puede_fabricar ? 'opacity-60 cursor-not-allowed' : ''"
                                                x-bind:title="!celda.receta.puede_fabricar ? motivoTexto(celda.receta) : ''"
                                            >
                                                <span x-text="fabricando === celda.receta.clave ? 'Fabricando…' : 'Fabricar'"></span>
                                            </x-ui.button>
                                        </div>
                                    </div>
                                </template>

                                <p x-show="!celda.receta" class="text-sm text-ink-muted">No disponible</p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function forjaPage() {
    return {
        recetas: [],
        secciones: [],
        categorias: [],
        familiasPiezas: [],
        totalRecetas: 0,
        seleccion: {},
        recetasAbiertas: {},
        cargando: true,
        errorCarga: null,
        fabricando: null,
        mensajeExito: null,
        errorAccion: null,
        piezaModalAbierta: false,
        piezaFamiliaClave: null,
        piezaNido: {},
        piezaNidoSeleccionado: null,
        piezaEspecieId: null,
        piezaOrigenFoco: null,

        init() {
            window.addEventListener('inventario:actualizado', () => this.cargar(false));
            this.cargar();
        },

        destroy() {
            document.body.classList.remove('overflow-hidden');
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

                this.secciones = Array.isArray(data.secciones) ? data.secciones : [];
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
            const familias = new Map();

            this.recetas.forEach((receta) => {
                if (this.esRecetaDeFamilia(receta)) {
                    const clave = String(receta.familia.evolution_chain_id);

                    if (!familias.has(clave)) {
                        familias.set(clave, {
                            clave: clave,
                            nombre: receta.familia.nombre,
                            icono: '/images/iconos_webp/' + receta.familia.primer_integrante_id + '.webp',
                            recetas: [],
                        });
                    }

                    familias.get(clave).recetas.push(receta);

                    return;
                }

                if (!grupos.has(receta.resultado_clave)) {
                    grupos.set(receta.resultado_clave, {
                        clave: receta.resultado_clave,
                        nombre: receta.resultado_nombre,
                        icono: receta.resultado_icono,
                        cantidad: receta.resultado_cantidad,
                        categoria: receta.categoria ?? null,
                        variantes: [],
                    });
                }

                grupos.get(receta.resultado_clave).variantes.push(receta);
            });

            const familiasPiezas = [...familias.values()]
                .map((familia) => this.resumirFamilia(familia));

            const abiertas = new Map(
                this.categorias.map((categoria) => [categoria.clave, categoria.abierta]),
            );

            const secciones = [...this.secciones].sort(
                (a, b) => Number(a.orden) - Number(b.orden),
            );

            const categorias = secciones.map((seccion) => ({
                clave: seccion.clave,
                nombre: seccion.nombre,
                abierta: abiertas.has(seccion.clave)
                    ? abiertas.get(seccion.clave)
                    : seccion.clave === 'piezas',
                recetas: [],
                respaldo: false,
            }));

            const categoriasPorClave = new Map(
                categorias.map((categoria) => [categoria.clave, categoria]),
            );

            const sinSeccion = [];

            grupos.forEach((grupo) => {
                const categoria = grupo.categoria === null
                    ? null
                    : categoriasPorClave.get(grupo.categoria);

                if (categoria) {
                    categoria.recetas.push(grupo);
                } else {
                    sinSeccion.push(grupo);
                }
            });

            const categoriaPiezas = categoriasPorClave.get('piezas');

            if (categoriaPiezas) {
                familiasPiezas.forEach((familia) => categoriaPiezas.recetas.push(familia));
            }

            if (sinSeccion.length > 0) {
                categorias.push({
                    clave: 'sin_seccion',
                    nombre: 'Otras recetas',
                    abierta: abiertas.has('sin_seccion') ? abiertas.get('sin_seccion') : true,
                    recetas: sinSeccion,
                    respaldo: true,
                });
            }

            this.familiasPiezas = familiasPiezas;
            this.categorias = categorias;
            this.totalRecetas = categorias.reduce(
                (total, categoria) => total + this.contarRecetas(categoria),
                0,
            );

            this.categorias
                .filter((categoria) => categoria.clave !== 'piezas')
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

            this.normalizarModalPiezas();
        },

        /** Una receta es de familia cuando el backend resuelve familia, especie y nido. */
        esRecetaDeFamilia(receta) {
            return receta !== null
                && typeof receta === 'object'
                && receta.familia !== null
                && typeof receta.familia === 'object'
                && receta.familia.evolution_chain_id !== null
                && receta.familia.evolution_chain_id !== undefined;
        },

        /** Agrupa las recetas de una familia en biomas y etapas para la tarjeta y las pestañas. */
        resumirFamilia(familia) {
            const nidos = new Map();
            const especies = new Map();

            familia.recetas.forEach((receta) => {
                const habitat = receta.habitat || null;
                const nidoId = habitat && habitat.id !== null && habitat.id !== undefined
                    ? String(habitat.id)
                    : null;

                if (nidoId !== null && !nidos.has(nidoId)) {
                    nidos.set(nidoId, {
                        id: nidoId,
                        etiqueta: (habitat.nombre || '') + ' · ' + (receta.bonguri?.nombre || ''),
                    });
                }

                const especie = receta.especie || null;
                const especieId = especie && especie.id !== null && especie.id !== undefined
                    ? Number(especie.id)
                    : null;

                if (especieId !== null && !especies.has(especieId)) {
                    especies.set(especieId, {
                        id: especieId,
                        nombre: especie.nombre,
                        etapa: Number(especie.etapa ?? 0),
                    });
                }
            });

            return {
                clave: familia.clave,
                nombre: familia.nombre,
                icono: familia.icono,
                recetas: familia.recetas,
                nidos: [...nidos.values()].sort((a, b) => a.etiqueta.localeCompare(b.etiqueta, 'es')),
                especies: [...especies.values()].sort((a, b) => a.etapa - b.etapa || a.id - b.id),
                fabricables: familia.recetas.filter((receta) => receta.puede_fabricar === true).length,
                completa: familia.recetas.length > 0
                    && familia.recetas.every((receta) => receta.motivo === 'pieza_ya_poseida'),
            };
        },

        /** Mantiene abierta la familia y su pestaña/nido tras refrescar las recetas. */
        normalizarModalPiezas() {
            const familia = this.piezaFamilia();

            if (familia === null) {
                if (this.piezaModalAbierta) {
                    this.cerrarPiezaModal();
                }

                this.piezaFamiliaClave = null;
                this.piezaEspecieId = null;
                this.piezaNidoSeleccionado = null;

                return;
            }

            const guardado = this.piezaNido[familia.clave];
            const candidato = this.piezaNidoSeleccionado !== null
                ? this.piezaNidoSeleccionado
                : guardado;

            this.piezaNidoSeleccionado = familia.nidos.some((nido) => nido.id === candidato)
                ? candidato
                : (familia.nidos.length > 0 ? familia.nidos[0].id : null);

            this.piezaEspecieId = familia.especies.some((especie) => especie.id === this.piezaEspecieId)
                ? this.piezaEspecieId
                : (familia.especies.length > 0 ? familia.especies[0].id : null);
        },

        contarRecetas(categoria) {
            return categoria.recetas.reduce(
                (total, item) => total + (Array.isArray(item.variantes) ? item.variantes.length : item.recetas.length),
                0,
            );
        },

        etiquetaRecetas(total) {
            return total === 1 ? '1 receta' : total + ' recetas';
        },

        /** Subtítulo de la tarjeta de familia: biomas y rango de etapas. */
        resumenFamilia(familia) {
            if (!familia || !Array.isArray(familia.especies) || familia.especies.length === 0) {
                return '';
            }

            const biomas = familia.nidos.length;
            const etapas = familia.especies.map((especie) => especie.etapa);
            const primera = Math.min(...etapas);
            const ultima = Math.max(...etapas);
            const rango = primera === ultima ? 'etapa ' + primera : 'etapas ' + primera + '–' + ultima;

            return biomas + (biomas === 1 ? ' bioma' : ' biomas') + ' · ' + rango;
        },

        etiquetaFamilia(familia) {
            if (!familia) {
                return '';
            }

            if (familia.completa) {
                return 'Completa';
            }

            if (familia.fabricables > 0) {
                return familia.fabricables + (familia.fabricables === 1 ? ' fabricable' : ' fabricables');
            }

            return 'Bloqueada';
        },

        claseBadgeFamilia(familia) {
            if (!familia) {
                return 'bg-surface-sunken text-ink-muted';
            }

            if (familia.completa) {
                return 'bg-success-soft text-success-ink';
            }

            return familia.fabricables > 0
                ? 'bg-primary-soft text-primary-ink'
                : 'bg-surface-sunken text-ink-muted';
        },

        piezaFamilia() {
            return this.familiasPiezas.find((familia) => familia.clave === this.piezaFamiliaClave)
                || null;
        },

        nidosModal() {
            const familia = this.piezaFamilia();

            return familia ? familia.nidos : [];
        },

        especiesModal() {
            const familia = this.piezaFamilia();

            return familia ? familia.especies : [];
        },

        abrirPiezaModal(familia, tarjeta) {
            if (!familia) {
                return;
            }

            const guardado = this.piezaNido[familia.clave];

            this.piezaOrigenFoco = tarjeta instanceof HTMLElement ? tarjeta : null;
            this.piezaFamiliaClave = familia.clave;
            this.piezaNidoSeleccionado = familia.nidos.some((nido) => nido.id === guardado)
                ? guardado
                : (familia.nidos.length > 0 ? familia.nidos[0].id : null);
            this.piezaEspecieId = familia.especies.length > 0 ? familia.especies[0].id : null;
            this.piezaModalAbierta = true;
            document.body.classList.add('overflow-hidden');

            this.$nextTick(() => {
                const dialogo = this.$refs.piezaDialogo;
                const select = dialogo ? dialogo.querySelector('#pieza-nido') : null;

                // El `<select>` se crea con las opciones del bioma: fija el bioma recordado.
                if (select && this.piezaNidoSeleccionado !== null) {
                    select.value = this.piezaNidoSeleccionado;
                }

                const primero = dialogo ? dialogo.querySelector('button, select, [tabindex="0"]') : null;

                (primero || dialogo)?.focus();
            });
        },

        cerrarPiezaModal() {
            if (!this.piezaModalAbierta) {
                return;
            }

            const familia = this.piezaFamilia();

            if (familia && this.piezaNidoSeleccionado !== null) {
                this.piezaNido[familia.clave] = this.piezaNidoSeleccionado;
            }

            const origen = this.piezaOrigenFoco;

            this.piezaModalAbierta = false;
            this.piezaOrigenFoco = null;
            document.body.classList.remove('overflow-hidden');

            this.$nextTick(() => {
                if (origen instanceof HTMLElement && origen.isConnected) {
                    origen.focus();
                }
            });
        },

        recordarNido() {
            const familia = this.piezaFamilia();

            if (familia) {
                this.piezaNido[familia.clave] = this.piezaNidoSeleccionado;
            }
        },

        seleccionarPestana(especieId, enfocar = false) {
            this.piezaEspecieId = especieId;

            if (!enfocar) {
                return;
            }

            this.$nextTick(() => {
                document.getElementById('pieza-tab-' + especieId)?.focus();
            });
        },

        moverPestana(delta) {
            const especies = this.especiesModal();

            if (especies.length === 0) {
                return;
            }

            const actual = especies.findIndex((especie) => especie.id === this.piezaEspecieId);
            const indice = ((actual === -1 ? 0 : actual) + delta + especies.length) % especies.length;

            this.seleccionarPestana(especies[indice].id, true);
        },

        extremoPestana(ultima) {
            const especies = this.especiesModal();

            if (especies.length === 0) {
                return;
            }

            this.seleccionarPestana(ultima ? especies[especies.length - 1].id : especies[0].id, true);
        },

        /** Las 3 recetas de tipo de una especie en el bioma seleccionado (sin inventar las ausentes). */
        recetasEspecie(especieId) {
            const familia = this.piezaFamilia();
            const nidoId = this.piezaNidoSeleccionado;

            return ['arma', 'armadura', 'amuleto'].map((tipo) => ({
                tipo: tipo,
                receta: familia === null
                    ? null
                    : (familia.recetas.find((receta) => receta.tipo_pieza === tipo
                        && receta.especie?.id === especieId
                        && String(receta.habitat?.id) === nidoId) || null),
            }));
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
                    motivo: null,
                    motivo_texto: null,
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

        /** Etiqueta y color del tipo de pieza: solo presentación del código recibido. */
        etiquetaTipoPieza(tipo) {
            const etiquetas = {
                arma: 'Arma',
                armadura: 'Armadura',
                amuleto: 'Amuleto',
            };

            return etiquetas[tipo] || (tipo || '');
        },

        claseTipoPieza(tipo) {
            const clases = {
                arma: 'border-primary bg-primary-soft text-primary-ink',
                armadura: 'border-info bg-info-soft text-info-ink',
                amuleto: 'border-warning bg-warning-soft text-warning-ink',
            };

            return clases[tipo] || 'border-line bg-surface-sunken text-ink-muted';
        },

        /** Píldoras de las estadísticas ya calculadas por el backend; se omiten los ceros. */
        statsPieza(variante) {
            if (!variante || !variante.stats) {
                return [];
            }

            const definiciones = [
                ['hp', 'HP', 'HP'],
                ['atk', 'Atk', 'Ataque'],
                ['def', 'Def', 'Defensa'],
                ['spAtk', 'SpA', 'Ataque especial'],
                ['spDef', 'SpD', 'Defensa especial'],
                ['speed', 'Vel', 'Velocidad'],
            ];

            return definiciones
                .filter(([clave]) => Number(variante.stats[clave]) > 0)
                .map(([clave, corta, larga]) => ({
                    clave: clave,
                    corta: corta,
                    larga: larga,
                    valor: variante.stats[clave],
                }));
        },

        motivoTexto(variante) {
            if (!variante || !variante.motivo_texto) {
                return '';
            }

            return variante.motivo_texto;
        },

        /** Stock inmediato devuelto por el servidor; los gates los recalcula el backend al refrescar. */
        aplicarSaldos(saldos) {
            this.recetas.forEach((receta) => {
                if (!Array.isArray(receta.ingredientes)) {
                    return;
                }

                receta.ingredientes.forEach((ingrediente) => {
                    if (Object.prototype.hasOwnProperty.call(saldos, ingrediente.clave)) {
                        ingrediente.disponible = Number(saldos[ingrediente.clave]);
                    }
                });
            });
        },

        async fabricar(receta) {
            if (!receta || !receta.puede_fabricar || this.fabricando !== null) {
                return;
            }

            this.fabricando = receta.clave;
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
                        receta: receta.clave,
                        cantidad: 1,
                    }),
                });

                if (!response.ok) {
                    const body = await response.json().catch(() => ({}));

                    throw new Error(body.message || 'No se pudo fabricar la receta.');
                }

                const data = await response.json().catch(() => ({}));
                const cantidad = data.cantidad || receta.resultado_cantidad;

                this.mensajeExito = 'Has fabricado ' + cantidad + ' × ' + receta.resultado_nombre + '.';

                if (data.saldos !== null && typeof data.saldos === 'object') {
                    this.aplicarSaldos(data.saldos);
                }

                // `puede_fabricar` y `motivo` solo los resuelve el backend: se refresca la
                // lista sin recargar la página ni cerrar el modal de piezas.
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
