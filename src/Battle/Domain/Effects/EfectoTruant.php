<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

/** Ausente: alterna un turno de acción y otro de descanso. */
class EfectoTruant implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    private bool $descansar = false;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onTurnStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        if ($this->descansar) {
            $portador->aplicarInterrupcion();
            $battle->agregarLog("{$portador->nombre()} está ausente y pierde el turno");
        }

        $this->descansar = ! $this->descansar;
    }
}
