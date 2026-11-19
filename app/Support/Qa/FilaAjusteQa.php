<?php

declare(strict_types=1);

namespace App\Support\Qa;

/**
 * Fila del panel QA: una clave de inventario ya resuelta para presentación.
 */
final readonly class FilaAjusteQa
{
    public function __construct(
        public string $clave,
        public string $nombre,
        public string $imagen,
        public int $cantidad,
    ) {
    }
}
