<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\Collections;

use Src\Granjas\Domain\DataTransferObjects\RefugioEstado;
use Src\Shared\Domain\Collection;

/**
 * Refugios de un hábitat indexados por cadena evolutiva.
 */
final class RefugiosHabitatCollection extends Collection
{
    public string $type = RefugioEstado::class;

    public function paraChain(int $chainId): ?RefugioEstado
    {
        /** @var RefugioEstado|null $refugio */
        $refugio = $this->first(
            static fn (RefugioEstado $estado): bool => $estado->chainId === $chainId,
        );

        return $refugio;
    }

    public function exclusivo(): ?RefugioEstado
    {
        /** @var RefugioEstado|null $refugio */
        $refugio = $this->first(
            static fn (RefugioEstado $estado): bool => $estado->nivel === 5,
        );

        return $refugio;
    }
}
