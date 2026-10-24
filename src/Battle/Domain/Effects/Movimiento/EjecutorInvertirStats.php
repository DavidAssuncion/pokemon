<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\MultiplicadoresStats;

/** Reversión: invierte las etapas de estadísticas del objetivo. */
class EjecutorInvertirStats extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::INVERTIR_STATS;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $etapas = $accion->defender->multiplicadores()->obtenerEtapas();
        $invertidas = array_map(static fn (int $etapa): int => -$etapa, $etapas->toArray());
        $accion->defender->setMultiplicadores(MultiplicadoresStats::desdeEtapas($invertidas));
        $battle->agregarLog("{$accion->defender->nombre()} ve invertidas sus características");
    }
}
