{{--
    Modal genérico sobre Alpine.

    Props:
    - id: identificador único; se usa en `aria-labelledby` como "{id}-title"
    - titulo: texto de la cabecera
    - abierto: expresión Alpine que determina la visibilidad (p. ej. "$store.x.abierto")
    - cerrar: expresión Alpine para cerrar (p. ej. "$store.x.cerrar()")
    - tamano: clase de ancho máximo del contenedor (por defecto max-w-lg)
    - capa: clase z-index de la raíz (por defecto z-50; las confirmaciones
      anidadas usan z-[60])

    Slot:
    - default: contenido del modal

    El componente no trae estado propio: toda la visibilidad la decide el
    consumidor. El scrim y el botón de cierre ejecutan la expresión `cerrar`.

    Accesibilidad y pila: el modal se registra en `$store.modales` al abrirse
    (foco al contenedor) y se desregistra al cerrarse (el foco vuelve al
    elemento activo previo cuando se cierra el último de la pila). `Escape`
    solo cierra el modal superior y `Tab` queda atrapado dentro del diálogo.
--}}
@props([
    'id',
    'titulo',
    'abierto',
    'cerrar',
    'tamano' => 'max-w-lg',
    'capa' => 'z-50',
])

<div
    x-data
    x-show="{{ $abierto }}"
    x-init="$watch('{{ $abierto }}', (abierto) => abierto ? $store.modales.abrir('{{ $id }}', $refs.dialogo) : $store.modales.cerrar('{{ $id }}'))"
    x-cloak
    class="fixed inset-0 {{ $capa }} flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $id }}-title"
    @keydown.escape.window="if ($store.modales.esTope('{{ $id }}')) { {{ $cerrar }} }"
    @keydown.tab="trampaDeFoco($event, $refs.dialogo)"
>
    <div class="absolute inset-0 scrim" @click="{{ $cerrar }}" aria-hidden="true"></div>

    <div
        x-ref="dialogo"
        tabindex="-1"
        class="relative w-full {{ $tamano }} max-h-[85vh] overflow-y-auto rounded-panel bg-surface-raised shadow-modal"
    >
        <header class="flex items-start justify-between gap-3 border-b border-line px-4 py-3">
            <h2 id="{{ $id }}-title" class="text-lg font-semibold text-ink">{{ $titulo }}</h2>
            <button
                type="button"
                @click="{{ $cerrar }}"
                aria-label="Cerrar"
                class="shrink-0 rounded-control p-1.5 text-ink-muted transition-colors hover:bg-surface-sunken hover:text-ink focus-ring"
            >
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <div class="px-4 py-4">
            {{ $slot }}
        </div>
    </div>
</div>
