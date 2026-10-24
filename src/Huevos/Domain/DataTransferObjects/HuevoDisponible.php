<?php

declare(strict_types=1);

namespace Src\Huevos\Domain\DataTransferObjects;

use Src\Shared\Domain\DataTransferObjects\PokemonResumen;

/**
 * Familia con reclutados suficientes para crear un huevo.
 */
final readonly class HuevoDisponible
{
    public function __construct(
        public readonly int $evolutionChainId,
        public readonly PokemonResumen $pokemon,
        public readonly int $captureRate,
        public readonly int $hatch,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato JSON previo.
     *
     * @return array{
     *     evolution_chain_id: int,
     *     pokemon: array{id: int, name: string, icon: string},
     *     capture_rate: int,
     *     hatch: int,
     * }
     */
    public function toArray(): array
    {
        return [
            'evolution_chain_id' => $this->evolutionChainId,
            'pokemon' => $this->pokemon->toArray(),
            'capture_rate' => $this->captureRate,
            'hatch' => $this->hatch,
        ];
    }
}
