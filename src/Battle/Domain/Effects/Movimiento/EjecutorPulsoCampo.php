<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\TipoTerreno;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Pulso de Campo: cambia de tipo y duplica potencia según el terreno activo.
 */
class EjecutorPulsoCampo extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::PULSO_CAMPO;
    }

    public function potenciaEfectiva(AccionBatalla $accion, AgregadoBatalla $battle): ?int
    {
        return $battle->terreno() === TipoTerreno::NONE ? null : 100;
    }

    public function tipoEfectivo(AccionBatalla $accion, AgregadoBatalla $battle): ?TipoPokemon
    {
        return match ($battle->terreno()) {
            TipoTerreno::HIERBA => TipoPokemon::PLANTA,
            TipoTerreno::ELECTRICO => TipoPokemon::ELECTRICO,
            TipoTerreno::PSIQUICO => TipoPokemon::PSIQUICO,
            TipoTerreno::NIEBLA => TipoPokemon::HADA,
            TipoTerreno::NONE => null,
        };
    }
}
