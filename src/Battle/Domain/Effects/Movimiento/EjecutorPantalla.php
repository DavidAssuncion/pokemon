<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Chain\ManejadorDanioBase;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoPantalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\StatClave;

/**
 * Pantalla de Luz / Reflejo: escudo de equipo con capacidad calculada a partir
 * de la Defensa o Defensa Especial del usuario (potencia 70 × 5).
 */
class EjecutorPantalla extends EjecutorMovimientoAbstracto
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
        $equipo = $battle->equipoDe($accion->attacker);
        if ($equipo === null) {
            return;
        }

        $especial = $this->claveEfecto === ClaveEfectoMovimiento::PANTALLA_LUZ;
        $stat = $accion->attacker->obtenerStatEfectivo(
            $especial ? StatClave::DEFENSA_ESPECIAL : StatClave::DEFENSA,
        );
        $capacidad = ManejadorDanioBase::formulaBase(50, 70, $stat, 50) * 5;
        $turnos = 10;

        if ($accion->attacker->tieneEfecto('pantallista')) {
            $capacidad *= 2;
            $turnos *= 2;
        }

        $equipo->agregarEfecto(new EfectoEquipoPantalla(
            clave: $this->claveEfecto->value,
            especial: $especial,
            turnosRestantes: $turnos,
            capacidadRestante: $capacidad,
        ));

        $battle->agregarLog("{$accion->attacker->nombre()} levanta {$this->claveEfecto->value}");
    }
}
