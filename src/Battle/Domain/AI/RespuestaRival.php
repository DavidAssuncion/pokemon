<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

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
     * @return AccionBatalla[] Máximo 3 acciones más peligrosas
     */
    public function generarRespuestas(
        AgregadoBatalla $estadoSimulado,
        Combatiente $actorQueActuo,
        string $equipoActor,
    ): array {
        $equipoEnemigo = $equipoActor === 'team1'
            ? $estadoSimulado->team2
            : $estadoSimulado->team1;

        $equipoAliado = $equipoActor === 'team1'
            ? $estadoSimulado->team1
            : $estadoSimulado->team2;

        $enemigosVivos = $equipoEnemigo->combatientesVivos();
        $aliadosVivos = $equipoAliado->combatientesVivos();

        if ($enemigosVivos === [] || $aliadosVivos === []) {
            return [];
        }

        $accionesPeligrosas = [];

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

                    $accionesPeligrosas[] = new AccionBatalla(
                        attacker: $enemigo,
                        defender: $aliado,
                        move: $movimiento,
                        fromPosition: $enemigo->posicion(),
                        defenderTeamHasVanguard: $defenderTeamHasVanguard,
                        weather: $estadoSimulado->weather(),
                    );
                }
            }
        }

        // Ordenar por daño estimado descendente, tomar top 3
        usort($accionesPeligrosas, function (AccionBatalla $a, AccionBatalla $b) use ($estadoSimulado) {
            $danoA = $a->move->esEstado() ? 0.0 : $this->estimarDano($a, $estadoSimulado);
            $danoB = $b->move->esEstado() ? 0.0 : $this->estimarDano($b, $estadoSimulado);

            return $danoB <=> $danoA;
        });

        return array_slice($accionesPeligrosas, 0, 3);
    }

    private function estimarDano(AccionBatalla $accion, AgregadoBatalla $estadoSimulado): float
    {
        if ($accion->move->esEstado()) {
            return 0.0;
        }

        return $this->calculadoraDanio->estimar(
            $accion->attacker,
            $accion->defender,
            $accion->move,
            $estadoSimulado,
        )->esperado;
    }
}
