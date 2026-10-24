<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Effects\Capacidades\BonusStab;
use Src\Battle\Domain\ReglasBatalla;

/**
 * Vacío: los movimientos de los tipos del portador reciben STAB otra vez.
 */
class EfectoDobleStab implements InterfazEfecto, BonusStab
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function multiplicadorStab(AccionBatalla $accion): float
    {
        $tipo = $accion->attacker->tipoEfectivoMovimiento($accion);

        foreach ($accion->attacker->tiposDefensivos() as $tipoAtacante) {
            if ($tipoAtacante === $tipo) {
                return ReglasBatalla::BONUS_STAB;
            }
        }

        return 1.0;
    }
}
