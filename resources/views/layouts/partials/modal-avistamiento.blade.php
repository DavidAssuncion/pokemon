{{--
    Modal sencillo del avistamiento activo.

    Se abre desde el icono de prismáticos de la navbar y se oculta con la X,
    con Escape o al pulsar fuera. Todos los datos (hábitat, familia, factor,
    niveles, especies y visto) llegan ya resueltos de
    `/api/avistamientos/vigente`; aquí solo se presenta.
--}}
<div
    x-data
    x-init="$store.avistamientosNavbar.iniciar()"
    x-show="$store.avistamientosNavbar.abierto"
    x-cloak
    class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:items-center"
    @click.self="$store.avistamientosNavbar.cerrar()"
>
    <div
        data-avistamientos-panel
        role="dialog"
        aria-modal="true"
        aria-labelledby="avistamiento-titulo"
        class="w-full max-w-lg rounded-panel border border-line bg-surface-raised shadow-modal"
    >
        <header class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
            <h2 id="avistamiento-titulo" class="flex items-center gap-2 text-base font-semibold text-ink">
                <i class="fa-solid fa-binoculars text-primary-ink" aria-hidden="true"></i>
                Avistamiento
            </h2>
            <button
                type="button"
                @click="$store.avistamientosNavbar.cerrar()"
                aria-label="Cerrar avistamiento"
                title="Cerrar"
                class="flex h-8 w-8 items-center justify-center rounded-control text-ink-muted transition-colors hover:bg-surface-sunken hover:text-ink focus-ring"
            >
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <!-- Cargando -->
        <div x-show="$store.avistamientosNavbar.cargando" aria-busy="true" class="animate-pulse space-y-3 px-4 py-4">
            <div class="h-5 w-2/3 rounded bg-surface-sunken"></div>
            <div class="h-16 rounded-control bg-surface-sunken"></div>
            <div class="h-10 rounded-control bg-surface-sunken"></div>
        </div>

        <!-- Error -->
        <div
            x-show="!$store.avistamientosNavbar.cargando && $store.avistamientosNavbar.error"
            role="alert"
            class="mx-4 mt-4 flex items-start gap-2 rounded-control border border-danger bg-danger-soft px-3 py-2 text-xs text-danger-ink"
        >
            <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0" aria-hidden="true"></i>
            <p class="min-w-0 flex-1 break-words" x-text="$store.avistamientosNavbar.error"></p>
            <button
                type="button"
                @click="$store.avistamientosNavbar.refrescar()"
                class="shrink-0 font-semibold underline underline-offset-2 focus-ring"
            >
                Reintentar
            </button>
        </div>

        <!-- Sin evento activo -->
        <div
            x-show="!$store.avistamientosNavbar.cargando && !$store.avistamientosNavbar.error && $store.avistamientosNavbar.datos && !$store.avistamientosNavbar.datos.activo"
            role="status"
            class="px-4 py-8 text-center"
        >
            <i class="fa-solid fa-binoculars mx-auto mb-2 block text-2xl text-ink-subtle" aria-hidden="true"></i>
            <p class="text-sm text-ink-muted">No hay ningún avistamiento activo</p>
            <p class="mt-1 text-xs text-ink-subtle">El próximo evento aparecerá aquí automáticamente.</p>
        </div>

        <!-- Evento activo -->
        <template x-if="!$store.avistamientosNavbar.cargando && !$store.avistamientosNavbar.error && $store.avistamientosNavbar.datos?.activo">
            <div class="px-4 py-4">
                <p class="text-2xs font-bold uppercase tracking-wide text-ink-subtle">Evento temporal · 3 días</p>

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-full bg-primary-soft px-2.5 py-1 text-xs font-bold text-primary-ink">
                    <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                    ×<span x-text="$store.avistamientosNavbar.datos.factor"></span> aparición
                </span>
                <span class="text-xs text-ink-muted" x-text="$store.avistamientosNavbar.datos.restante"></span>
            </div>

            <p class="mt-3 text-sm text-ink">
                <span class="font-semibold" x-text="$store.avistamientosNavbar.datos.familia || 'Familia desconocida'"></span>
                <span class="text-ink-muted">en</span>
                <a
                    :href="$store.avistamientosNavbar.datos.habitat?.url || '/habitats'"
                    class="font-semibold text-primary-ink hover:underline focus-ring"
                    x-text="$store.avistamientosNavbar.datos.habitat?.nombre || 'el hábitat'"
                ></a>
            </p>

            <p
                x-show="$store.avistamientosNavbar.datos.sin_efecto"
                class="mt-3 rounded-control border border-warning bg-warning-soft px-3 py-2 text-xs text-warning-ink"
            >
                Este avistamiento ya no tiene efecto en el hábitat, pero la ventana sigue activa.
            </p>

            <div x-show="!$store.avistamientosNavbar.datos.sin_efecto" class="mt-4 space-y-4">
                <template x-for="nivel in ($store.avistamientosNavbar.datos.niveles || [])" :key="nivel.nivel">
                    <section>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-subtle">
                            Nivel <span x-text="nivel.nivel"></span>
                        </h3>
                        <ul class="flex flex-wrap gap-2">
                            <template x-for="especie in nivel.especies" :key="especie.id">
                                <li class="flex w-24 flex-col items-center gap-1 rounded-control border border-line bg-surface-sunken px-2 py-2 text-center">
                                    <img
                                        :src="especie.icono"
                                        :alt="especie.nombre"
                                        width="48"
                                        height="48"
                                        loading="lazy"
                                        decoding="async"
                                        class="h-12 w-12 object-contain"
                                    >
                                    <span class="w-full truncate text-2xs font-medium text-ink" x-text="especie.nombre"></span>
                                    <span
                                        class="rounded-full px-1.5 py-0.5 text-2xs font-bold uppercase"
                                        :class="especie.visto ? 'bg-success-soft text-success-ink' : 'bg-surface-raised text-ink-subtle border border-line'"
                                        x-text="especie.visto ? 'Visto' : 'No visto'"
                                    ></span>
                                </li>
                            </template>
                        </ul>
                    </section>
                </template>
            </div>

            <a
                :href="$store.avistamientosNavbar.datos.habitat?.url || '/habitats'"
                class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-control bg-primary px-3 py-2 text-sm font-semibold text-on-primary transition-colors hover:bg-primary-strong focus-ring"
            >
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                Ver hábitat
            </a>
            </div>
        </template>
    </div>
</div>
