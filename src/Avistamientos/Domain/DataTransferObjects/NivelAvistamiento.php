<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\DataTransferObjects;

/**
 * Especies afectadas agrupadas por nivel de aparición.
 */
final class NivelAvistamiento
{
    public function __construct(
        public readonly int $nivel,
        public readonly EspeciesAvistadasCollection $especies,
    ) {
    }
}
