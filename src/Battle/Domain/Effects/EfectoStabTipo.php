<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Effects\Capacidades\BonusStab;
use Src\Battle\Domain\ReglasBatalla;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Guardián de los mares: los movimientos de Agua reciben STAB aunque el
 * portador no sea de tipo Agua.
 */
class EfectoStabTipo implements InterfazEfecto, BonusStab
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly TipoPokemon $tipo,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function multiplicadorStab(AccionBatalla $accion): float
    {
        if ($accion->attacker->tipoEfectivoMovimiento($accion) !== $this->tipo) {
            return 1.0;
        }

        foreach ($accion->attacker->tiposDefensivos() as $tipoAtacante) {
            if ($tipoAtacante === $this->tipo) {
                return 1.0;
            }
        }

        return ReglasBatalla::BONUS_STAB;
    }
}
