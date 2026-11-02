<?php

declare(strict_types=1);

return [
    'niveles' => ['visibles' => 3, 'maximo' => 5],
    'umbrales' => [1 => 0, 2 => 9000, 3 => 18000, 4 => 36000, 5 => 72000],
    'costes' => [1 => 2, 2 => 5, 3 => 10, 4 => 25, 5 => 60],
    'experiencia' => ['maxima' => 72000, 'por_caramelo' => 100],
    'beneficios' => [4 => 0.10, 5 => 0.20],
    'captura' => ['cap_base' => 0.25, 'cap_senda' => 0.30, 'factores' => [1 => 1.0, 2 => 0.5, 3 => 0.3]],
];
