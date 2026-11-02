<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Collections;

use Src\Exploraciones\Domain\DataTransferObjects\Matchup;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de matchups del preview (sin neutrales).
 */
final class MatchupCollection extends Collection
{
    public string $type = Matchup::class;

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return $this->map(fn (Matchup $matchup): array => $matchup->toArray());
    }
}
