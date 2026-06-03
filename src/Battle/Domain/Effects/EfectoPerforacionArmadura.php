<?php

namespace Src\Battle\Domain\Effects;

/**
 * Efecto que hace que un porcentaje del daño infligido por el portador
 * ignore las barreras (DefenseHp / SpDefenseHp) y vaya directo a la salud.
 */
class EfectoPerforacionArmadura implements InterfazEfecto
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

    public function obtenerPorcentajeDanioDirecto(): float
    {
        return $this->porcentaje;
    }
}
