<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\Entities;

/**
 * Plantación persistente de una baya en un hueco de nido. El stock no se
 * almacena: se calcula a partir de `plantedAt`, `harvestCount` y la hora actual.
 */
final readonly class Plantacion
{
    public function __construct(
        public int $id,
        public int $slotIndex,
        public string $berryKey,
        public \DateTimeImmutable $plantedAt,
        public int $harvestCount,
    ) {
    }

    /**
     * @return array{id:int, slot_index:int, berry_key:string, planted_at:string, harvest_count:int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slot_index' => $this->slotIndex,
            'berry_key' => $this->berryKey,
            'planted_at' => $this->plantedAt->format(\DateTimeInterface::ATOM),
            'harvest_count' => $this->harvestCount,
        ];
    }
}
