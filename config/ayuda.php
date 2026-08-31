<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Ayuda
    |--------------------------------------------------------------------------
    |
    | Secciones mostradas en el popup de ayuda del nav (layouts/app.blade.php).
    | El backend provee esta configuración; el contenido aquí es placeholder.
    |
    */

    'secciones' => [
        [
            'titulo' => 'Guía rápida',
            'items' => [
                'Crea un equipo de 3 Pokémon desde la pestaña Equipos.',
                'Asigna a cada miembro un rol (Vanguardia, Combatiente, Recolector o Rastreador).',
                'Explora hábitats y envía a tu equipo desde Hábitats o Exploraciones.',
                'Recluta nuevos Pokémon tras completar exploraciones.',
            ],
        ],
        [
            'titulo' => 'Preguntas frecuentes',
            'items' => [
                '¿Cómo formo un equipo? Usa el botón "Nuevo Equipo" y añade Pokémon desde "Reclutados Disponibles".',
                '¿Puedo cambiar de rol a un miembro? Sí, desde el desplegable bajo su nombre en la tarjeta del equipo.',
                '¿Qué significa "En exploración"? El equipo está fuera; no puede modificarse hasta que regrese.',
                '¿Cómo recibo mis recompensas? Revisa "Resultados por revisar" en Exploraciones.',
            ],
        ],
        [
            'titulo' => 'Próximas mejoras',
            'items' => [
                'Bonificaciones de sinergia por composición de equipo.',
                'Más hábitats, provincias y especies disponibles.',
                'Historial detallado de exploraciones y estadísticas.',
            ],
        ],
        [
            'titulo' => 'Anotaciones',
            'items' => [],
        ],
    ],

];
