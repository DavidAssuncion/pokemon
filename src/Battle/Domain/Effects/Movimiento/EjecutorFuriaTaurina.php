<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Furia Taurina: adopta el tipo de la forma del usuario y rompe pantallas.
 */
class EjecutorFuriaTaurina extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::FURIA_TAURINA;
    }

    public function tipoEfectivo(AccionBatalla $accion, AgregadoBatalla $battle): ?TipoPokemon
    {
        foreach ($accion->attacker->tiposDefensivos() as $tipo) {
            if (in_array($tipo, [TipoPokemon::FUEGO, TipoPokemon::AGUA, TipoPokemon::LUCHA], true)) {
                return $tipo;
            }
        }

        return TipoPokemon::NORMAL;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $equipoRival = $battle->equipoRivalDe($accion->attacker);
        $equipoRival?->limpiarPantallas();
    }
}
