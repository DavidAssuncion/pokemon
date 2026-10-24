<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Atiborramiento: cura la mitad del HP y sube dos etapas la Defensa. */
class EjecutorAtiborramiento extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::ATIBORRAMIENTO;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $cantidad = $accion->attacker->pokemon()->battleStats()->hp * 0.5;
        $accion->attacker->curarConExcedente($cantidad);
        $accion->attacker->setMultiplicadores(CambioStat::desdeEtapas(StatClave::DEFENSA, 2)->aplicadoTemporalEn($accion->attacker->multiplicadores()));
        $battle->agregarLog("{$accion->attacker->nombre()} come y se atiborra");
    }
}
