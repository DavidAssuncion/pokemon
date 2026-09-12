<?php

declare(strict_types=1);

namespace Src\Mazmorras\Infra;

use App\Models\DungeonLog;
use App\Models\DungeonProgress;
use Illuminate\Support\Carbon;
use Src\Mazmorras\Domain\Repositories\DungeonProgresoRepositoryInterface;

final class EloquentDungeonProgresoRepository implements DungeonProgresoRepositoryInterface
{
    public function pisoActual(int $userId, int $habitatId): ?int
    {
        $progreso = DungeonProgress::query()
            ->where('user_id', $userId)
            ->where('habitat_id', $habitatId)
            ->first();

        return $progreso !== null ? $progreso->current_floor : null;
    }

    public function registrarVictoria(int $userId, int $habitatId, int $pisoGanado, int $totalPisos): void
    {
        $siguiente = min($totalPisos + 1, $pisoGanado + 1);
        $data = ['current_floor' => $siguiente];

        if ($siguiente > $totalPisos) {
            $data['completed_at'] = now();
        }

        DungeonProgress::updateOrCreate(
            ['user_id' => $userId, 'habitat_id' => $habitatId],
            $data,
        );
    }

    public function registrarDerrota(int $userId, int $habitatId, int $piso): void
    {
        DungeonLog::create([
            'user_id' => $userId,
            'habitat_id' => $habitatId,
            'floor' => $piso,
            'won' => false,
            'fought_at' => now(),
        ]);
    }

    public function enCooldown(int $userId, int $habitatId, int $piso): bool
    {
        return DungeonLog::query()
            ->where('user_id', $userId)
            ->where('habitat_id', $habitatId)
            ->where('floor', $piso)
            ->where('won', false)
            ->where('fought_at', '>', now()->subHour())
            ->exists();
    }

    public function cooldownHasta(int $userId, int $habitatId, int $piso): ?Carbon
    {
        $derrota = DungeonLog::query()
            ->where('user_id', $userId)
            ->where('habitat_id', $habitatId)
            ->where('floor', $piso)
            ->where('won', false)
            ->where('fought_at', '>', now()->subHour())
            ->latest('fought_at')
            ->first();

        return $derrota !== null ? $derrota->fought_at->addHour() : null;
    }
}
