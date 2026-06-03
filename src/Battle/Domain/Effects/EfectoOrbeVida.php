<?php

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\AgregadoBatalla;

/**
 * Orbe Vida: el portador pierde 10% de su HP máximo cada vez que
 * inflige daño con un movimiento. El bonus de daño ×1.3 se aplica
 * en el ManejadorOrbeVida de la cadena de daño.
 */
class EfectoOrbeVida implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
    ) {}

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onDamageDealt(Combatiente $portador, Combatiente $target, float $daño, AgregadoBatalla $battle): void
    {
        if ($daño <= 0 || !$portador->estaVivo()) {
            return;
        }

        $maxHp = $portador->pokemon->battleStats->hp;
        $recoil = max(1, $maxHp * 0.10);
        $portador->hpActual = max(0, $portador->hpActual - $recoil);
        $battle->log[] = "[{$portador->nombre}] pierde {$recoil} PS por la Orbe Vida";
    }
}
