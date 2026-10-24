<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Effects\Capacidades\ModificaDanioInfligido;

/**
 * Daño creciente según la vida que le falte al objetivo (Depredador /
 * Depredador Prehistórico / Malvado): +0,25 % por cada 1 % de vida faltante.
 */
class EfectoDanioPorVidaFaltante implements InterfazEfecto, ModificaDanioInfligido
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly float $porcentajePorPunto = 0.0025,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function multiplicadorDanioInfligido(AccionBatalla $accion): float
    {
        $maxHp = $accion->defender->pokemon()->battleStats()->hp;
        if ($maxHp <= 0.0) {
            return 1.0;
        }

        $vidaFaltante = max(0.0, 1.0 - ($accion->defender->hpActual() / $maxHp));

        return 1.0 + ($vidaFaltante * 100 * $this->porcentajePorPunto);
    }
}
