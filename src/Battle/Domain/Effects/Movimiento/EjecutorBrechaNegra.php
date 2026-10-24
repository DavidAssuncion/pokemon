<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;

/**
 * Brecha Negra: si el objetivo no tiene escudo especial, lo duerme un turno y
 * el usuario recupera el 50 % del daño. Si ya está dormido, potencia 100.
 */
class EjecutorBrechaNegra extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::BRECHA_NEGRA;
    }

    public function potenciaEfectiva(AccionBatalla $accion, AgregadoBatalla $battle): ?int
    {
        return $accion->defender->estado() === EstadoPokemon::SLEEP ? 100 : null;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        if ($accion->defender->defensaEspHpActual() > 0.0) {
            return;
        }

        if ($accion->defender->estado() === EstadoPokemon::NONE) {
            $accion->defender->setEstado(EstadoPokemon::SLEEP);
            $accion->defender->setTurnosEstado(1);
        }

        $curacion = $danoAplicado * 0.5;
        if ($curacion > 0.0) {
            $accion->attacker->curarConExcedente($curacion);
        }

        $battle->agregarLog("{$accion->defender->nombre()} cae en la Brecha Negra");
    }
}
