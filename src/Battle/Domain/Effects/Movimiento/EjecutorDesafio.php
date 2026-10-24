<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/**
 * Desafío: mientras dure, el objetivo solo puede seleccionar al usuario como
 * objetivo (salvo que la posición lo impida).
 */
class EjecutorDesafio extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::DESAFIO;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $accion->defender->agregarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::DESAFIO,
            turnos: 3,
            origenId: $accion->attacker->id(),
            objetivoForzadoId: $accion->attacker->id(),
        ));

        $battle->agregarLog("{$accion->defender->nombre()} queda desafiado por {$accion->attacker->nombre()}");
    }
}
