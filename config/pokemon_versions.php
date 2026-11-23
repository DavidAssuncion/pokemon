<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Nivel de desbloqueo de formas
    |--------------------------------------------------------------------------
    |
    | La primera vez que se cambia a una forma alternativa hay que pagar la
    | exp de tipo equivalente a subir al Pokémon a este nivel, igual que una
    | evolución. Después el cambio es libre y reversible.
    |
    */

    'nivel_desbloqueo' => 55,

    /*
    |--------------------------------------------------------------------------
    | Grupos de versiones
    |--------------------------------------------------------------------------
    |
    | Solo los Pokémon del mismo grupo pueden cambiar de forma entre sí. Las
    | formas de evolución que no estén aquí (p. ej. Typhlosion de Hisui) son
    | permanentes una vez elegidas.
    |
    | @var array<string, list<int>>
    */
    'grupos' => [
        'deoxys' => [386, 10001, 10002, 10003],
        'giratina' => [487, 10007],
        'gardevoir-gallade' => [282, 475],
    ],

];
