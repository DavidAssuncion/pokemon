<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Equipo;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Pantalla de Luz / Reflejo: reduce a la mitad el daño especial o físico del
 * equipo hasta agotar su capacidad o sus turnos.
 */
class EfectoEquipoPantalla implements InterfazEfectoEquipo
{
    public function __construct(
        private readonly string $clave,
        private readonly bool $especial,
        private int $turnosRestantes,
        private float $capacidadRestante,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onRoundStart(EquipoBatalla $equipo, AgregadoBatalla $battle): void
    {
    }

    public function onRoundEnd(EquipoBatalla $equipo, AgregadoBatalla $battle): void
    {
        $this->turnosRestantes--;

        if ($this->turnosRestantes <= 0) {
            $this->capacidadRestante = 0.0;
        }
    }

    public function multiplicadorDanoRecibido(AccionBatalla $accion): float
    {
        if (! $this->estaActivo()) {
            return 1.0;
        }

        if ($accion->attacker->tieneEfecto('allanamiento')) {
            return 1.0;
        }

        $coincide = $this->especial ? $accion->move->esEspecial() : $accion->move->esFisico();

        return $coincide ? 0.5 : 1.0;
    }

    public function multiplicadorVelocidad(): float
    {
        return 1.0;
    }

    public function consumir(float $dano, MovimientoBatalla $movimiento): void
    {
        if (! $this->estaActivo()) {
            return;
        }

        $coincide = $this->especial ? $movimiento->esEspecial() : $movimiento->esFisico();
        if ($coincide) {
            $this->capacidadRestante = max(0.0, $this->capacidadRestante - $dano);
        }
    }

    public function estaActivo(): bool
    {
        return $this->turnosRestantes > 0 && $this->capacidadRestante > 0.0;
    }
}
