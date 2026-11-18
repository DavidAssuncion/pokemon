{{--
    Modal de confirmación de «Descartar todos».

    Es una confirmación destructiva: se apoya en `descarteTodosAbierto` y
    `descartarTodos()` del store `reclutamientoNavbar`.
--}}
<x-ui.modal
    id="navbar-descartar-todos"
    titulo="Descartar todos"
    abierto="$store.reclutamientoNavbar.descarteTodosAbierto"
    cerrar="$store.reclutamientoNavbar.descarteTodosAbierto = false"
    tamano="max-w-sm"
    capa="z-[60]"
>
    <div class="mb-4 text-center">
        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-danger-soft">
            <i class="fa-solid fa-triangle-exclamation text-danger-ink" aria-hidden="true"></i>
        </div>
        <p class="text-sm text-ink-muted">
            Se descartarán todos los Pokémon y generarán caramelos de experiencia. Esta acción no se puede deshacer.
        </p>
    </div>

    <div class="flex gap-3">
        <button
            type="button"
            @click="$store.reclutamientoNavbar.descarteTodosAbierto = false"
            class="flex-1 rounded-control bg-surface-sunken px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-line focus-ring"
        >
            Cancelar
        </button>
        <button
            type="button"
            @click="$store.reclutamientoNavbar.descartarTodos()"
            :disabled="$store.reclutamientoNavbar.accionEnCurso !== ''"
            class="flex-1 rounded-control bg-danger px-4 py-2.5 text-sm font-bold text-on-primary transition-colors hover:bg-danger/90 focus-ring disabled:cursor-not-allowed disabled:opacity-50"
        >
            Descartar
        </button>
    </div>
</x-ui.modal>
