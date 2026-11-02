<?php

declare(strict_types=1);

/*
 * Parámetros ajustables del selector de movimientos de rivales.
 *
 * Los valores por defecto viven también en `PerfilMovesetRival` para que el
 * dominio pueda probarse sin depender de la configuración de Laravel. Ajustar
 * aquí no requiere tocar la lógica del selector.
 */
return [
    'rival' => [
        'slots' => 6,
        'caps' => [
            'ruta' => [1 => 60, 2 => 90, 3 => 110],
            'entrenador' => [1 => 70, 2 => 95, 3 => 120],
            'gimnasio' => [1 => 80, 2 => 100, 3 => 120],
            'lider' => [1 => 90, 2 => 110, 3 => 130],
        ],
        'max_utilidad' => [1 => 1, 2 => 2, 3 => 2],
        'max_setup' => [1 => 0, 2 => 1, 3 => 1],
        'precision_min' => 0.70,
        'roles' => [
            'fast_attacker' => ['ataques' => 4, 'utilidad' => 1, 'setup' => 0],
            'bulky_attacker' => ['ataques' => 4, 'utilidad' => 1, 'setup' => 0],
            'bulky_support' => ['ataques' => 3, 'utilidad' => 3, 'setup' => 0],
            'setup_sweeper' => ['ataques' => 4, 'utilidad' => 0, 'setup' => 1],
            'stall' => ['ataques' => 2, 'utilidad' => 4, 'setup' => 0],
            'lider' => ['ataques' => 5, 'utilidad' => 1, 'setup' => 0],
            'sin_showdown' => ['ataques' => 4, 'utilidad' => 1, 'setup' => 0],
        ],
    ],
];
