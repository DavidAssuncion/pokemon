<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

/**
 * Ordena la cola de la ronda en curso. Sin estado: el actor actual y las
 * vistas de equipo se pasan como parámetros.
 */
final class ColaTurnosBatalla
{
    public function __construct(
        private readonly ResolutorPosicionCombatiente $posiciones,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $team1
     * @param  list<array<string, mixed>>  $team2
     * @return list<array{team: int, index: int}>
     */
    public function construir(
        AgregadoBatalla $battle,
        string $actorActualRefId,
        array $team1,
        array $team2,
    ): array {
        $pendientes = $battle->turnManager()->combatientesVivos()
            ->conAccionPendiente()
            ->all();

        $turnos = $battle->turnManager();
        $invertir = $battle->trickRoomActivo();

        usort($pendientes, function (Combatiente $a, Combatiente $b) use ($turnos, $invertir, $actorActualRefId): int {
            if ($a->id() === $actorActualRefId) {
                return -1;
            }
            if ($b->id() === $actorActualRefId) {
                return 1;
            }

            if ($a->turnoRetrasado() !== $b->turnoRetrasado()) {
                return $a->turnoRetrasado() ? 1 : -1;
            }

            $va = $turnos->velocidadEfectivaDe($a);
            $vb = $turnos->velocidadEfectivaDe($b);

            return $invertir ? $va <=> $vb : $vb <=> $va;
        });

        return array_map(
            fn (Combatiente $c) => $this->posiciones->localizar($c, $team1, $team2)?->aArray()
                ?? ['team' => 0, 'index' => 0],
            $pendientes,
        );
    }

}
