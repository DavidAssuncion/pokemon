{{--
    Acciones de la navbar (Reclutamiento y Expediciones).

    Vive fuera del contenedor con `overflow-x-auto` para que el panel de
    expediciones no se recorte. El grupo es `relative`: ancla el panel en
    escritorio.
--}}
@php
    $claseBoton = 'flex h-9 w-9 items-center justify-center rounded-full border border-line transition-colors focus-ring';
    $claseInactivo = 'text-ink-muted hover:bg-surface-sunken hover:text-ink';
    $reclutamientoActivo = request()->is('reclutamiento*');
    $expedicionesActivo = request()->is('exploraciones*');
@endphp

<div class="relative flex items-center gap-1 shrink-0">
    <button
        type="button"
        data-reclutamiento-trigger
        @click="$store.reclutamientoNavbar.abrir()"
        aria-label="Abrir reclutamiento"
        title="Reclutamiento"
        class="{{ $claseBoton }} {{ $reclutamientoActivo ? 'bg-primary-soft text-primary-ink' : $claseInactivo }}"
    >
        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
    </button>

    <button
        type="button"
        data-expediciones-trigger
        @click="$store.expedicionesNavbar.abrirPanel()"
        :aria-expanded="$store.expedicionesNavbar.panelAbierto"
        aria-controls="panel-expediciones"
        aria-label="Abrir expediciones"
        title="Expediciones"
        class="{{ $claseBoton }} {{ $expedicionesActivo ? 'bg-primary-soft text-primary-ink' : $claseInactivo }}"
    >
        <i class="fa-solid fa-compass" aria-hidden="true"></i>
    </button>

    @include('layouts.partials.panel-expediciones')
</div>
