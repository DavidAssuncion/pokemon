<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\ObjetivoMovimiento;

/**
 * Sustituto: crea un clon con PS propios que absorbe el daño dirigido al
 * portador. No ocupa un slot adicional del equipo.
 */
class EjecutorSustituto extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::SUSTITUTO;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $objetivo = $accion->move->objetivo === ObjetivoMovimiento::SELF
            ? $accion->attacker
            : $accion->defender;

        if (! $objetivo->estaVivo()) {
            return;
        }

        $perfil = $accion->move->perfil;
        $hp = $objetivo->aplicarSustituto($perfil->fraccionHpSustituto, $perfil->turnosSustituto);

        if ($hp <= 0.0) {
            $battle->agregarLog("{$objetivo->nombre()} no tiene HP suficiente para crear un sustituto");

            return;
        }

        $battle->agregarLog(
            "{$objetivo->nombre()} crea un sustituto con ".round($hp).' PS',
        );
    }
}
