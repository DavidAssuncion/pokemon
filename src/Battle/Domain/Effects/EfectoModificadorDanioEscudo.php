<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Effects\Capacidades\ModificaDanioEscudo;

/**
 * Cañones: +25 % de daño a las barreras (además del bonus a movimientos con
 * flags de puñetazo/pulso/bala, que se aplica como modificador normal).
 */
class EfectoModificadorDanioEscudo implements InterfazEfecto, ModificaDanioEscudo
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly float $multiplicador = 1.25,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function multiplicadorDanioEscudo(AccionBatalla $accion): float
    {
        return max(1.0, $this->multiplicador);
    }
}
