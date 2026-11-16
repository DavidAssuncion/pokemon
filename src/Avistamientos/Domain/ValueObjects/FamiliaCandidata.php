<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\ValueObjects;

/**
 * Familia evolutiva elegible dentro de un hábitat, con su número de especies.
 */
final class FamiliaCandidata
{
    public function __construct(
        public readonly int $evolutionChainId,
        public readonly int $numeroEspecies,
    ) {
    }
}
