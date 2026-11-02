<div class="flex flex-col rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/30 p-2.5" :class="mov.desbloqueado === false ? 'opacity-60' : ''">
    <div class="flex items-start justify-between gap-1.5">
        <p class="min-w-0 text-sm font-semibold leading-tight text-gray-900 dark:text-white" x-text="mov.name"></p>
        <span class="shrink-0 inline-flex items-center gap-1 pl-0 pr-2 py-0.5 text-[11px] font-semibold rounded-full" :class="tipoBadge(mov.tipo_slug || tipoDeMovimiento(mov)).clases">
            <img :src="tipoBadge(mov.tipo_slug || tipoDeMovimiento(mov)).icono" alt="" loading="lazy" decoding="async" class="w-[30px] h-[30px] object-contain"><span x-text="tipoDeMovimiento(mov) || '—'"></span>
        </span>
    </div>
    <div class="mt-1 flex flex-wrap items-center gap-x-1.5 text-xs text-gray-400 dark:text-gray-500">
        <img :src="categoriaIconoUrl(mov)" :alt="etiquetaCategoria(mov)" loading="lazy" decoding="async" class="w-[30px] h-[30px] object-contain"><span x-text="etiquetaCategoria(mov)"></span>
        <span x-show="mov.base_power > 0" x-text="'· ' + mov.base_power + ' pot.'"></span>
        <span x-show="mov.origen === 'nivel' && mov.nivel_requerido" x-text="'· Nv ' + mov.nivel_requerido"></span>
        <span x-show="mov.origen === 'senda' && mov.nivel_senda" x-text="'· Senda ' + mov.nivel_senda + (mov.es_maestria ? ' · Maestría ' + mov.tramo_senda + '/5' : '')"></span>
        <span x-show="!mov.desbloqueado && mov.origen === 'senda' && mov.coste_senda" x-text="'· ' + mov.coste_senda + ' caramelos'"></span>
    </div>
    <p class="mt-1.5 flex-1 text-xs leading-snug text-gray-500 dark:text-gray-400" x-text="mov.description || 'Sin descripción.'"></p>

    <template x-if="mov.desbloqueado">
        <button type="button" @click="toggleLoadoutMovimiento(mov.slug)" class="mt-2 w-full px-2 py-1 rounded-md text-[11px] font-bold border transition-colors" :class="loadoutMovimientos.includes(mov.slug) ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300' : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:border-blue-300'" x-text="loadoutMovimientos.includes(mov.slug) ? 'Quitar' : 'Añadir'"></button>
    </template>
    <template x-if="!mov.desbloqueado && mov.origen === 'senda'">
        <a :href="'/sendas/' + mov.tipo_slug" class="mt-2 block w-full text-center px-2 py-1 rounded-md text-[11px] font-bold border border-amber-300 dark:border-amber-700 text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors">Ver Senda</a>
    </template>
    <template x-if="!mov.desbloqueado && mov.origen === 'nivel'">
        <a :href="'/sendas/' + mov.tipo_slug" class="mt-2 block w-full text-center px-2 py-1 rounded-md text-[11px] font-bold border border-gray-200 text-gray-500 hover:border-amber-300 hover:text-amber-700 dark:border-gray-700 dark:text-gray-400 dark:hover:border-amber-700 dark:hover:text-amber-300 transition-colors" x-text="'Nivel ' + mov.nivel_requerido + ' · Ver Senda'"></a>
    </template>
</div>
