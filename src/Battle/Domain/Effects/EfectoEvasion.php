<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ProbabilidadEsquiva;
use Src\Battle\Domain\Enums\TipoClima;

/**
 * Evasión: 12,5 % de esquivar un ataque.
 */
class EfectoEvasion implements InterfazEfecto, ProbabilidadEsquiva
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly float $probabilidad = 0.125,
        private readonly ?TipoClima $clima = null,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function probabilidadEsquiva(?Combatiente $portador = null): float
    {
        if ($this->clima !== null && $portador !== null && $portador->clima() !== $this->clima) {
            return 0.0;
        }

        return $this->probabilidad;
    }
}
