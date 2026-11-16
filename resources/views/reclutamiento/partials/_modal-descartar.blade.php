{{--
    Modal de descarte parcial de un reclutable.

    Todo el estado vive en el store `reclutamientoNavbar` (`descarte*`), que
    también usan el grid y el modal de «Descartar todos».
--}}
<x-ui.modal
    id="navbar-descartar-reclutable"
    titulo="Descartar Pokémon"
    abierto="$store.reclutamientoNavbar.descarteAbierto && $store.reclutamientoNavbar.descarte"
    cerrar="$store.reclutamientoNavbar.descarteAbierto = false"
    tamano="max-w-sm"
    capa="z-[60]"
>
    <h3 class="mb-4 text-lg font-bold text-ink">
        Descartar <span x-text="$store.reclutamientoNavbar.descarte?.nombre"></span>
    </h3>

    <div class="mb-4">
        <label for="navbar-discard-quantity" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-ink-muted">
            Cantidad
        </label>
        <input
            id="navbar-discard-quantity"
            type="number"
            min="1"
            :max="$store.reclutamientoNavbar.descarte?.cantidad"
            x-model.number="$store.reclutamientoNavbar.descarteCantidad"
            class="w-full rounded-control border border-line bg-surface-sunken px-3 py-2 text-sm text-ink transition-colors focus-ring"
        >
    </div>

    <div class="mb-4 grid grid-cols-4 gap-2">
        <button
            type="button"
            @click="$store.reclutamientoNavbar.setPorcentaje(25)"
            class="rounded-control bg-surface-sunken px-2 py-1.5 text-xs font-medium text-ink transition-colors hover:bg-line focus-ring"
        >
            25%
        </button>
        <button
            type="button"
            @click="$store.reclutamientoNavbar.setPorcentaje(50)"
            class="rounded-control bg-surface-sunken px-2 py-1.5 text-xs font-medium text-ink transition-colors hover:bg-line focus-ring"
        >
            50%
        </button>
        <button
            type="button"
            @click="$store.reclutamientoNavbar.setPorcentaje(75)"
            class="rounded-control bg-surface-sunken px-2 py-1.5 text-xs font-medium text-ink transition-colors hover:bg-line focus-ring"
        >
            75%
        </button>
        <button
            type="button"
            @click="$store.reclutamientoNavbar.setPorcentaje(100)"
            class="rounded-control bg-surface-sunken px-2 py-1.5 text-xs font-medium text-ink transition-colors hover:bg-line focus-ring"
        >
            Todos
        </button>
    </div>

    <p class="mb-6 text-sm text-ink-muted">
        Se convertirán en caramelos de la familia
        <span class="font-medium text-ink" x-text="$store.reclutamientoNavbar.descarte?.nombre"></span>
    </p>

    <div class="flex gap-3">
        <button
            type="button"
            @click="$store.reclutamientoNavbar.descarteAbierto = false"
            class="flex-1 rounded-control bg-surface-sunken px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-line focus-ring"
        >
            Cancelar
        </button>
        <button
            type="button"
            @click="$store.reclutamientoNavbar.confirmarDescarte()"
            :disabled="$store.reclutamientoNavbar.accionEnCurso"
            class="flex-1 rounded-control bg-danger px-4 py-2.5 text-sm font-bold text-on-primary transition-colors hover:bg-danger/90 focus-ring disabled:cursor-not-allowed disabled:opacity-50"
        >
            Confirmar
        </button>
    </div>
</x-ui.modal>
