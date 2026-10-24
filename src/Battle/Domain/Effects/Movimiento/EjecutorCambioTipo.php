<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\ObjetivoMovimiento;

/**
 * Cambio de tipo temporal (Camuflaje, Conversión y similares).
 */
class EjecutorCambioTipo extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::CAMBIO_TIPO;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $tipo = $accion->move->perfil->tipoOverride;
        if ($tipo === null) {
            return;
        }

        $objetivo = $accion->move->objetivo === ObjetivoMovimiento::SELF
            ? $accion->attacker
            : $accion->defender;

        $objetivo->aplicarTipoOverride($tipo, $accion->move->perfil->turnosTipo);
        $battle->agregarLog(
            "{$objetivo->nombre()} cambia temporalmente a tipo {$tipo->label()}",
        );
    }
}
