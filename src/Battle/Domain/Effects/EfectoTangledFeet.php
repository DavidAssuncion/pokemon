<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ProbabilidadEsquiva;
use Src\Battle\Domain\Enums\ClaveCondicion;

/** Tumbos: aumenta la evasión mientras el portador está confuso. */
class EfectoTangledFeet implements InterfazEfecto, ProbabilidadEsquiva
{
    use ComportamientosPorDefecto;

    public function __construct(private readonly string $clave, private readonly float $probabilidad = 0.125)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function probabilidadEsquiva(?Combatiente $portador = null): float
    {
        return $portador !== null && $portador->tieneCondicion(ClaveCondicion::CONFUSION)
            ? $this->probabilidad
            : 0.0;
    }
}
