<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\DataTransferObjects;

/**
 * Hábitat de un avistamiento con los datos necesarios para presentarlo.
 */
final class HabitatAvistamiento
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
    ) {
    }
}
