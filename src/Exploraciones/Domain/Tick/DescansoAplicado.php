<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Tick;

use Src\Exploraciones\Domain\ValueObjects\EstadoExplorador;

/**
 * Resultado de aplicar un descanso al explorador.
 */
final readonly class DescansoAplicado
{
    public function __construct(
        public EstadoExplorador $estado,
        public int $duracionMinutos,
        public float $hpRecuperado,
    ) {
    }
}
