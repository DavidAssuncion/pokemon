<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Acupresión: sube dos etapas una estadística aleatoria del objetivo. */
class EjecutorAcupresion extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::ACUPRESION;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $stats = [StatClave::ATAQUE, StatClave::DEFENSA, StatClave::ATAQUE_ESPECIAL, StatClave::DEFENSA_ESPECIAL, StatClave::VELOCIDAD];
        $stat = $stats[array_rand($stats)];
        $objetivo = $accion->move->objetivo->value === 'self' ? $accion->attacker : $accion->defender;
        $objetivo->setMultiplicadores(CambioStat::desdeEtapas($stat, 2)->aplicadoTemporalEn($objetivo->multiplicadores()));
        $battle->agregarLog("{$objetivo->nombre()} sube su {$stat->label()}");
    }
}
