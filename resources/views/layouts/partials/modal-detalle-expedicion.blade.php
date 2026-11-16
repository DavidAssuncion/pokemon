{{--
    Modal de detalle de una expedición.

    El contenido es el fragmento HTML servido por
    `/exploraciones/{id}/panel/detalle` y se inyecta con `x-html`. Es HTML de
    confianza, renderizado en Blade y sin JavaScript.
--}}
<x-ui.modal
    id="navbar-detalle-expedicion"
    titulo="Detalle de la expedición"
    abierto="$store.expedicionesNavbar.detalleAbierto"
    cerrar="$store.expedicionesNavbar.cerrarDetalle()"
    tamano="max-w-3xl"
>
    <div
        x-show="$store.expedicionesNavbar.detalleError"
        role="alert"
        class="flex items-start gap-2 rounded-control border border-danger bg-danger-soft px-3 py-2 text-sm text-danger-ink"
    >
        <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0" aria-hidden="true"></i>
        <p class="min-w-0 flex-1 break-words" x-text="$store.expedicionesNavbar.detalleError"></p>
    </div>

    <div
        x-show="$store.expedicionesNavbar.detalleCargando && !$store.expedicionesNavbar.detalleHtml"
        aria-busy="true"
        class="animate-pulse space-y-3"
    >
        <div class="h-32 rounded-card bg-surface-sunken"></div>
        <div class="h-16 rounded-card bg-surface-sunken"></div>
    </div>

    <div
        x-show="$store.expedicionesNavbar.detalleHtml"
        x-html="$store.expedicionesNavbar.detalleHtml"
    ></div>
</x-ui.modal>
