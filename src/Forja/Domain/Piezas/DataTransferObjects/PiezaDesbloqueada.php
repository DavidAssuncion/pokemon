<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Piezas\DataTransferObjects;

/**
 * Estado de fabricación de una receta de pieza para el jugador.
 *
 * `desbloqueada` depende solo del nivel de nido; `puedeFabricar` exige además
 * no poseer la pieza, tener la anterior y cubrir los materiales.
 */
final readonly class PiezaDesbloqueada
{
    public function __construct(
        public RecetaPieza $receta,
        public bool $desbloqueada,
        public bool $puedeFabricar,
        public ?string $motivo,
    ) {
    }
}
