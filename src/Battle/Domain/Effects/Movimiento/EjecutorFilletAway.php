<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Deslome: paga la mitad del HP y sube Ataque, At. Especial y Velocidad. */
class EjecutorFilletAway extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::FILLET_AWAY;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $coste = $accion->attacker->pokemon()->battleStats()->hp * 0.5;
        $accion->attacker->setHpActual(max(1.0, $accion->attacker->hpActual() - $coste));
        foreach ([StatClave::ATAQUE, StatClave::ATAQUE_ESPECIAL, StatClave::VELOCIDAD] as $stat) {
            $accion->attacker->setMultiplicadores(CambioStat::desdeEtapas($stat, 2)->aplicadoTemporalEn($accion->attacker->multiplicadores()));
        }
        $battle->agregarLog("{$accion->attacker->nombre()} se desloma para atacar mejor");
    }
}
