<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Equipo;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Efecto persistente de un equipo (pantallas, trampas, condiciones de lado).
 */
interface InterfazEfectoEquipo
{
    public function obtenerClave(): string;

    public function onRoundStart(EquipoBatalla $equipo, AgregadoBatalla $battle): void;

    public function onRoundEnd(EquipoBatalla $equipo, AgregadoBatalla $battle): void;

    /**
     * Factor sobre el daño que recibe el equipo (1.0 = sin cambio).
     */
    public function multiplicadorDanoRecibido(AccionBatalla $accion): float;

    /**
     * Factor sobre la velocidad efectiva del equipo (1.0 = sin cambio).
     */
    public function multiplicadorVelocidad(): float;

    /**
     * Consume capacidad del efecto tras aplicar daño (pantallas).
     */
    public function consumir(float $dano, MovimientoBatalla $movimiento): void;

    public function estaActivo(): bool;
}
