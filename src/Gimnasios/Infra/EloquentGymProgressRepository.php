<?php

declare(strict_types=1);

namespace Src\Gimnasios\Infra;

use App\Models\GymProgress;
use Src\Gimnasios\Domain\Repositories\GymProgressRepositoryInterface;

final class EloquentGymProgressRepository implements GymProgressRepositoryInterface
{
    public function obtenerProgreso(int $userId, string $gymId): ?int
    {
        $modelo = GymProgress::query()
            ->where('user_id', $userId)
            ->where('gym_id', $gymId)
            ->first();

        return $modelo !== null ? $modelo->current_stage : null;
    }

    public function registrarVictoria(int $userId, string $gymId, int $etapaCompletada): void
    {
        $siguiente = min(5, $etapaCompletada + 1);
        $data = ['current_stage' => $siguiente];

        if ($siguiente === 5) {
            $data['completed_at'] = now();
        }

        GymProgress::updateOrCreate(
            ['user_id' => $userId, 'gym_id' => $gymId],
            $data,
        );
    }

    public function esCompletado(int $userId, string $gymId): bool
    {
        return GymProgress::query()
            ->where('user_id', $userId)
            ->where('gym_id', $gymId)
            ->where('current_stage', 5)
            ->exists();
    }
}
