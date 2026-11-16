{{--
    Modal de historial de expediciones.

    El contenido es un fragmento HTML servido por el backend
    (`/exploraciones/panel/contenido?incluir_archivadas=1`) y se inyecta con
    `x-html`. Es HTML de confianza, renderizado en Blade y sin JavaScript.
--}}
<x-ui.modal
    id="navbar-historial-expediciones"
    titulo="Historial de expediciones"
    abierto="$store.expedicionesNavbar.historialAbierto"
    cerrar="$store.expedicionesNavbar.cerrarHistorial()"
    tamano="max-w-4xl"
>
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm text-ink-muted">Activas, resultados por revisar y archivadas.</p>
        <button
            type="button"
            @click="$store.expedicionesNavbar.refrescarHistorial()"
            :disabled="$store.expedicionesNavbar.historialCargando"
            class="inline-flex shrink-0 items-center gap-2 rounded-control px-3 py-1.5 text-sm font-medium text-ink transition-colors hover:bg-surface-sunken focus-ring disabled:cursor-not-allowed disabled:opacity-50"
        >
            <i class="fa-solid" :class="$store.expedicionesNavbar.historialCargando ? 'fa-spinner fa-spin' : 'fa-rotate'" aria-hidden="true"></i>
            Actualizar
        </button>
    </div>

    <div
        x-show="$store.expedicionesNavbar.historialError"
        role="alert"
        class="mt-3 flex items-start gap-2 rounded-control border border-danger bg-danger-soft px-3 py-2 text-sm text-danger-ink"
    >
        <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0" aria-hidden="true"></i>
        <p class="min-w-0 flex-1 break-words" x-text="$store.expedicionesNavbar.historialError"></p>
    </div>

    <div
        x-show="$store.expedicionesNavbar.historialCargando && !$store.expedicionesNavbar.historialHtml"
        aria-busy="true"
        class="mt-3 animate-pulse space-y-3"
    >
        <div class="h-16 rounded-card bg-surface-sunken"></div>
        <div class="h-16 rounded-card bg-surface-sunken"></div>
        <div class="h-16 rounded-card bg-surface-sunken"></div>
    </div>

    <p
        x-show="!$store.expedicionesNavbar.historialCargando && !$store.expedicionesNavbar.historialError && !$store.expedicionesNavbar.historialHtml"
        role="status"
        class="mt-3 text-sm text-ink-muted"
    >
        No hay expediciones en el historial.
    </p>

    <div
        x-show="$store.expedicionesNavbar.historialHtml"
        x-html="$store.expedicionesNavbar.historialHtml"
        class="mt-3"
    ></div>
</x-ui.modal>
