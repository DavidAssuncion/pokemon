{{--
    Grid compartido de reclutables.

    Consume el store `reclutamientoNavbar`, de modo que la página y el modal de
    la navbar muestran siempre la misma colección. No incluye cabecera ni el
    botón «Descartar todos»: los pone el contenedor.

    Los datos (nombre, icono, cantidad y orden) llegan del backend; la vista no
    calcula nada.
--}}
<template x-if="$store.reclutamientoNavbar.items.length > 0">
    <ul class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7 2xl:grid-cols-9 gap-3" role="list">
        <template x-for="item in $store.reclutamientoNavbar.items" :key="item.id">
            <li class="bg-surface rounded-card shadow-sm border border-line overflow-hidden text-center">
                <div class="relative mx-auto w-full max-w-32 aspect-square">
                    <x-sprite
                        size="128"
                        wrapper-class="mx-auto"
                        x-bind:src="item.icono"
                        x-bind:alt="item.nombre"
                    />

                    <template x-if="item.cantidad > 1">
                        <span class="absolute top-1 right-1 px-1.5 py-0.5 bg-primary text-on-primary text-2xs font-bold rounded-full" x-text="item.cantidad"></span>
                    </template>
                </div>

                <div class="px-2 pb-2">
                    <p class="text-xs font-medium text-ink truncate" x-text="item.nombre"></p>
                    <p class="text-2xs text-ink-subtle" x-text="'x' + item.cantidad"></p>

                    <button
                        type="button"
                        @click="$store.reclutamientoNavbar.reclutar(item.id)"
                        :disabled="$store.reclutamientoNavbar.accionEnCurso"
                        :aria-label="'Reclutar a ' + item.nombre"
                        class="mt-1 w-full px-2 py-1 bg-primary text-on-primary rounded-control text-2xs font-medium hover:bg-primary-hover focus-ring transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Reclutar
                    </button>
                    <button
                        type="button"
                        @click="$store.reclutamientoNavbar.solicitarDescarte(item.id)"
                        :aria-label="'Descartar a ' + item.nombre"
                        class="mt-1 w-full px-2 py-1 bg-danger text-on-primary rounded-control text-2xs font-medium hover:bg-danger/90 focus-ring transition-colors"
                    >
                        Descartar
                    </button>
                </div>
            </li>
        </template>
    </ul>
</template>

{{-- En la primera carga el contenedor muestra su propio skeleton: el vacío solo
     aparece cuando no hay items y no hay petición en curso. --}}
<template x-if="$store.reclutamientoNavbar.items.length === 0 && !$store.reclutamientoNavbar.cargando">
    <div class="text-center py-16">
        <i class="fa-solid fa-box-open mb-4 block text-4xl text-ink-subtle" aria-hidden="true"></i>
        <p class="text-lg text-ink-muted">No hay Pokémon reclutables</p>
        <p class="mt-1 text-sm text-ink-subtle">Explora hábitats para capturar Pokémon</p>
    </div>
</template>
