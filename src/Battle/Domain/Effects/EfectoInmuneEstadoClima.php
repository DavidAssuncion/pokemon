<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlEstado;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\TipoClima;

/** Defensa Hoja: inmunidad a estados mientras el clima indicado esté activo. */
class EfectoInmuneEstadoClima implements InterfazEfecto, ReaccionaAlEstado
{
    use ComportamientosPorDefecto;

    /** @param list<EstadoPokemon> $estados */
    public function __construct(
        private readonly string $clave,
        private readonly array $estados,
        private readonly TipoClima $clima,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function permiteEstado(Combatiente $portador, EstadoPokemon $estado, AgregadoBatalla $battle): bool
    {
        if ($portador->clima() !== $this->clima) {
            return true;
        }

        return ! in_array($estado, $this->estados, true);
    }
}
