<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Equipo;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Velo Aurora: reduce a la mitad el daño físico y especial del equipo.
 */
class EfectoEquipoAuroraVeil implements InterfazEfectoEquipo
{
    public function __construct(
        private readonly string $clave,
        private int $turnosRestantes = 5,
        private float $capacidadRestante = 9999.0,
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
        if (! $this->estaActivo() || $accion->move->esEstado()) {
            return 1.0;
        }

        return 0.5;
    }

    public function multiplicadorVelocidad(): float
    {
        return 1.0;
    }

    public function consumir(float $dano, MovimientoBatalla $movimiento): void
    {
        if (! $this->estaActivo() || $movimiento->esEstado()) {
            return;
        }

        $this->capacidadRestante = max(0.0, $this->capacidadRestante - $dano);
    }

    public function estaActivo(): bool
    {
        return $this->turnosRestantes > 0 && $this->capacidadRestante > 0.0;
    }
}
