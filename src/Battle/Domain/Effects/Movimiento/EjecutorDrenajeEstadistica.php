<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Absorbefuerza: cura al usuario según el Ataque del objetivo y lo baja. */
class EjecutorDrenajeEstadistica extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::DRENAJE_ESTADISTICA;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $cantidad = $accion->defender->obtenerStatEfectivo(StatClave::ATAQUE);
        $accion->attacker->curarConExcedente($cantidad);
        $cambio = CambioStat::desdeEtapas(StatClave::ATAQUE, -1);
        $accion->defender->setMultiplicadores($cambio->aplicadoTemporalEn($accion->defender->multiplicadores()));
        $battle->agregarLog("{$accion->attacker->nombre()} absorbe la fuerza de {$accion->defender->nombre()}");
    }
}
