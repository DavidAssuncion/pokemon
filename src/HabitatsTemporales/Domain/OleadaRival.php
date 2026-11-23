<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain;

use Src\HabitatsTemporales\Domain\Collections\CombateRivalCollection;

/**
 * Una oleada de una facción: 3 reclutas y un jefe (4 combates).
 */
final readonly class OleadaRival
{
    public function __construct(
        public int $numero,
        public string $nombre,
        public CombateRivalCollection $combates,
    ) {
    }

    public function combate(int $numero): ?CombateRival
    {
        $encontrado = $this->combates->first(
            static fn (object $combate): bool => $combate instanceof CombateRival
                && $combate->numero === $numero,
        );

        return $encontrado instanceof CombateRival ? $encontrado : null;
    }

    public function totalCombates(): int
    {
        return $this->combates->count();
    }
}
