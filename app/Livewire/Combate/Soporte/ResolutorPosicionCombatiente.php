<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate\Soporte\DTO\PosicionVistaBatalla;
use Src\Battle\Domain\Combatiente;

/**
 * Resuelve la posición de un combatiente (`team`, `index`) en las vistas
 * serializadas de ambos equipos. Sin estado.
 */
final class ResolutorPosicionCombatiente
{
    /**
     * @param  list<array<string, mixed>>  $team1
     * @param  list<array<string, mixed>>  $team2
     */
    public function localizar(Combatiente $target, array $team1, array $team2): ?PosicionVistaBatalla
    {
        foreach ($team1 as $index => $pokemon) {
            if ($pokemon['refId'] === $target->id()) {
                return new PosicionVistaBatalla(team: 0, index: $index);
            }
        }

        foreach ($team2 as $index => $pokemon) {
            if ($pokemon['refId'] === $target->id()) {
                return new PosicionVistaBatalla(team: 1, index: $index);
            }
        }

        return null;
    }
}
