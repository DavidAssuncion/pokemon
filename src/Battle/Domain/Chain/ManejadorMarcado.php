<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;

/**
 * Aplica el multiplicador de la condición MARCADO. La marca vive en el
 * defensor y guarda su propio factor, de modo que el catálogo puede definir
 * marcas más fuertes sin tocar la cadena.
 */
class ManejadorMarcado extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        if (! $action->defender->estaVivo()) {
            return $daño;
        }

        $multiplicador = 1.0;
        foreach ([ClaveCondicion::MARCADO, ClaveCondicion::VULNERABLE] as $clave) {
            $condicion = $action->defender->condicion($clave);
            if ($condicion !== null && $condicion->multiplicadorDanoRecibido > 1.0) {
                $multiplicador *= $condicion->multiplicadorDanoRecibido;
            }
        }

        return $daño * $multiplicador;
    }
}
