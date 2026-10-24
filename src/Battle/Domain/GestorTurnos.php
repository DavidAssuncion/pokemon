<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Collections\CombatientesCollection;

class GestorTurnos
{
    private int $round = 0;

    private bool $invertirVelocidad = false;

    private CombatientesCollection $teamA;

    private CombatientesCollection $teamB;

    public function __construct(
        public readonly EquipoBatalla $team1,
        public readonly EquipoBatalla $team2,
    ) {
        $this->teamA = $team1->combatientesCollection();
        $this->teamB = $team2->combatientesCollection();
    }

    /**
     * Todos los combatientes de ambos equipos (vivos y muertos).
     */
    public function allCombatants(): CombatientesCollection
    {
        $todos = new CombatientesCollection();
        foreach ($this->teamA as $c) {
            $todos->add($c);
        }
        foreach ($this->teamB as $c) {
            $todos->add($c);
        }

        return $todos;
    }

    /**
     * Solo los combatientes vivos de ambos equipos.
     */
    public function combatientesVivos(): CombatientesCollection
    {
        return $this->allCombatants()->vivos();
    }

    /**
     * Inicia una nueva ronda: incrementa el contador y resetea las acciones
     * consumidas, de modo que cada combatiente vivo pueda actuar una vez.
     */
    public function startNewRound(): void
    {
        $this->round++;

        foreach ($this->allCombatants() as $combatant) {
            $combatant->setVecesActuadoEstaRonda(0);
            $combatant->reiniciarAccionExtra();
            $combatant->reiniciarRetraso();
            $combatant->limpiarPrioridad();
            $combatant->setRondaActual($this->round);

            if ($combatant->estaVivo()) {
                $combatant->avanzarCargasMovimiento();
            }
        }

        $this->team1->normalizarCubridores();
        $this->team2->normalizarCubridores();
    }

    /**
     * Siguiente combatiente vivo que aún no actuó en la ronda actual: el de
     * mayor velocidad efectiva (con multiplicadores y parálisis). Los empates
     * se resuelven por orden de inserción (team1 antes que team2). null solo
     * si ningún vivo tiene acción pendiente.
     */
    public function getNextActor(): ?Combatiente
    {
        $normal = $this->seleccionarMasRapido(
            $this->combatientesVivos()->filter(
                static fn (Combatiente $c): bool => $c->vecesActuadoEstaRonda() === 0 && ! $c->turnoRetrasado(),
            ),
        );

        if ($normal !== null) {
            return $normal;
        }

        $retrasado = $this->seleccionarMasRapido(
            $this->combatientesVivos()->filter(
                static fn (Combatiente $c): bool => $c->vecesActuadoEstaRonda() === 0 && $c->turnoRetrasado(),
            ),
        );

        if ($retrasado !== null) {
            return $retrasado;
        }

        return $this->seleccionarMasRapido(
            $this->combatientesVivos()->filter(
                static fn (Combatiente $c): bool => $c->accionesExtra() > 0,
            ),
        );
    }

    private function seleccionarMasRapido(CombatientesCollection $candidatos): ?Combatiente
    {
        $seleccionado = null;
        $mejorVelocidad = null;

        foreach ($candidatos as $candidato) {
            $velocidad = $candidato->velocidadEfectiva()
                * ($this->equipoDe($candidato)?->multiplicadorVelocidad() ?? 1.0);

            $mejor = $mejorVelocidad === null
                || ($this->invertirVelocidad ? $velocidad < $mejorVelocidad : $velocidad > $mejorVelocidad);

            if ($mejor) {
                $seleccionado = $candidato;
                $mejorVelocidad = $velocidad;
            }
        }

        return $seleccionado;
    }

    public function setInvertirVelocidad(bool $invertir): void
    {
        $this->invertirVelocidad = $invertir;
    }

    public function velocidadEfectivaDe(Combatiente $combatiente): float
    {
        return $combatiente->velocidadEfectiva()
            * ($this->equipoDe($combatiente)?->multiplicadorVelocidad() ?? 1.0);
    }

    private function equipoDe(Combatiente $combatiente): ?EquipoBatalla
    {
        if ($this->team1->findCombatant($combatiente) !== null) {
            return $this->team1;
        }

        if ($this->team2->findCombatant($combatiente) !== null) {
            return $this->team2;
        }

        return null;
    }

    /**
     * Marca la acción del actor en la ronda actual. No altera velocidades:
     * cada combatiente vivo actúa exactamente una vez por ronda.
     */
    public function consumeAction(Combatiente $actor): void
    {
        $actor->limpiarRetraso();

        if ($actor->accionesExtra() > 0) {
            $actor->setAccionesExtra($actor->accionesExtra() - 1);

            return;
        }

        $actor->setVecesActuadoEstaRonda($actor->vecesActuadoEstaRonda() + 1);
    }

    /**
     * ¿Queda algún combatiente vivo sin actuar en la ronda actual?
     */
    public function hayAlgunoConAccionPendiente(): bool
    {
        return ! $this->combatientesVivos()->conAccionPendiente()->isEmpty();
    }

    public function bothTeamsAlive(): bool
    {
        return ! $this->team1->todosDebilitados() && ! $this->team2->todosDebilitados();
    }

    public function round(): int
    {
        return $this->round;
    }
}
