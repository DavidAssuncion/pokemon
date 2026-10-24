<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\EstadoPokemon;

/**
 * Habilidad que decide si un estado alterado puede aplicarse a su portador
 * (Antídoto, inmunidades). Devolver false cancela la aplicación.
 */
interface ReaccionaAlEstado
{
    public function permiteEstado(Combatiente $portador, EstadoPokemon $estado, AgregadoBatalla $battle): bool;
}
