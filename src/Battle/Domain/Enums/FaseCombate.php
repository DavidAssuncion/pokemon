<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Representa las fases de una batalla Pokémon.
 *
 * Cada valor (string) es el contrato con la propiedad pública `$phase`
 * de `App\Livewire\Combate` y con las Blade que comparan fase por
 * string (p. ej. `$phase === 'init'`).
 *
 * Se consume SIEMPRE como `->value` (el string subyacente).  El enum
 * NUNCA debe pasarse como objeto Livewire a la vista porque Livewire
 * serializa/deserializa el estado de los componentes y un enum
 * serializado puede romper la hidratación entre peticiones.
 *
 * Por la misma razón, `$phase` en `Combate` sigue siendo de tipo
 * `string`: es el límite natural entre la capa de dominio (donde el
 * enum vive) y la capa de presentación (Livewire + Blade).
 *
 * Valores:
 *  - INICIO ('init')            → batalla recién creada, sin selección.
 *  - SELECCION_OBJETIVO         → el jugador elige objetivo del ataque.
 *  - SELECCION_MOVIMIENTO       → el jugador elige movimiento a usar.
 *  - BATALLA_TERMINADA          → resultado final, no hay más acciones.
 */
enum FaseCombate: string
{
    case INICIO = 'init';
    case SELECCION_OBJETIVO = 'player_target';
    case SELECCION_MOVIMIENTO = 'player_move';
    case BATALLA_TERMINADA = 'battle_over';
}
