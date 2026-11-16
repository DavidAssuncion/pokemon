<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Collections;

use Src\Exploraciones\Domain\DataTransferObjects\ExpedicionResumen;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de expediciones resumidas para el panel.
 *
 * No ordena ni filtra: conserva el orden canónico decidido por la aplicación.
 */
final class ExpedicionesResumenCollection extends Collection
{
    public string $type = ExpedicionResumen::class;

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return $this->map(fn (ExpedicionResumen $expedicion): array => $expedicion->toArray());
    }
}
