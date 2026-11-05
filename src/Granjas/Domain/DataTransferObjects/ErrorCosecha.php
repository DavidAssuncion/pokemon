<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

/**
 * Fallo aislado al cosechar un nido concreto durante una cosecha global.
 */
final readonly class ErrorCosecha
{
    public function __construct(
        public int $farmId,
        public int $habitatId,
        public int $nidoId,
        public int $evolutionChainId,
        public string $mensaje,
    ) {
    }

    public static function desde(
        int $farmId,
        int $habitatId,
        int $nidoId,
        int $evolutionChainId,
        string $mensaje,
    ): self {
        return new self(
            farmId: $farmId,
            habitatId: $habitatId,
            nidoId: $nidoId,
            evolutionChainId: $evolutionChainId,
            mensaje: $mensaje,
        );
    }

    /**
     * @return array{
     *     farm_id:int,
     *     habitat_id:int,
     *     nido_id:int,
     *     evolution_chain_id:int,
     *     mensaje:string
     * }
     */
    public function toArray(): array
    {
        return [
            'farm_id' => $this->farmId,
            'habitat_id' => $this->habitatId,
            'nido_id' => $this->nidoId,
            'evolution_chain_id' => $this->evolutionChainId,
            'mensaje' => $this->mensaje,
        ];
    }
}
