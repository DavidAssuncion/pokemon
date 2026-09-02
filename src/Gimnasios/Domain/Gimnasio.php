<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain;

use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Representa un gimnasio del juego: sus datos fijos (slug, medalla, tipo, nivel
 * mínimo) y los equipos de cada etapa (EquipoEtapaGimnasio con vanguardia y
 * retaguardia para etapas 1-4).
 */
final class Gimnasio
{
    /**
     * @param  array<int, EquipoEtapaGimnasio>  $equipos  etapas 1-4, cada una con vanguardia/retaguardia
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $medalla,
        public readonly TipoPokemon $tipo,
        public readonly int $nivelMinimo,
        public readonly array $equipos,
    ) {
    }

    public function equipoEtapa(int $etapa): ?EquipoEtapaGimnasio
    {
        return $this->equipos[$etapa] ?? null;
    }

    public function nombreEtapa(int $etapa): string
    {
        return match ($etapa) {
            1 => 'Entrenador 1',
            2 => 'Entrenador 2',
            3 => 'Entrenador 3',
            4 => 'Líder',
            default => 'Desconocido',
        };
    }
}
