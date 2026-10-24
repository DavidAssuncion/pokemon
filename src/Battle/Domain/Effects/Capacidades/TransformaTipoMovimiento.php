<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AccionBatalla;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Habilidad que cambia el tipo efectivo de un movimiento (Alma Dragón:
 * los ataques Normal pasan a Dragón).
 */
interface TransformaTipoMovimiento
{
    public function tipoEfectivo(AccionBatalla $accion): ?TipoPokemon;
}
