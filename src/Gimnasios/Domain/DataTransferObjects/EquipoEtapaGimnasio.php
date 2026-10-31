<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\DataTransferObjects;

use Src\Shared\Collections\IntCollection;

/**
 * Equipo de una etapa de gimnasio (1-4) separado por posiciones:
 * vanguardia y retaguardia. Inmutable.
 *
 * Las colecciones contienen species_id enteros (IntCollection). El mapa
 * `movimientos` es opcional y permite definir a mano el loadout de cada
 * especie: `[species_id => [slug, ...]]`. Si una especie no tiene entrada,
 * el generador usa todos sus movimientos de nivel disponibles.
 */
final class EquipoEtapaGimnasio
{
    /**
     * @param  array<int, list<string>>  $movimientos
     */
    public function __construct(
        public readonly IntCollection $vanguardia,
        public readonly IntCollection $retaguardia,
        public readonly array $movimientos = [],
    ) {
    }

    public function todos(): IntCollection
    {
        return new IntCollection([
            ...$this->vanguardia->all(),
            ...$this->retaguardia->all(),
        ]);
    }

    /**
     * @return list<string>
     */
    public function movimientosDe(int $speciesId): array
    {
        $movimientos = $this->movimientos[$speciesId] ?? [];

        return is_array($movimientos)
            ? array_values(array_filter($movimientos, 'is_string'))
            : [];
    }
}
