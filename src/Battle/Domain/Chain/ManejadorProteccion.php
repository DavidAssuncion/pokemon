<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;

/**
 * Protección: reduce el daño del turno (no lo anula). Aguante se aplica al
 * final de la cadena en ManejadorAguante.
 */
class ManejadorProteccion extends ManejadorDanioAbstracto
{
    /** Fracción del daño que sigue recibiendo el portador de Protección. */
    private const FACTOR_DANIO_RECIBIDO = 0.4;

    protected function process(AccionBatalla $action, float $daño): float
    {
        if ($action->defender->tieneCondicion(ClaveCondicion::PROTEGIDO)) {
            return $daño * self::FACTOR_DANIO_RECIBIDO;
        }

        return $daño;
    }
}
