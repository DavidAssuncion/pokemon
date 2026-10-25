<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/** Protección / Detección: reduce el daño recibido durante la ronda a cambio de no atacar. */
class EjecutorProteccion extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::PROTECCION;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $atacante = $accion->attacker;
        $ronda = $battle->turnManager()->round();
        $ultimaRonda = $atacante->ultimaRondaProteccion();

        if ($ultimaRonda > 0 && $ultimaRonda === $ronda - 1) {
            $atacante->setUltimaRondaProteccion($ronda);
            $battle->agregarLog("{$atacante->nombre()} no puede volver a protegerse en rondas consecutivas");

            return;
        }

        $atacante->setUltimaRondaProteccion($ronda);
        $atacante->reemplazarCondicion(new CondicionVolatil(ClaveCondicion::PROTEGIDO, 1));
        $battle->agregarLog("{$atacante->nombre()} se protege (-60% daño)");
    }
}
