<?php

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\AgregadoBatalla;

/**
 * Restos: cura 1/16 del HP máximo al final de cada ronda.
 */
class EfectoRestos implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
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

        $maxHp = $portador->pokemon->battleStats->hp;
        $cura = max(1, $maxHp * 0.0625);
        $portador->hpActual = min($maxHp, $portador->hpActual + $cura);
        $battle->log[] = "[{$portador->nombre}] Restos recuperan {$cura} PS";
    }
}
