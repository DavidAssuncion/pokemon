<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Equipo;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Trampa de lado: daña al equipo rival al inicio de cada ronda.
 * Puas, Puas Tóxicas y Trampa Rocas comparten esta mecánica con distintos
 * porcentajes/tipo y hasta 3 acumulaciones.
 */
class EfectoEquipoTrampa implements InterfazEfectoEquipo
{
    public int $acumulaciones = 1;

    public function __construct(
        private readonly string $clave,
        private readonly float $fraccionHp,
        private readonly float $fraccionEscudo,
        private readonly int $maxAcumulaciones = 3,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function sumar(): void
    {
        $this->acumulaciones = min($this->maxAcumulaciones, $this->acumulaciones + 1);
    }

    public function onRoundStart(EquipoBatalla $equipo, AgregadoBatalla $battle): void
    {
        if ($equipo->bloqueaDanioIndirecto()) {
            return;
        }

        foreach ($equipo->combatientesCollection()->vivos() as $objetivo) {
            $maxHp = $objetivo->pokemon()->battleStats()->hp;
            $danoHp = $maxHp * $this->fraccionHp * $this->acumulaciones;
            $objetivo->recibirDaño($danoHp, false, 1.0);

            $objetivo->daniarBarreras(
                $objetivo->pokemon()->battleStats()->spDefenseHp * $this->fraccionEscudo * $this->acumulaciones,
                true,
            );
            $objetivo->daniarBarreras(
                $objetivo->pokemon()->battleStats()->defenseHp * $this->fraccionEscudo * $this->acumulaciones,
                false,
            );

            $battle->agregarLog("{$objetivo->nombre()} sufre el efecto de {$this->clave}");

            if ($objetivo->hpActual() <= 0.0) {
                $battle->agregarLog("¡{$objetivo->nombre()} se ha debilitado por {$this->clave}!");
            }
        }
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
        return true;
    }
}
