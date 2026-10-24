<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Tambor: paga la mitad del HP máximo y maximiza el Ataque. */
class EjecutorTambor extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::TAMBOR;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $coste = $accion->attacker->pokemon()->battleStats()->hp * 0.5;
        $accion->attacker->setHpActual(max(1.0, $accion->attacker->hpActual() - $coste));
        $accion->attacker->setMultiplicadores(CambioStat::desdeEtapas(StatClave::ATAQUE, 6)->aplicadoTemporalEn($accion->attacker->multiplicadores()));
        $battle->agregarLog("{$accion->attacker->nombre()} toca el tambor y maximiza su Ataque");
    }
}
