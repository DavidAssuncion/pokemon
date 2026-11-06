<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

/**
 * Resuelve combatientes a partir de referencias del componente o de la
 * selección del jugador. Sin estado: solo consulta el agregado.
 */
final class ResolutorActorBatalla
{
    public function porRefId(string $refId, AgregadoBatalla $battle): ?Combatiente
    {
        return $battle->team1->findCombatantById($refId)
            ?? $battle->team2->findCombatantById($refId);
    }

    /**
     * Resuelve el actor del jugador autorizado para acciones de objeto.
     * Lee del agregado (server-side); `actingRefId` no es fuente de verdad.
     * El fallback a `getNextActor()` solo cubre sesiones antiguas sin
     * `actorActualId`, incluido el actor forzado por `swapTurno()`.
     */
    public function jugadorAutorizado(AgregadoBatalla $battle): ?Combatiente
    {
        $actorId = $battle->actorActualId();

        if ($actorId === null) {
            $actorId = $battle->turnManager()->getNextActor()?->id();
        }

        if ($actorId === null) {
            return null;
        }

        $actor = $battle->team1->findCombatantById($actorId);

        if ($actor === null || ! $actor->estaVivo() || ! $actor->tieneAccionPendienteEstaRonda()) {
            return null;
        }

        return $actor;
    }

    public function objetivoPorSeleccion(AgregadoBatalla $battle, ?int $teamIdx, ?int $pokemonIdx): ?Combatiente
    {
        if ($teamIdx === null || $pokemonIdx === null) {
            return null;
        }

        $team = $teamIdx === 0 ? $battle->team1 : $battle->team2;

        return $team->combatants()->en($pokemonIdx);
    }
}
