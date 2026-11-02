<?php

declare(strict_types=1);

namespace Src\Mazmorras\App;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\CombateEntrenadores\App\OtorgarRecompensasEntrenador;
use Src\CombateEntrenadores\Domain\DataTransferObjects\DatosModalVictoria;

/**
 * Recompensas de victoria en mazmorra: caramelos ×70 y EXP ×10 (decisión de
 * producto 2026-10-31). Reutiliza el otorgamiento del módulo de entrenadores
 * con multiplicadores separados para no inflar la EXP con el factor de
 * caramelos.
 */
final class OtorgarRecompensasMazmorra
{
    public function __construct(
        private readonly OtorgarRecompensasEntrenador $otorgarRecompensas,
    ) {
    }

    public function otorgar(int $userId, int $teamId, AgregadoBatalla $battle, int $nivel): ?DatosModalVictoria
    {
        $speciesRival = [];
        foreach ($battle->team2->combatientesCollection() as $combatiente) {
            if ($combatiente instanceof Combatiente) {
                $speciesRival[] = $combatiente->speciesId();
            }
        }

        return $this->otorgarRecompensas->otorgar(
            userId: $userId,
            teamId: $teamId,
            speciesIdsRival: $speciesRival,
            nivelEntrenador: $nivel,
            multiplicador: 70.0,
            multiplicadorExp: 10.0,
        );
    }
}
