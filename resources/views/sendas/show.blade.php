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
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1.5 text-sm font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300" title="Caramelos de este tipo">
                <img :src="'/images/candy_type/' + arbol.tipo.slug + '.webp'" :alt="'Caramelos de ' + arbol.tipo.nombre" loading="lazy" decoding="async" class="h-5 w-5 object-contain" onerror="this.style.display='none'">
                <span x-text="arbol.caramelos"></span>
            </span>
        </div>
    </div>

    <template x-if="feedback.mensaje">
        <div class="mb-4 rounded-lg px-3 py-2 text-xs" role="status" :class="feedback.tipo === 'error' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'" x-text="feedback.mensaje"></div>
    </template>

    <div class="grid grid-cols-12 gap-4">
        <!-- Barra vertical 1/12 -->
        <aside class="col-span-12 lg:col-span-1">
            <div class="sticky top-24 rounded-2xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="mb-2 text-center text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nivel</p>
                <div class="mx-auto h-[24rem] w-7 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="relative h-full w-full">
                        <div class="absolute bottom-0 left-0 right-0 rounded-full transition-all duration-500" :style="'height: ' + arbol.progreso_vertical + '%; background-color: ' + arbol.tipo.color"></div>
                        <div class="absolute inset-0 flex flex-col-reverse items-center justify-between py-1 text-[9px] font-bold text-gray-500 dark:text-gray-400">
                            <template x-for="nivel in [1, 2, 3, 4, 5]" :key="'nivel-barra-' + nivel">
                                <span :class="arbol.nivel >= nivel ? 'text-white drop-shadow' : ''" x-text="nivel"></span>
                            </template>
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-center text-xs font-bold text-gray-900 dark:text-white"><span x-text="arbol.nivel"></span>/5</p>
                <p class="text-center text-[10px] font-semibold" :style="'color: ' + arbol.tipo.color" x-text="arbol.porcentaje + '%'"></p>
                <p class="mt-2 text-center text-[9px] leading-tight text-gray-400 dark:text-gray-500" x-text="arbol.exp_siguiente === null ? 'Nivel máximo' : (arbol.bloqueado_por_cuenta ? ('Cap de cuenta: ' + arbol.cap) : (arbol.exp_faltante + ' exp para el siguiente'))"></p>
            </div>
        </aside>

        <!-- Contenido 11/12 -->
        <div class="col-span-12 lg:col-span-11">
            <div class="mb-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">Progreso de la Senda</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            <span x-text="arbol.exp"></span> exp
                            <template x-if="arbol.exp_siguiente !== null && !arbol.bloqueado_por_cuenta">
                                <span> / <span x-text="arbol.exp_siguiente"></span> exp para nivel <span x-text="arbol.nivel + 1"></span></span>
                            </template>
                            <template x-if="arbol.bloqueado_por_cuenta">
                                <span> · cap alcanzado en nivel <span x-text="arbol.cap"></span></span>
                            </template>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-bold" :style="'color: ' + arbol.tipo.color" x-text="arbol.porcentaje + '%'"></p>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500">Cap de cuenta: nivel <span x-text="arbol.cap"></span></p>
                    </div>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700" role="progressbar" :aria-valuenow="arbol.porcentaje" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-full rounded-full transition-all duration-500" :style="'width: ' + arbol.porcentaje + '%; background-color: ' + arbol.tipo.color"></div>
                </div>
                <template x-if="arbol.bloqueado_por_cuenta">
                    <p class="mt-2 text-[10px] font-semibold text-amber-600 dark:text-amber-400">Has alcanzado el cap de tu nivel de cuenta. Sube de nivel para seguir avanzando.</p>
                </template>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <template x-for="categoria in ['fisico', 'especial', 'estado']" :key="'senda-rama-' + categoria">
                    <section class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-3 flex items-center justify-between gap-2 border-b border-gray-100 pb-2 dark:border-gray-700">
                            <div class="flex items-center gap-2">
                                <img :src="categoriaIcono(categoria)" :alt="etiquetaCategoria(categoria)" loading="lazy" decoding="async" class="h-6 w-6 object-contain">
                                <h2 class="text-xs font-bold uppercase tracking-wide text-gray-600 dark:text-gray-300" x-text="etiquetaCategoria(categoria)"></h2>
                            </div>
                            <span class="text-[10px] font-semibold text-gray-400 dark:text-gray-500" x-text="arbol.ramas[categoria].length + ' movimientos'"></span>
                        </div>

                        <div class="space-y-2">
                            <template x-for="nodo in arbol.ramas[categoria]" :key="'senda-nodo-' + nodo.slug">
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
                                            :disabled="!nodo.puede_desbloquear || saving"
                                            class="mt-2 w-full rounded-md border border-amber-300 px-2 py-1 text-[11px] font-bold text-amber-700 transition-colors hover:bg-amber-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400 dark:border-amber-700 dark:text-amber-300 dark:hover:bg-amber-900/20 dark:disabled:border-gray-700 dark:disabled:text-gray-500"
                                            x-text="textoBoton(nodo)"
                                        ></button>
                                    </template>
                                </article>
                            </template>
                        </div>

                        <p x-show="arbol.ramas[categoria].length === 0" class="py-4 text-center text-xs text-gray-400 dark:text-gray-500">Sin movimientos en esta categoría.</p>
                    </section>
                </template>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function sendasApp(arbolInicial) {
    return {
        arbol: arbolInicial,
        saving: false,
        feedback: { tipo: '', mensaje: '' },

        etiquetaCategoria(categoria) {
            return { fisico: 'Físico', especial: 'Especial', estado: 'Estado' }[categoria] || categoria;
        },

        categoriaIcono(categoria) {
            return '/images/moves/' + categoria + '.png';
        },

        textoBoton(nodo) {
            if (!nodo.disponible) {
                if (this.arbol.bloqueado_por_cuenta && this.arbol.nivel_por_exp >= nodo.nivel_requerido) {
                    return 'Cap de cuenta: Nv ' + this.arbol.cap;
                }
                return 'Nivel de Senda ' + nodo.nivel_requerido;
            }
            if (this.arbol.caramelos < nodo.coste) {
                return 'Faltan ' + (nodo.coste - this.arbol.caramelos) + ' caramelos';
            }
            return 'Desbloquear · ' + nodo.coste + ' caramelos';
        },

        async desbloquear(nodo) {
            if (!nodo || this.saving) {
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
