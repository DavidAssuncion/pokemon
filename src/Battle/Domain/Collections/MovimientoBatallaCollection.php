<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Collections;

use Src\Battle\Domain\MovimientoBatalla;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de MovimientoBatalla.
 *
 * Nace en Battle (junto a MovimientoBatalla): la deuda 12 de docs/context.md
 * planifica mover MovimientoBatalla a src/Pokemon/Domain/Movement/ para romper
 * el ciclo Pokemon↔Battle. NO se mueve en esta tarea; si se migra la clase,
 * esta colección debe viajar con ella.
 */
final class MovimientoBatallaCollection extends Collection
{
    public string $type = MovimientoBatalla::class;

    /**
     * Primer movimiento con el nombre dado, o null si no existe.
     */
    public function porNombre(string $nombre): ?MovimientoBatalla
    {
        return $this->first(fn (MovimientoBatalla $movimiento) => $movimiento->nombre === $nombre);
    }

    /**
     * Nueva colección con los movimientos de potencia estrictamente mayor.
     */
    public function conPotenciaMayorQue(int $potencia): static
    {
        return $this->filter(fn (MovimientoBatalla $movimiento) => $movimiento->potencia > $potencia);
    }

    /**
     * Frontera: serialización de cada movimiento (mismo shape que la sesión).
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->map(fn (MovimientoBatalla $movimiento) => $movimiento->__serialize());
    }
}
