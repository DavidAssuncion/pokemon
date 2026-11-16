{{--
    Modal de reclutamiento de la navbar.

    Reutiliza el grid compartido (`reclutamiento.partials._grid`) y todo el
    estado del store `reclutamientoNavbar`. El contenido se mantiene visible
    mientras se revalida: la caché no se limpia durante la carga.
--}}
<x-ui.modal
    id="navbar-reclutamiento"
    titulo="Reclutamiento"
    abierto="$store.reclutamientoNavbar.abierto"
    cerrar="$store.reclutamientoNavbar.cerrar()"
    tamano="max-w-5xl"
>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-ink-muted">
            Pokémons que sueñan con unirse a tus filas
        </p>
        <button
            type="button"
            x-show="$store.reclutamientoNavbar.items.length > 0"
            @click="$store.reclutamientoNavbar.solicitarDescarteTodos()"
            class="inline-flex shrink-0 items-center gap-2 self-start rounded-control bg-danger px-4 py-2 text-sm font-medium text-on-primary transition-colors hover:bg-danger/90 focus-ring"
        >
            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
            Descartar todos
        </button>
    </div>

    <div
        x-show="$store.reclutamientoNavbar.error"
        role="alert"
        class="mb-4 flex items-start gap-2 rounded-control border border-danger bg-danger-soft px-3 py-2 text-sm text-danger-ink"
    >
        <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0" aria-hidden="true"></i>
        <p class="min-w-0 flex-1 break-words" x-text="$store.reclutamientoNavbar.error"></p>
        <button
            type="button"
            @click="$store.reclutamientoNavbar.refrescar()"
            :disabled="$store.reclutamientoNavbar.cargando"
            class="shrink-0 font-medium underline underline-offset-2 transition-opacity hover:opacity-80 focus-ring disabled:cursor-not-allowed disabled:opacity-50"
        >
            Reintentar
        </button>
    </div>

    <p
        x-show="$store.reclutamientoNavbar.mensaje"
        role="status"
        aria-live="polite"
        class="mb-4 rounded-control border border-success bg-success-soft px-3 py-2 text-sm text-success-ink"
        x-text="$store.reclutamientoNavbar.mensaje"
    ></p>

    <div
        x-show="$store.reclutamientoNavbar.cargando && $store.reclutamientoNavbar.items.length === 0"
        aria-busy="true"
        class="animate-pulse space-y-3"
    >
        <div class="h-40 rounded-card bg-surface-sunken"></div>
        <div class="h-40 rounded-card bg-surface-sunken"></div>
    </div>

    @include('reclutamiento.partials._grid')
</x-ui.modal>
