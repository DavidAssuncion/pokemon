<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use App\Models\Granja;
use App\Models\Habitat;
use Src\Shared\Domain\Exceptions\RecursoNoExiste;

/**
 * Obtiene (o crea) la granja de un jugador en un hábitat.
 */
final class AsegurarGranja
{
    public function para(int $userId, int $habitatId): Granja
    {
        if (Habitat::find($habitatId) === null) {
            throw new RecursoNoExiste('Hábitat no encontrado');
        }

        $granja = Granja::query()
            ->where('user_id', $userId)
            ->where('habitat_id', $habitatId)
            ->first();

        if ($granja === null) {
            $granja = new Granja();
            $granja->user_id = $userId;
            $granja->habitat_id = $habitatId;
            $granja->save();
        }

        return $granja;
    }
}
