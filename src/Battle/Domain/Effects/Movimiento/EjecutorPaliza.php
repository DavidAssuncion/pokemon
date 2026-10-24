<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;

/**
 * Paliza: el número de golpes depende de los aliados vivos. La potencia de
 * cada golpe se calcula a partir del Ataque del aliado que lo aporta.
 */
class EjecutorPaliza extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::PALIZA;
    }

    public function potenciaEfectiva(AccionBatalla $accion, AgregadoBatalla $battle): ?int
    {
        $equipo = $battle->equipoDe($accion->attacker);
        if ($equipo === null) {
            return null;
        }

        $total = 0;
        foreach ($equipo->combatientesCollection()->vivos()->all() as $aliado) {
            $total += max(5, (int) round($aliado->obtenerStatEfectivo(
                \Src\Battle\Domain\Enums\StatClave::ATAQUE,
            ) / 10));
        }

        return $total > 0 ? $total : null;
    }
}
