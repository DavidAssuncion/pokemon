<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Pokédex: desbloquear todos (TEMPORAL)
    |--------------------------------------------------------------------------
    |
    | Cuando está activo, la Pokédex muestra todos los Pokémon y permite abrir
    | su detalle (tipos, stats y hábitat) aunque no hayan sido avistados.
    | Pensado solo para pruebas/QA. Se activa con POKEDEX_UNLOCK_ALL=true o,
    | en local/debug, añadiendo ?unlock=1 a /pokedex.
    |
    */
    'unlock_all' => (bool) env('POKEDEX_UNLOCK_ALL', false),
];
