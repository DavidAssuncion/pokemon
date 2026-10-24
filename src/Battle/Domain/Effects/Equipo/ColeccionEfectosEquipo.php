<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Equipo;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de efectos de equipo.
 */
final class ColeccionEfectosEquipo extends Collection
{
    public string $type = InterfazEfectoEquipo::class;

    public function __clone(): void
    {
        $items = [];
        foreach ($this->items as $item) {
            $items[] = clone $item;
        }
        $this->items = $items;
    }

    /**
     * @return list<InterfazEfectoEquipo>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function multiplicadorDanoRecibido(AccionBatalla $accion): float
    {
        $factor = 1.0;
        foreach ($this->items as $efecto) {
            $factor *= $efecto->multiplicadorDanoRecibido($accion);
        }

        return $factor;
    }

    public function consumir(float $dano, MovimientoBatalla $movimiento): void
    {
        foreach ($this->items as $efecto) {
            $efecto->consumir($dano, $movimiento);
        }
    }

    /**
     * Elimina las pantallas activas (Furia Taurina, Despejar, Giro Rápido).
     */
    public function limpiarPantallas(): void
    {
        $this->items = array_values(array_filter(
            $this->items,
            static fn (InterfazEfectoEquipo $efecto): bool => ! in_array(
                $efecto->obtenerClave(),
                ['pantalla_luz', 'reflejo'],
                true,
            ),
        ));
    }

    public function onRoundStart(EquipoBatalla $equipo, AgregadoBatalla $battle): void
    {
        foreach ($this->items as $efecto) {
            $efecto->onRoundStart($equipo, $battle);
        }
    }

    public function onRoundEnd(EquipoBatalla $equipo, AgregadoBatalla $battle): void
    {
        foreach ($this->items as $efecto) {
            $efecto->onRoundEnd($equipo, $battle);
        }

        $this->items = array_values(array_filter(
            $this->items,
            static fn (InterfazEfectoEquipo $efecto): bool => $efecto->estaActivo(),
        ));
    }
}
