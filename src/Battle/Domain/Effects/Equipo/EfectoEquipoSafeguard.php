<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Equipo;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;

/** Velo Sagrado: protege al equipo de estados alterados. */
class EfectoEquipoSafeguard implements InterfazEfectoEquipo
{
    public function __construct(private readonly string $clave, private int $turnos = 5)
    {
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
        $this->turnos--;
    }
    public function multiplicadorDanoRecibido(AccionBatalla $accion): float
    {
        return 1.0;
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
        return $this->turnos > 0;
    }
}
