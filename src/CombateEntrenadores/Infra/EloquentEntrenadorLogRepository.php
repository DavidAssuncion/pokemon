<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Infra;

use App\Models\TrainerCombatLog;
use Src\CombateEntrenadores\Domain\Repositories\EntrenadorLogRepositoryInterface;

class EloquentEntrenadorLogRepository implements EntrenadorLogRepositoryInterface
{
    public function haGanadoHoy(int $userId, int $habitatId, int $level, int $trainerIndex, string $fecha): bool
    {
        return TrainerCombatLog::query()
            ->where('user_id', $userId)
            ->where('habitat_id', $habitatId)
            ->where('level', $level)
            ->where('trainer_index', $trainerIndex)
            ->where('fought_at', $fecha)
            ->where('won', true)
            ->exists();
    }

    public function registrarResultado(int $userId, int $habitatId, int $level, int $trainerIndex, bool $won, string $fecha): void
    {
        TrainerCombatLog::updateOrCreate(
            [
                'user_id' => $userId,
                'habitat_id' => $habitatId,
                'level' => $level,
                'trainer_index' => $trainerIndex,
                'fought_at' => $fecha,
            ],
            ['won' => $won],
        );
    }
}
