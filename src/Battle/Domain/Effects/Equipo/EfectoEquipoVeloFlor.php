<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Equipo;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Shared\Tipos\TipoPokemon;

/** Velo Flor: los aliados de tipo Planta no pueden ser afectados por estados. */
class EfectoEquipoVeloFlor implements InterfazEfectoEquipo
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

    public function protegeA(Combatiente $combatiente, EstadoPokemon $estado): bool
    {
        return $this->activo
            && $estado !== EstadoPokemon::NONE
            && in_array(TipoPokemon::PLANTA, $combatiente->tiposDefensivos(), true);
    }
}
