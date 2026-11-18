<?php

declare(strict_types=1);

namespace Src\Equipos\Domain\Collections;

use Src\Equipos\Domain\ValueObjects\EquipamientoPiezas;

/**
 * Mapa inmutable `reclutadoId => EquipamientoPiezas`.
 *
 * No extiende `Src\Shared\Domain\Collection` a propósito: su semántica es la de
 * un mapa indexado por reclutado (lectura por clave, sin iteración secuencial)
 * y `Collection` valida listas homogéneas. Envuelve el array en privado para
 * que la frontera HTTP/Livewire nunca reciba el array crudo; `obtener()`
 * devuelve vacío si el id no está.
 */
final readonly class EquipamientosPorReclutado
{
    /**
     * @param  array<int, EquipamientoPiezas>  $porReclutado
     */
    private function __construct(
        private array $porReclutado = [],
    ) {
    }

    public static function vacio(): self
    {
        return new self();
    }

    public function con(int $reclutadoId, EquipamientoPiezas $equipamiento): self
    {
        $porReclutado = $this->porReclutado;
        $porReclutado[$reclutadoId] = $equipamiento;

        return new self($porReclutado);
    }

    public function obtener(int $reclutadoId): EquipamientoPiezas
    {
        return $this->porReclutado[$reclutadoId] ?? EquipamientoPiezas::vacio();
    }
}
