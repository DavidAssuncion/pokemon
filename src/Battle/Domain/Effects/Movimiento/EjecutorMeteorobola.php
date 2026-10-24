<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Meteorobola: cambia de tipo y duplica potencia según el clima activo.
 */
class EjecutorMeteorobola extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::METEOROBOLA;
    }

    public function potenciaEfectiva(AccionBatalla $accion, AgregadoBatalla $battle): ?int
    {
        return $battle->weather() === TipoClima::NONE ? null : 100;
    }

    public function tipoEfectivo(AccionBatalla $accion, AgregadoBatalla $battle): ?TipoPokemon
    {
        return match ($battle->weather()) {
            TipoClima::SEQUIA => TipoPokemon::FUEGO,
            TipoClima::DILUVIO => TipoPokemon::AGUA,
            TipoClima::GRANIZO => TipoPokemon::HIELO,
            TipoClima::TORMENTA_ARENA => TipoPokemon::ROCA,
            TipoClima::NIEBLA => TipoPokemon::SINIESTRO,
            TipoClima::TURBULENCIAS => TipoPokemon::VOLADOR,
            TipoClima::NONE => null,
        };
    }
}
