<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\EstadoPokemon;

/** Curación: probabilidad de curar el estado de los aliados al final de ronda. */
class EfectoCuraEquipo implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly float $probabilidad = 0.30,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onRoundEnd(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $equipo = $battle->equipoDe($portador);
        if ($equipo === null) {
            return;
        }

        foreach ($equipo->combatientesCollection()->vivos() as $aliado) {
            if ($aliado->estado() === EstadoPokemon::NONE || mt_rand() / mt_getrandmax() > $this->probabilidad) {
                continue;
            }

            $aliado->setEstado(EstadoPokemon::NONE);
            $aliado->setTurnosEstado(0);
            $aliado->setContadorVenenoGrave(0);
            $battle->agregarLog("{$aliado->nombre()} se cura gracias a Curación");
        }
    }
}
