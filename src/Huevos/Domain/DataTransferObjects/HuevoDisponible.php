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
        public int $evolutionChainId,
        public PokemonResumen $pokemon,
        public EspecieHuevo $especie,
        public NidoHuevo $nido,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'evolution_chain_id' => $this->evolutionChainId,
            'pokemon' => $this->pokemon->toArray(),
            'capture_rate' => $this->especie->captureRate,
            'hatch' => $this->especie->hatch,
            'nido_nivel' => $this->nido->nivel,
            'rango_iv' => $this->nido->rangoIvs->toArray(),
            'factor_tiempo' => $this->nido->factorTiempo,
        ];
    }
}
