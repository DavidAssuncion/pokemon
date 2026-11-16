{{--
    Panel de expediciones de la navbar.

    Presenta la colección canónica de `$store.expedicionesNavbar.items` sin
    ordenar, filtrar ni calcular: progreso, estado, tiempo y permisos vienen
    del backend. Las acciones (`archivar`, `detalle`) se delegan al listener
    global del store mediante `data-expedicion-accion`.

    Foco: al abrirse el panel recibe el foco para que sea navegable con
    teclado. `Escape` lo cierra solo si no hay un modal por encima (historial,
    detalle o reclutamiento).
--}}
<div
    id="panel-expediciones"
    x-ref="panelExpediciones"
    x-show="$store.expedicionesNavbar.panelAbierto"
    x-effect="if ($store.expedicionesNavbar.panelAbierto) { $nextTick(() => $refs.panelExpediciones?.focus()) }"
    x-cloak
    data-expediciones-panel
    role="region"
    aria-label="Expediciones"
    tabindex="-1"
    class="fixed sm:absolute inset-x-2 sm:inset-x-auto sm:right-0 top-16 sm:top-full sm:mt-2 z-40 w-auto sm:w-[44rem] max-h-[70vh] overflow-y-auto rounded-panel border border-line bg-surface-raised shadow-modal"
    @keydown.escape.window="if ($store.expedicionesNavbar.panelAbierto && !$store.expedicionesNavbar.historialAbierto && !$store.expedicionesNavbar.detalleAbierto && !$store.reclutamientoNavbar.abierto) { $store.expedicionesNavbar.cerrarPanel() }"
