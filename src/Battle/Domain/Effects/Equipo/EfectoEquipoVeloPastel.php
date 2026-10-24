<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Equipo;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;

/** Velo Pastel: protege al equipo del veneno. */
class EfectoEquipoVeloPastel implements InterfazEfectoEquipo
{
    private bool $activo = true;

    public function __construct(private readonly string $clave, private readonly string $origenId)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onRoundStart(EquipoBatalla $equipo, AgregadoBatalla $battle): void
    {
        $origen = $equipo->findCombatantById($this->origenId);
        $this->activo = $origen !== null && $origen->estaVivo();
    }

    public function onRoundEnd(EquipoBatalla $equipo, AgregadoBatalla $battle): void
    {
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
        return $this->activo;
    }

    public function bloqueaEstado(EstadoPokemon $estado): bool
    {
        return $this->activo && in_array($estado, [EstadoPokemon::POISON, EstadoPokemon::BAD_POISON], true);
    }
}
