<?php

declare(strict_types=1);

namespace Src\Mazmorras\Domain\Repositories;

use Illuminate\Support\Carbon;

interface DungeonProgresoRepositoryInterface
{
    /** Piso actual alcanzable del jugador en una mazmorra. null si no hay progreso. */
    public function pisoActual(int $userId, int $habitatId): ?int;

    /** Avanza al siguiente piso (o marca la mazmorra completada si era el último). */
    public function registrarVictoria(int $userId, int $habitatId, int $pisoGanado, int $totalPisos): void;

    /** Registra una derrota en un piso (inicia el cooldown de 1h). */
    public function registrarDerrota(int $userId, int $habitatId, int $piso): void;

    /** ¿Está el piso en cooldown por una derrota reciente (< 1h)? */
    public function enCooldown(int $userId, int $habitatId, int $piso): bool;

    /** Fin del cooldown del piso (fought_at + 1h) o null si no hay cooldown. */
    public function cooldownHasta(int $userId, int $habitatId, int $piso): ?Carbon;
}