>
    <header class="sticky top-0 z-10 flex items-center justify-between gap-2 border-b border-line bg-surface-raised px-4 py-3">
        <h2 class="text-base font-semibold text-ink">Expediciones</h2>
        <div class="flex items-center gap-1">
            <button
                type="button"
                @click="$store.expedicionesNavbar.refrescar()"
                :disabled="$store.expedicionesNavbar.cargando"
                aria-label="Actualizar expediciones"
                title="Actualizar"
                class="flex h-8 w-8 items-center justify-center rounded-control text-ink-muted transition-colors hover:bg-surface-sunken hover:text-ink focus-ring disabled:cursor-not-allowed disabled:opacity-50"
            >
                <i class="fa-solid" :class="$store.expedicionesNavbar.cargando ? 'fa-spinner fa-spin' : 'fa-rotate'" aria-hidden="true"></i>
            </button>
            <button
                type="button"
                @click="$store.expedicionesNavbar.cerrarPanel()"
                aria-label="Cerrar panel de expediciones"
                title="Cerrar"
                class="flex h-8 w-8 items-center justify-center rounded-control text-ink-muted transition-colors hover:bg-surface-sunken hover:text-ink focus-ring"
            >
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
    </header>

    <p
        role="status"
        aria-live="polite"
        x-show="$store.expedicionesNavbar.mensaje"
        x-text="$store.expedicionesNavbar.mensaje"
        class="px-4 pt-3 text-xs font-medium text-success-ink"
    ></p>

    <div x-show="$store.expedicionesNavbar.error" role="alert" class="mx-4 mt-3 flex items-start gap-2 rounded-control border border-danger bg-danger-soft px-3 py-2 text-xs text-danger-ink">
        <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0" aria-hidden="true"></i>
        <p class="min-w-0 flex-1 break-words" x-text="$store.expedicionesNavbar.error"></p>
        <button
            type="button"
            @click="$store.expedicionesNavbar.refrescar()"
            class="shrink-0 font-semibold underline underline-offset-2 focus-ring"
        >
            Reintentar
        </button>
    </div>

    <!-- Cargando sin caché -->
    <div
        x-show="$store.expedicionesNavbar.cargando && !$store.expedicionesNavbar.items.length"
        aria-busy="true"
        class="animate-pulse space-y-3 px-4 py-4"
    >
        <div class="h-10 rounded-control bg-surface-sunken"></div>
        <div class="h-10 rounded-control bg-surface-sunken"></div>
        <div class="h-10 rounded-control bg-surface-sunken"></div>
    </div>

    <!-- Vacío -->
    <div
        x-show="!$store.expedicionesNavbar.cargando && !$store.expedicionesNavbar.error && !$store.expedicionesNavbar.items.length"
        role="status"
        class="px-4 py-8 text-center"
    >
        <i class="fa-solid fa-compass mx-auto mb-2 block text-2xl text-ink-subtle" aria-hidden="true"></i>
        <p class="text-sm text-ink-muted">No hay expediciones activas</p>
        <p class="mt-1 text-xs text-ink-subtle">
            Envía a un explorador desde un
            <a href="/habitats" class="text-primary-ink hover:underline focus-ring">hábitat</a>
        </p>
    </div>

    <!-- Lista -->
    <ul x-show="$store.expedicionesNavbar.items.length > 0" class="divide-y divide-line">
        <template x-for="item in $store.expedicionesNavbar.items" :key="item.id">
            <li class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid sm:grid-cols-[minmax(0,1fr)_9rem_7rem_auto_auto] sm:items-center sm:gap-3">
                <!-- Explorador / Pokémon / hábitat / nivel + estado -->
                <div class="flex min-w-0 items-center gap-3">
                    <img
                        :src="item.icono"
                        alt=""
                        width="40"
                        height="40"
                        loading="lazy"
                        decoding="async"
                        class="h-10 w-10 shrink-0 object-contain"
                    >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm font-semibold text-ink" x-text="item.explorador"></p>
                            <span
                                class="shrink-0 rounded-full px-2 py-0.5 text-2xs font-bold uppercase"
                                :class="item.estado_clase"
                                x-text="item.estado_label"
                            ></span>
                        </div>
                        <p class="truncate text-xs text-ink-muted">
                            <span x-text="item.pokemon"></span>
                            <span aria-hidden="true">·</span>
                            <span x-text="item.habitat"></span>
                            <span aria-hidden="true">·</span>
                            Nv <span x-text="item.nivel"></span>
                        </p>
                    </div>
                </div>

                <!-- Progreso -->
                <div>
                    <div class="mb-1 flex items-center justify-between text-2xs text-ink-muted">
                        <span>Progreso</span>
                        <span class="font-medium text-ink" x-text="item.progreso + '%'"></span>
                    </div>
                    <div
                        class="h-2 overflow-hidden rounded-full bg-surface-sunken"
                        role="progressbar"
                        aria-label="Progreso de la expedición"
                        :aria-valuenow="item.progreso"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div class="h-2 rounded-full bg-primary transition-all" :style="'width: ' + item.progreso + '%'"></div>
                    </div>
                </div>

                <!-- Tiempo restante -->
                <span class="text-xs font-medium text-ink" x-text="item.restante_label || '—'"></span>

                <!-- Archivar -->
                <button
                    type="button"
                    x-show="item.puede_archivar"
                    data-expedicion-accion="archivar"
                    :data-expedicion-id="item.id"
                    :aria-label="'Archivar expedición de ' + item.explorador"
                    title="Archivar"
                    class="flex h-8 w-8 items-center justify-center justify-self-start rounded-control text-ink-muted transition-colors hover:bg-surface-sunken hover:text-ink focus-ring sm:justify-self-center"
                >
                    <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                </button>

                <!-- Detalle -->
                <button
                    type="button"
                    data-expedicion-accion="detalle"
                    :data-expedicion-id="item.id"
                    :aria-label="'Ver detalle de la expedición de ' + item.explorador"
                    title="Ver detalle"
                    class="flex h-8 w-8 items-center justify-center justify-self-start rounded-control text-ink-muted transition-colors hover:bg-surface-sunken hover:text-ink focus-ring sm:justify-self-center"
                >
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </button>
            </li>
        </template>
    </ul>

    <footer class="flex justify-end border-t border-line px-4 py-3">
        <button
            type="button"
            @click="$store.expedicionesNavbar.abrirHistorial()"
            class="inline-flex items-center gap-2 rounded-control px-3 py-1.5 text-sm font-medium text-primary-ink transition-colors hover:bg-primary-soft focus-ring"
        >
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            Ver historial
        </button>
    </footer>
</div>
