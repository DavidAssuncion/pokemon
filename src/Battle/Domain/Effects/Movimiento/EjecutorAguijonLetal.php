<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Aguijón Letal: sube Ataque si debilita al objetivo. */
class EjecutorAguijonLetal extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::AGUIJON_LETAL;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        if ($accion->defender->estaVivo()) {
            return;
        }

        $cambio = CambioStat::desdeEtapas(StatClave::ATAQUE, 1);
        $accion->attacker->setMultiplicadores($cambio->aplicadoTemporalEn($accion->attacker->multiplicadores()));
        $battle->agregarLog("{$accion->attacker->nombre()} sube su Ataque tras debilitar al rival");
    }
}
