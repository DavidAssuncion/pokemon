<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\Repositories;

interface GymProgressRepositoryInterface
{
    /** Obtiene el stage actual del jugador en un gimnasio. null si no hay progreso. */
    public function obtenerProgreso(int $userId, string $gymId): ?int;

    /**
     * Registra que el jugador ha ganado en una etapa.
     * Avanza current_stage al siguiente. Si llega a 5, marca completed_at.
     */
    public function registrarVictoria(int $userId, string $gymId, int $etapaCompletada): void;

    /** Indica si el jugador ha completado el gimnasio (current_stage >= 5). */
    public function esCompletado(int $userId, string $gymId): bool;
}
