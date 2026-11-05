<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\Collections;

use Src\Granjas\Domain\DataTransferObjects\BayaCosechada;
use Src\Shared\Domain\Collection;

final class BayasCosechadasCollection extends Collection
{
    public string $type = BayaCosechada::class;

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->map(
            static fn (BayaCosechada $baya): array => $baya->toArray(),
        );
    }
}
