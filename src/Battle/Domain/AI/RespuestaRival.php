<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Collections\AccionesPosiblesCollection;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\Bando;
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
     * Máximo 3 acciones más peligrosas, ordenadas por daño estimado descendente.
     */
    public function generarRespuestas(
        AgregadoBatalla $estadoSimulado,
        Combatiente $actorQueActuo,
        Bando $equipoActor,
    ): AccionesPosiblesCollection {
        $equipoEnemigo = $equipoActor === Bando::UNO
            ? $estadoSimulado->team2
            : $estadoSimulado->team1;

        $equipoAliado = $equipoActor === Bando::UNO
            ? $estadoSimulado->team1
            : $estadoSimulado->team2;

        $enemigosVivos = $equipoEnemigo->combatientesCollection()->vivos();
        $aliadosVivos = $equipoAliado->combatientesCollection()->vivos();

        if ($enemigosVivos->isEmpty() || $aliadosVivos->isEmpty()) {
            return new AccionesPosiblesCollection();
        }

        /** @var list<array{accion: AccionBatalla, dano: float}> $accionesPeligrosas */
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

                    $accion = new AccionBatalla(
                        attacker: $enemigo,
                        defender: $aliado,
                        move: $movimiento,
                        defenderTeamHasVanguard: $equipoAliado->tieneVanguardiaViva(),
                        weather: $estadoSimulado->weather(),
                        terreno: $estadoSimulado->terreno(),
                    );

                    // El daño se estima UNA sola vez por acción: estimarDano
                    // recorre toda la cadena de daño y hacerlo dentro del
                    // comparador multiplicaba el coste por cada comparación
                    // del sort (decenas de miles de cálculos por decisión).
                    $accionesPeligrosas[] = [
                        'accion' => $accion,
                        'dano' => $this->estimarDano($accion, $estadoSimulado),
                    ];
                }
            }
        }

        // Ordenar por el daño precalculado (descendente) y tomar el top 3.
        usort(
            $accionesPeligrosas,
            static fn (array $a, array $b): int => $b['dano'] <=> $a['dano'],
        );

        return new AccionesPosiblesCollection(array_map(
            static fn (array $entrada): AccionBatalla => $entrada['accion'],
            array_slice($accionesPeligrosas, 0, 3),
        ));
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
