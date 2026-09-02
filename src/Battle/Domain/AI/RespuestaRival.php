<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Illuminate\Support\Collection;
use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Genera las acciones más peligrosas que el rival podría ejecutar en respuesta.
 * Dado un estado de batalla, retorna las acciones candidatas del rival ordenadas por daño estimado.
 */
class RespuestaRival
{
    public function __construct(
        private readonly CalculadoraDanioIA $calculadoraDanio,
    ) {
    }

    /**
     * Genera las respuestas más peligrosas del rival contra el equipo del actor.
     *
     * @return Collection<int, AccionBatalla> Máximo 3 acciones más peligrosas
     */
    public function generarRespuestas(
        AgregadoBatalla $estadoSimulado,
        Combatiente $actorQueActuo,
        string $equipoActor,
    ): Collection {
        $equipoEnemigo = $equipoActor === 'team1'
            ? $estadoSimulado->team2
            : $estadoSimulado->team1;

        $equipoAliado = $equipoActor === 'team1'
            ? $estadoSimulado->team1
            : $estadoSimulado->team2;

        $enemigosVivos = collect($equipoEnemigo->combatientesVivos());
        $aliadosVivos = collect($equipoAliado->combatientesVivos());

        if ($enemigosVivos->isEmpty() || $aliadosVivos->isEmpty()) {
            return new Collection();
        }

        $accionesPeligrosas = new Collection();

        foreach ($enemigosVivos as $enemigo) {
            foreach ($aliadosVivos as $aliado) {
                if (! $aliado->estaVivo()) {
                    continue;
                }

                foreach ($enemigo->pokemon()->moves() as $movimiento) {
                    if (! $movimiento instanceof MovimientoBatalla) {
                        continue;
                    }

                    $defenderTeamHasVanguard = $equipoAliado->tieneVanguardiaViva();

                    $accionesPeligrosas->add(new AccionBatalla(
                        attacker: $enemigo,
                        defender: $aliado,
                        move: $movimiento,
                        fromPosition: $enemigo->posicion(),
                        defenderTeamHasVanguard: $defenderTeamHasVanguard,
                        weather: $estadoSimulado->weather(),
                    ));
                }
            }
        }

        // Ordenar por daño estimado descendente, tomar top 3
        return $accionesPeligrosas
            ->sortByDesc(function (AccionBatalla $accion) use ($estadoSimulado) {
                if ($accion->move->esEstado()) {
                    return 0.0;
                }

                $estimacion = $this->calculadoraDanio->estimar(
                    $accion->attacker,
                    $accion->defender,
                    $accion->move,
                    $estadoSimulado,
                );

                return $estimacion->esperado;
            })
            ->take(3)
            ->values();
    }
}
