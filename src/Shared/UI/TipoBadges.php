<?php

declare(strict_types=1);

namespace Src\Shared\UI;

use Src\Shared\Tipos\TipoPokemon;

/**
 * Constantes de presentación: colores Tailwind por tipo de Pokémon.
 * Single source of truth para badges de tipo en vistas Blade/Alpine.
 */
final class TipoBadges
{
    /**
     * Mapa de TipoPokemon::value (int 1-18) → ['label', 'tailwind_classes'].
     *
     * @var array<int, array{0: string, 1: string}>
     */
    public const MAP = [
        TipoPokemon::NORMAL->value => ['Normal', 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'],
        TipoPokemon::LUCHA->value => ['Lucha', 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400'],
        TipoPokemon::VOLADOR->value => ['Volador', 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400'],
        TipoPokemon::VENENO->value => ['Veneno', 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400'],
        TipoPokemon::TIERRA->value => ['Tierra', 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'],
        TipoPokemon::ROCA->value => ['Roca', 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400'],
        TipoPokemon::BICHO->value => ['Bicho', 'bg-lime-100 dark:bg-lime-900/30 text-lime-700 dark:text-lime-400'],
        TipoPokemon::FANTASMA->value => ['Fantasma', 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400'],
        TipoPokemon::ACERO->value => ['Acero', 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300'],
        TipoPokemon::FUEGO->value => ['Fuego', 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'],
        TipoPokemon::AGUA->value => ['Agua', 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'],
        TipoPokemon::PLANTA->value => ['Planta', 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'],
        TipoPokemon::ELECTRICO->value => ['Eléctrico', 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400'],
        TipoPokemon::PSIQUICO->value => ['Psíquico', 'bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-400'],
        TipoPokemon::HIELO->value => ['Hielo', 'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-400'],
        TipoPokemon::DRAGON->value => ['Dragón', 'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-400'],
        TipoPokemon::SINIESTRO->value => ['Siniestro', 'bg-zinc-800 dark:bg-gray-600 text-white'],
        TipoPokemon::HADA->value => ['Hada', 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400'],
    ];

    /** @var array{0: string, 1: string} */
    public const DEFAULT = ['Desconocido', 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'];
}
