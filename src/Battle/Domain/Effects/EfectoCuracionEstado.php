<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\TipoClima;

/** Cura el estado alterado al final de la ronda (Hidratación/Mudar). */
class EfectoCuracionEstado implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly float $probabilidad = 1.0,
        private readonly ?TipoClima $clima = null,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onRoundEnd(Combatiente $portador, AgregadoBatalla $battle): void
    {
        if (! $portador->estaVivo() || $portador->estado() === EstadoPokemon::NONE) {
            return;
        }

        if ($this->clima !== null && $battle->weather() !== $this->clima) {
            return;
        }

        if (mt_rand() / mt_getrandmax() > $this->probabilidad) {
            return;
        }

        $portador->setEstado(EstadoPokemon::NONE);
        $portador->setTurnosEstado(0);
        $portador->setContadorVenenoGrave(0);
        $battle->agregarLog("{$portador->nombre()} se cura de su estado alterado");
    }
}
