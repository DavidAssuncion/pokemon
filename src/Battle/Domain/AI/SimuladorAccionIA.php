<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\AI\ValueObjects\ResultadoSimulacion;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\ServicioEjecucionBatalla;

/**
 * Simula la ejecución de una acción sobre un clon del estado de batalla.
 * Retorna ResultadoSimulacion sin mutar la batalla original.
 */
class SimuladorAccionIA
{
    public function __construct(
        private readonly CadenaDanio $cadenaDanio,
    ) {
    }

    public function simular(AgregadoBatalla $batalla, AccionBatalla $accion): ResultadoSimulacion
    {
        $clon = $this->clonarBatalla($batalla);

        $atacante = $this->encontrarCombatiente($clon, $accion->attacker->id());
        $defensor = $this->encontrarCombatiente($clon, $accion->defender->id());

        if ($atacante === null || $defensor === null) {
            return new ResultadoSimulacion(
                estadoSimulado: $clon,
                danoInfligido: 0.0,
                objetivoDerrotado: false,
                actorId: $accion->attacker->id(),
                objetivoId: $accion->defender->id(),
            );
        }

        $accionClon = new AccionBatalla(
            attacker: $atacante,
            defender: $defensor,
            move: $accion->move,
            fromPosition: $atacante->posicion(),
            defenderTeamHasVanguard: $accion->defenderTeamHasVanguard,
            weather: $accion->weather,
        );

        $servicio = new ServicioEjecucionBatalla($this->cadenaDanio);
        $resultadoDanio = $servicio->calcularYAplicarDano($accionClon);

        $servicio->aplicarEstado($defensor, $accion->move);
        $servicio->aplicarStatChanges($atacante, $defensor, $accion->move);

        $dano = $resultadoDanio->dano;
        $objetivoDerrotado = ! $defensor->estaVivo();

        return new ResultadoSimulacion(
            estadoSimulado: $clon,
            danoInfligido: $dano,
            objetivoDerrotado: $objetivoDerrotado,
            actorId: $atacante->id(),
            objetivoId: $defensor->id(),
        );
    }

    private function clonarBatalla(AgregadoBatalla $batalla): AgregadoBatalla
    {
        return clone $batalla;
    }

    private function encontrarCombatiente(AgregadoBatalla $batalla, string $id): ?Combatiente
    {
        foreach ($batalla->team1->combatientesVivos() as $c) {
            if ($c->id() === $id) {
                return $c;
            }
        }
        foreach ($batalla->team2->combatientesVivos() as $c) {
            if ($c->id() === $id) {
                return $c;
            }
        }

        return null;
    }
}
