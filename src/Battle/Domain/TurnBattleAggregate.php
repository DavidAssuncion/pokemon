<?php

namespace Src\Battle\Domain;

use Src\Battle\Domain\Chain\DamageChain;
use Src\Battle\Domain\Observer\BattleSubject;

class TurnBattleAggregate
{
    public TurnManager $turnManager;
    public DamageChain $damageChain;
    public BattleSubject $subject;
    public array $log = [];
    public ?array $pendingAction = null;

    public function __construct(
        public readonly BattleTeam $team1,
        public readonly BattleTeam $team2,
    ) {
        $this->turnManager = new TurnManager($team1, $team2);
        $this->damageChain = new DamageChain();
        $this->subject = new BattleSubject();
    }

    public function ejecutarBatalla(): array
    {
        $this->log('¡Comienza la batalla!');

        while ($this->turnManager->bothTeamsAlive()) {
            $this->turnManager->startNewRound();
            $this->log("--- Ronda {$this->turnManager->round} ---");

            while ($this->turnManager->hayAlgunoConAccionPendiente() && $this->turnManager->bothTeamsAlive()) {
                $actor = $this->turnManager->getNextActor();

                if ($actor === null) {
                    break;
                }

                $this->ejecutarAccion($actor);
                $this->turnManager->consumeAction($actor);
            }

            foreach ($this->turnManager->aliveCombatants() as $c) {
                $this->subject->notifyEndTurn($c);
            }
        }

        $winner = !$this->team1->allFainted() ? $this->team1->name : $this->team2->name;
        $this->log("¡{$winner} gana la batalla!");

        return $this->log;
    }

    private function ejecutarAccion(Combatant $actor): void
    {
        $objetivo = $this->elegirObjetivo($actor);

        if ($objetivo === null) {
            return;
        }

        $movimiento = $this->chooseBestMove($actor, $objetivo);

        if ($movimiento === null) {
            return;
        }

        $enemigo = $actor->estaEnVanguardia() ? $this->team2 : $this->team1;
        $defenderTeamHasVanguard = $enemigo->tieneVanguardiaViva();

        $action = new BattleAction(
            attacker: $actor,
            defender: $objetivo,
            move: $movimiento,
            fromPosition: $actor->position,
            defenderTeamHasVanguard: $defenderTeamHasVanguard,
        );

        $daño = $this->damageChain->calculate($action);

        $isSpecial = $movimiento->esEspecial();
        $dañoReal = $objetivo->recibirDaño($daño, $isSpecial);

        $this->log(
            "{$actor->pokemon->battleStats->speed}vel [{$actor->pokemon->stats->hp}hp] "
            . "ataca a [{$objetivo->pokemon->stats->hp}hp] "
            . "con {$movimiento->nombre} -> {$dañoReal} de daño"
        );

        $this->subject->notifyDamaged($objetivo, $daño);

        if (!$objetivo->isAlive()) {
            $this->log("¡[{$objetivo->pokemon->stats->hp}hp] ha debilitado!");
            $this->subject->notifyFainted($objetivo);
        }
    }

    private function elegirObjetivo(Combatant $actor): ?Combatant
    {
        $enemigo = $actor->position === Position::VANGUARDIA
            ? $this->team2
            : $this->team1;
        $aliado = $actor->position === Position::VANGUARDIA
            ? $this->team1
            : $this->team2;

        if ($actor->estaEnVanguardia()) {
            $vanguardiaEnemiga = $enemigo->vanguardiaAlive();

            if (!empty($vanguardiaEnemiga)) {
                return $vanguardiaEnemiga[array_rand($vanguardiaEnemiga)];
            }

            $retaguardiaEnemiga = $enemigo->retaguardiaAlive();

            if (!empty($retaguardiaEnemiga)) {
                return $retaguardiaEnemiga[array_rand($retaguardiaEnemiga)];
            }
        }

        if ($actor->estaEnRetaguardia()) {
            $todosEnemigos = $enemigo->aliveCombatants();

            if (!empty($todosEnemigos)) {
                return $todosEnemigos[array_rand($todosEnemigos)];
            }
        }

        $todosEnemigos = $enemigo->aliveCombatants();

        return !empty($todosEnemigos) ? $todosEnemigos[array_rand($todosEnemigos)] : null;
    }

    public function chooseBestMove(Combatant $attacker, Combatant $defender): ?BattleMove
    {
        if (empty($attacker->pokemon->moves)) {
            return new BattleMove('Placaje', 40, \Src\Shared\Tipos\TipoPokemon::NORMAL, 'fisico');
        }

        $best = null;
        $bestScore = -1;

        foreach ($attacker->pokemon->moves as $move) {
            if ($move instanceof BattleMove) {
                $efectividad = $move->tipo->effectiveness($defender->pokemon);
                $score = $efectividad * $move->potencia;

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $move;
                }
            }
        }

        return $best;
    }

    private function log(string $message): void
    {
        $this->log[] = $message;
    }
}
