<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlEstado;
use Src\Battle\Domain\Enums\EstadoPokemon;

/**
 * Antídoto: el veneno no solo no daña, sino que cura al portador.
 */
class EfectoAntidoto implements InterfazEfecto, ReaccionaAlEstado
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly float $fraccionCuracion = 0.125,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function permiteEstado(Combatiente $portador, EstadoPokemon $estado, AgregadoBatalla $battle): bool
    {
        if ($estado !== EstadoPokemon::POISON && $estado !== EstadoPokemon::BAD_POISON) {
            return true;
        }

        $portador->curarConExcedente($portador->pokemon()->battleStats()->hp * $this->fraccionCuracion);
        $battle->agregarLog("{$portador->nombre()} convierte el veneno en curación");

        return false;
    }
}
