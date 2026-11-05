<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\Collections;

use Src\Granjas\Domain\DataTransferObjects\ErrorCosecha;
use Src\Shared\Domain\Collection;

final class ErroresCosechaCollection extends Collection
{
    public string $type = ErrorCosecha::class;

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->map(
            static fn (ErrorCosecha $error): array => $error->toArray(),
        );
    }
}
