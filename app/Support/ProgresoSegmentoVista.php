<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tramo de una barra de EXP que se anima de forma independiente.
 *
 * `desde` y `hasta` son porcentajes del nivel (0-100); el cliente solo
 * interpola entre ambos valores, no deriva niveles ni fórmulas.
 */
final readonly class ProgresoSegmentoVista
{
    public function __construct(
        public int $nivel,
        public int $desde,
        public int $hasta,
    ) {
    }

    /**
     * Frontera de vista.
     *
     * @return array{nivel: int, desde: int, hasta: int}
     */
    public function aArray(): array
    {
        return [
            'nivel' => $this->nivel,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ];
    }
}
