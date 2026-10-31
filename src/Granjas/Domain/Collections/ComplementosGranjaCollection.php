<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\Collections;

use Src\Granjas\Domain\DataTransferObjects\ComplementoGranja;
use Src\Shared\Domain\Collection;

final class ComplementosGranjaCollection extends Collection
{
    public string $type = ComplementoGranja::class;

    public function evTotal(): int
    {
        return (int) $this->sum(
            static fn (ComplementoGranja $complemento): int => $complemento->ev,
        );
    }

    public function nivelDe(int $statId): int
    {
        /** @var ComplementoGranja|null $complemento */
        $complemento = $this->first(
            static fn (ComplementoGranja $item): bool => $item->stat->stat->value === $statId,
        );

        return $complemento !== null ? $complemento->nivel : 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->map(
            static fn (ComplementoGranja $complemento): array => $complemento->toArray(),
        );
    }
}
