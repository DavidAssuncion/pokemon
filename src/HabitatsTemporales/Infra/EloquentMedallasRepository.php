<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Infra;

use Illuminate\Support\Facades\DB;
use Src\HabitatsTemporales\Domain\Repositories\MedallasRepositoryInterface;

/**
 * Cuenta las medallas de gimnasio completadas por el jugador.
 */
final class EloquentMedallasRepository implements MedallasRepositoryInterface
{
    public function contarCompletadas(int $userId): int
    {
        return DB::table('gym_progress')
            ->where('user_id', $userId)
            ->where('current_stage', 5)
            ->count();
    }
}
