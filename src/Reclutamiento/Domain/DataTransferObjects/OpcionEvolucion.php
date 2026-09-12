<?php

declare(strict_types=1);

namespace Src\Reclutamiento\Domain\DataTransferObjects;

use Src\Reclutamiento\Domain\Collections\RequisitoEvolucionCollection;

/**
 * Una opción de evolución del pokémon (Eevee: varias) con sus requisitos tipados.
 *
 * Sustituye el array {pokemon_id, nombre, imagen, requisitos, puede_evolucionar}
 * (deuda F6).
 */
final readonly class OpcionEvolucion
{
    public function __construct(
        public readonly int $pokemonId,
        public readonly string $nombre,
        public readonly string $imagen,
        public readonly RequisitoEvolucionCollection $requisitos,
        public readonly bool $puedeEvolucionar,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato previo.
     *
     * @return array{pokemon_id: int, nombre: string, imagen: string, requisitos: list<array{tipo: string, slug: string, necesario: int, actual: int, caramelosDisponibles: int}>, puede_evolucionar: bool}
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return [
            'pokemon_id' => $this->pokemonId,
            'nombre' => $this->nombre,
            'imagen' => $this->imagen,
            'requisitos' => $this->requisitos->toArray(),
            'puede_evolucionar' => $this->puedeEvolucionar,
        ];
    }
}
