<?php

declare(strict_types=1);

namespace Src\Reclutamiento\Domain\Collections;

use Src\Reclutamiento\Domain\DataTransferObjects\ReclutableResumen;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de reclutables resumidos.
 *
 * No ordena ni filtra: conserva el orden canónico decidido por la aplicación.
 */
final class ReclutablesResumenCollection extends Collection
{
    public string $type = ReclutableResumen::class;

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return $this->map(fn (ReclutableResumen $reclutable): array => $reclutable->toArray());
    }
}
