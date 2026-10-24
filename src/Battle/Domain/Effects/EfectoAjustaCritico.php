<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Effects\Capacidades\AjustaCritico;

/**
 * Multiplica la probabilidad de crítico del portador (Predicción ×2).
 */
class EfectoAjustaCritico implements InterfazEfecto, AjustaCritico
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly float $multiplicador = 2.0,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function multiplicadorProbabilidadCritico(): float
    {
        return $this->multiplicador;
    }
}
