<?php

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\AgregadoBatalla;

/**
 * Regenera un porcentaje de la barrera de defensa física (DefenseHp)
 * al final de cada ronda.
 */
class EfectoRegeneracionDefensa implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly float $porcentaje,
    ) {}

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onRoundEnd(Combatiente $portador, AgregadoBatalla $battle): void
    {
        if (!$portador->estaVivo()) {
            return;
        }

        $maxDefHp = $portador->pokemon->battleStats->defenseHp;
        $cura = $maxDefHp * ($this->porcentaje / 100);
        $portador->defensaHpActual = min($maxDefHp, $portador->defensaHpActual + $cura);
    }
}
