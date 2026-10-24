<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\EstadoPokemon;

/**
 * Baya Lum: cura el estado mayor al inicio de la ronda, una sola vez.
 */
class EfectoBayaLum implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onRoundStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        if (! $portador->itemActivo() || ! $portador->estaVivo()) {
            return;
        }

        if ($portador->estado() === EstadoPokemon::NONE) {
            return;
        }

        $portador->setEstado(EstadoPokemon::NONE);
        $portador->setTurnosEstado(0);
        $portador->quitarCondicion(ClaveCondicion::CONFUSION);
        $portador->consumirCargaObjeto();
        $battle->agregarLog("{$portador->nombre()} consume Baya Lum y cura su estado");
    }
}
