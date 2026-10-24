<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Base de los ejecutores: por defecto no cambian potencia, tipo ni hacen nada.
 */
abstract class EjecutorMovimientoAbstracto implements InterfazEjecutorMovimiento
{
    public function potenciaEfectiva(AccionBatalla $accion, AgregadoBatalla $battle): ?int
    {
        return null;
    }

    public function tipoEfectivo(AccionBatalla $accion, AgregadoBatalla $battle): ?TipoPokemon
    {
        return null;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
    }
}
