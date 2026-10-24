<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Equipo;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Reduce el daño de un tipo durante N turnos (Chapoteo Lodo).
 */
class EfectoEquipoResistenciaTipo implements InterfazEfectoEquipo
{
    public function __construct(
        private readonly string $clave,
        private readonly TipoPokemon $tipo,
        private readonly float $factor,
        private int $turnosRestantes,
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
        if (! $this->estaActivo()) {
            return 1.0;
        }

        return $accion->move->tipo === $this->tipo ? $this->factor : 1.0;
    }

    public function multiplicadorVelocidad(): float
    {
        return 1.0;
    }

    public function consumir(float $dano, MovimientoBatalla $movimiento): void
    {
    }

    public function estaActivo(): bool
    {
        return $this->turnosRestantes > 0;
    }
}
