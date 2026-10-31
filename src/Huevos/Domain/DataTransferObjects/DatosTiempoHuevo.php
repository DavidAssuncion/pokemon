<?php

declare(strict_types=1);

namespace Src\Huevos\Domain\DataTransferObjects;

/**
 * Datos de especie y nido necesarios para calcular el estado de un huevo.
 */
final readonly class DatosTiempoHuevo
{
    public function __construct(
        public int $captureRate,
        public int $hatch,
        public int $nidoNivel,
    ) {
    }
}
