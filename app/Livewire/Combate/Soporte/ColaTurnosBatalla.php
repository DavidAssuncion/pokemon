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
            fn (Combatiente $c) => $this->posicion($c, $team1, $team2) ?? ['team' => 0, 'index' => 0],
            $pendientes,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $team1
     * @param  list<array<string, mixed>>  $team2
     * @return array{team: int, index: int}|null
     */
    private function posicion(Combatiente $target, array $team1, array $team2): ?array
    {
        foreach ($team1 as $i => $p) {
            if ($p['refId'] === $target->id()) {
                return ['team' => 0, 'index' => $i];
            }
        }
        foreach ($team2 as $i => $p) {
            if ($p['refId'] === $target->id()) {
                return ['team' => 1, 'index' => $i];
            }
        }

        return null;
    }
}
