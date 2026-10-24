<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlEstado;
use Src\Battle\Domain\Enums\EstadoPokemon;

/**
 * Inmunidad a uno o varios estados alterados.
 */
class EfectoInmuneEstado implements InterfazEfecto, ReaccionaAlEstado
{
    use ComportamientosPorDefecto;

    /** @param list<EstadoPokemon> $estados */
    public function __construct(
        private readonly string $clave,
        private readonly array $estados,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function permiteEstado(Combatiente $portador, EstadoPokemon $estado, AgregadoBatalla $battle): bool
    {
        return ! in_array($estado, $this->estados, true);
    }
}
