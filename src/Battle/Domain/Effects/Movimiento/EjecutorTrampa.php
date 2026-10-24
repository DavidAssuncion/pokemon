<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoTrampa;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;

/**
 * Puas, Puas Tóxicas y Trampa Rocas: añaden una trampa al lado rival.
 */
class EjecutorTrampa extends EjecutorMovimientoAbstracto
{
    public function __construct(
        private readonly ClaveEfectoMovimiento $claveEfecto,
    ) {
    }

    public function clave(): ClaveEfectoMovimiento
    {
        return $this->claveEfecto;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $rival = $battle->equipoRivalDe($accion->attacker);
        if ($rival === null) {
            return;
        }

        foreach ($rival->efectos()->all() as $efecto) {
            if ($efecto instanceof EfectoEquipoTrampa && $efecto->obtenerClave() === $this->claveEfecto->value) {
                $efecto->sumar();

                return;
            }
        }

        $rival->agregarEfecto(new EfectoEquipoTrampa(
            clave: $this->claveEfecto->value,
            fraccionHp: 0.0833,
            fraccionEscudo: 0.1667,
            maxAcumulaciones: 3,
        ));

        $battle->agregarLog("{$accion->attacker->nombre()} coloca {$this->claveEfecto->value} en el campo rival");
    }
}
