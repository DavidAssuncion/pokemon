{{--
    Combobox de selección única con buscador (filtros de la Pokédex).

    Props:
    - name: identificador del filtro ('ability' | 'move'); viaja en el evento
    - label: texto visible del filtro y nombre accesible del trigger
    - options: colección de App\Support\Pokedex\OpcionFiltro ({ id, name })
    - placeholder: texto del buscador
    - ancho: clases de anchura (w-56 por defecto)
    - ayuda: texto explicativo; se expone en title y aria-description

    El componente solo presenta: no calcula el catálogo ni el orden (los recibe del
    backend) y no conoce el estado de la página. Al elegir o limpiar emite el evento
    `combobox-seleccionado` con { name, id, label } para que la vista decida.

    Las comillas del atributo x-data son dobles a propósito: @js() serializa los
    strings con comillas simples ('ability') y envuelve los arrays en
    JSON.parse('…') con los caracteres peligrosos escapados (JSON_HEX_*), de modo
    que nunca emite comillas dobles crudas que rompan el atributo.
--}}
@props([
    'name',
    'label',
    'options' => [],
    'placeholder' => 'Buscar…',
    'ancho' => 'w-56',
    'ayuda' => null,
])

@php
    $baseId = 'combobox-' . \Illuminate\Support\Str::slug((string) $name);
    $panelId = $baseId . '-panel';
    $listboxId = $baseId . '-listbox';
    $buscadorId = $baseId . '-buscador';
    $labelMinusculas = \Illuminate\Support\Str::lower((string) $label);
    $tieneAyuda = $ayuda !== null && $ayuda !== '';
@endphp

<div
    id="{{ $baseId }}"
    class="relative"
    x-data="comboboxFiltro(@js($options), @js($name), @js($label))"
    @click.outside="cerrar(false)"
    @keydown.escape.stop="cerrar(true)"
>
    <button
        type="button"
        x-ref="trigger"
        @click="alternar()"
        class="{{ $ancho }} flex items-center justify-between gap-2 rounded-control border border-line bg-surface px-4 py-2 pr-8 text-left text-sm transition-colors hover:bg-surface-sunken focus-ring"
        aria-haspopup="listbox"
        aria-controls="{{ $panelId }}"
        aria-label="{{ $label }}"
        :aria-expanded="abierto"
        @if($tieneAyuda)
            :title="@js($ayuda)"
            aria-description="{{ $ayuda }}"
        @endif
    >
        <span
            class="truncate"
            :class="seleccion ? 'font-medium text-ink' : 'text-ink-muted'"
            x-text="seleccion ? seleccion.name : @js($label)"
        ></span>
        <svg
            class="h-4 w-4 shrink-0 text-ink-subtle"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
            x-show="!seleccion"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <button
        type="button"
        x-show="seleccion"
        x-cloak
        @click.stop="limpiar()"
        class="absolute right-1.5 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-control text-xs font-bold text-danger-ink transition-colors hover:text-danger focus-ring"
        aria-label="Quitar filtro de {{ $labelMinusculas }}"
        title="Quitar filtro de {{ $labelMinusculas }}"
    >✕</button>

    <div
        id="{{ $panelId }}"
        x-show="abierto"
        x-cloak
        x-transition
        x-ref="panel"
        class="absolute left-0 top-full z-40 mt-1 {{ $ancho }} rounded-card border border-line bg-surface-raised p-3 shadow-modal"
    >
        <input
            id="{{ $buscadorId }}"
            type="search"
            x-ref="buscador"
            x-model="consulta"
            @input="resaltado = 0"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="{{ $listboxId }}"
            aria-label="Buscar {{ $labelMinusculas }}"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            :aria-expanded="abierto"
            :aria-activedescendant="opcionActivaId"
            class="w-full rounded-control border border-line bg-surface px-3 py-1.5 text-sm text-ink placeholder:text-ink-subtle focus-ring focus:border-primary"
            @keydown.down.prevent="mover(1)"
            @keydown.up.prevent="mover(-1)"
            @keydown.enter.prevent="confirmar()"
            @keydown.home.prevent="resaltado = 0"
            @keydown.end.prevent="resaltado = Math.max(filtradas.length - 1, 0)"
            @keydown.tab="cerrar(false)"
        >

        <ul
            id="{{ $listboxId }}"
            role="listbox"
            aria-label="{{ $label }}"
            class="mt-2 max-h-56 overflow-y-auto"
        >
            <template x-for="(opcion, indice) in filtradas" :key="opcion.id">
                <li
                    role="option"
                    :id="'{{ $baseId }}-op-' + opcion.id"
                    :aria-selected="seleccion && seleccion.id === opcion.id ? 'true' : 'false'"
                    :class="indice === resaltado ? 'bg-surface-sunken font-medium text-primary-ink' : 'text-ink-muted'"
                    class="cursor-pointer rounded-control px-3 py-1.5 text-sm transition-colors"
                    @click="elegir(opcion)"
                    @mousemove="resaltado = indice"
                    x-text="opcion.name"
                ></li>
            </template>
        </ul>

        <p x-show="filtradas.length === 0" role="status" class="px-3 py-2 text-sm text-ink-subtle">
            Sin resultados
        </p>
    </div>
</div>
