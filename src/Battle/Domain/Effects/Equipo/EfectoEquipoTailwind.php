<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Equipo;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Tailwind: duplica la velocidad efectiva del equipo durante 4 turnos.
 */
class EfectoEquipoTailwind implements InterfazEfectoEquipo
{
    public function __construct(
        private readonly string $clave,
        private int $turnosRestantes = 4,
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
    }

    public function multiplicadorDanoRecibido(AccionBatalla $accion): float
    {
        return 1.0;
    }

    public function multiplicadorVelocidad(): float
    {
        return $this->estaActivo() ? 2.0 : 1.0;
    }

    public function consumir(float $dano, MovimientoBatalla $movimiento): void
    {
    }

    public function estaActivo(): bool
    {
        return $this->turnosRestantes > 0;
    }
}
