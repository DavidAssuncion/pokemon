<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Collections\CombatientesCollection;
use Src\Battle\Domain\Enums\StatClave;

class GestorTurnos
{
    private int $round = 0;

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
        }
    }

    /**
     * Siguiente combatiente vivo que aún no actuó en la ronda actual: el de
     * mayor velocidad efectiva (StatClave::VELOCIDAD, con multiplicadores y
     * parálisis). Los empates se resuelven por orden de inserción (team1 antes
     * que team2). null solo si ningún vivo tiene acción pendiente.
     */
    public function getNextActor(): ?Combatiente
    {
        $seleccionado = null;
        $mejorVelocidad = null;

        foreach ($this->combatientesVivos() as $candidato) {
            if ($candidato->vecesActuadoEstaRonda() !== 0) {
                continue;
            }

            $velocidad = $candidato->obtenerStatEfectivo(StatClave::VELOCIDAD);

            if ($mejorVelocidad === null || $velocidad > $mejorVelocidad) {
                $seleccionado = $candidato;
                $mejorVelocidad = $velocidad;
            }
        }

        return $seleccionado;
    }

    /**
     * Marca la acción del actor en la ronda actual. No altera velocidades:
     * cada combatiente vivo actúa exactamente una vez por ronda.
     */
    public function consumeAction(Combatiente $actor): void
    {
        $actor->setVecesActuadoEstaRonda($actor->vecesActuadoEstaRonda() + 1);
    }

    /**
     * ¿Queda algún combatiente vivo sin actuar en la ronda actual?
     */
    public function hayAlgunoConAccionPendiente(): bool
    {
        foreach ($this->combatientesVivos() as $c) {
            if ($c->vecesActuadoEstaRonda() === 0) {
                return true;
            }
        }

        return false;
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
