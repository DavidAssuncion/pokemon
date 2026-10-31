<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use App\Enums\StatEnum;

/**
 * Presentación de un stat de complemento.
 */
final readonly class StatGranja
{
    public function __construct(
        public StatEnum $stat,
        public string $nombre,
        public string $slug,
    ) {
    }
}
