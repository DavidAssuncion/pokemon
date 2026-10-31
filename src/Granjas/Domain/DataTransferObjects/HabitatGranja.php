<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

/**
 * Hábitat al que pertenece una granja.
 */
final readonly class HabitatGranja
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
