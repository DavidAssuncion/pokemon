<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain;

use Src\HabitatsTemporales\Domain\Collections\OleadaRivalCollection;

/**
 * Facción rival con sus 3 oleadas y sus datos de presentación.
 */
final readonly class FaccionRival
{
    public function __construct(
        public string $slug,
        public string $nombre,
        public string $descripcion,
        public string $color,
        public string $imagen,
        public int $orden,
        public int $medallasDesbloqueo,
        public OleadaRivalCollection $oleadas,
    ) {
    }

    public function oleada(int $numero): ?OleadaRival
    {
        $encontrada = $this->oleadas->first(
            static fn (object $oleada): bool => $oleada instanceof OleadaRival
                && $oleada->numero === $numero,
        );

        return $encontrada instanceof OleadaRival ? $encontrada : null;
    }

    public function totalOleadas(): int
    {
        return $this->oleadas->count();
    }
}
