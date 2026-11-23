<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Nivel mínimo de jugador por tier de spawn
    |--------------------------------------------------------------------------
    |
    | Cada fila de `pokemon_habitat` tiene un tier (1-3). El proceso
    | `habitats:recalcular-niveles` asigna a cada spawn el mayor de:
    |   - el mínimo del tier,
    |   - el nivel de evolución (`pokemon_evolution.minimum_level`),
    |   - el mínimo de starter cuando la familia es inicial.
    |
    | Los valores son editables y se pueden sobrescribir por spawn desde la
    | gestión del hábitat (columna `manual`).
    |
    */

    'min_level_por_tier' => [
        1 => 1,
        2 => 8,
        3 => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Iniciales
    |--------------------------------------------------------------------------
    |
    | Todas las familias starter y sus formas regionales requieren el mismo
    | nivel, independientemente del tier. Los IDs son estables.
    |
    */

    'starter_min_level' => 35,

    /*
    | Cadenas evolutivas de los starters de todas las generaciones.
    |
    | @var list<int>
    */
    'starter_chain_ids' => [
        // Kanto
        1, 2, 3,
        // Johto
        79, 80, 81,
        // Hoenn
        130, 131, 132,
        // Sinnoh
        203, 204, 205,
        // Unova
        256, 257, 258,
        // Kalos
        337, 338, 339,
        // Alola
        374, 375, 376,
        // Galar
        430, 431, 432,
        // Paldea
        478, 479, 480,
        // Formas de Hisui con cadena propia
        13080, 13258, 13374,
    ],

    /*
    | Especies concretas de starter que no comparten la cadena principal
    | (formas regionales con `evolution_chain_id` propio).
    |
    | @var list<int>
    */
    'starter_species_ids' => [
        10233, // Typhlosion de Hisui
        10236, // Samurott de Hisui
        10244, // Decidueye de Hisui
    ],

    /*
    |--------------------------------------------------------------------------
    | Hábitats sin spawns salvajes
    |--------------------------------------------------------------------------
    |
    | Hábitats que existen como escenario pero no alimentan rutas,
    | exploraciones ni entrenadores. Los legendarios se sirven únicamente
    | desde encuentros especiales.
    |
    */

    'habitats_sin_spawns' => [49],

    /*
    |--------------------------------------------------------------------------
    | Overrides de nivel mínimo por especie
    |--------------------------------------------------------------------------
    |
    | Se aplican antes que tier, evolución y starter. Útil para especies sin
    | evolución que deben aparecer más tarde de lo que marca su tier.
    |
    | @var array<int, int>
    */
    'species_min_level_overrides' => [
        79 => 18,  // Slowpoke
        131 => 30, // Lapras
    ],

    /*
    |--------------------------------------------------------------------------
    | Paradoja / prehistóricos
    |--------------------------------------------------------------------------
    |
    | Los Pokémon Paradoja (pasado y futuro) no evolucionan y se desbloquean
    | siempre a 55, por encima del mínimo de su tier.
    |
    */

    'paradox_min_level' => 55,

    /*
    | @var list<int>
    */
    'paradox_species_ids' => [
        // Pasado / prehistóricos
        984, // Great Tusk
        985, // Scream Tail
        986, // Brute Bonnet
        987, // Flutter Mane
        988, // Slither Wing
        989, // Sandy Shocks
        1005, // Roaring Moon
        1009, // Walking Wake
        1020, // Gouging Fire
        1021, // Raging Bolt
        // Futuro
        990, // Iron Treads
        991, // Iron Bundle
        992, // Iron Hands
        993, // Iron Jugulis
        994, // Iron Moth
        995, // Iron Thorns
        1006, // Iron Valiant
        1010, // Iron Leaves
        1022, // Iron Boulder
        1023, // Iron Crown
    ],

];
