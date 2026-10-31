<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\Collections;

use App\Enums\StatEnum;
use Src\Granjas\Domain\DataTransferObjects\NivelComplemento;
use Src\Shared\Domain\Collection;

final class NivelesComplementoCollection extends Collection
{
    public string $type = NivelComplemento::class;

    public function nivelDe(StatEnum $stat): int
    {
        /** @var NivelComplemento|null $nivel */
        $nivel = $this->first(
            static fn (NivelComplemento $item): bool => $item->stat === $stat,
        );

        return $nivel !== null ? $nivel->nivel : 0;
    }
}
