<?php

namespace App\Livewire;

use Livewire\Component;
use Src\Battle\Domain\BattleAction;
use Src\Battle\Domain\BattleMove;
use Src\Battle\Domain\BattlePokemonData;
use Src\Battle\Domain\BattleTeam;
use Src\Battle\Domain\Combatant;
use Src\Battle\Domain\Position;
use Src\Battle\Domain\TurnBattleAggregate;
use Src\Shared\Tipos\TipoPokemon;

class Combate extends Component
{
    public string $battleId = '';
    public array $team1 = [];
    public array $team2 = [];
    public array $turnQueue = [];
    public array $currentMoves = [];
    public ?int $selectedMoveIdx = null;
    public string $phase = 'init';
    public int $round = 0;
    public array $log = [];
    public string $actingRefId = '';
    public bool $processing = false;
    public string $animAttackerId = '';
    public string $animDefenderId = '';
    public string $animAttackerNombre = '';
    public string $animDefenderNombre = '';
    public string $animMoveNombre = '';
    public int $animTick = 0;
    public ?int $selectedTargetTeam = null;
    public ?int $selectedTargetIdx = null;
    public string $selectedTargetRefId = '';

    public function mount(): void
    {
        $this->battleId = 'battle_' . uniqid();
        try {
            $this->initMockBattle();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Combate mount error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->log[] = 'Error: ' . $e->getMessage();
        }
    }

    private function getBattle(): ?TurnBattleAggregate
    {
        $data = session($this->battleId);
        if ($data === null) {
            return null;
        }
        return unserialize($data);
    }

    private function saveBattle(TurnBattleAggregate $battle): void
    {
        session()->put($this->battleId, serialize($battle));
    }

    private function initMockBattle(): void
    {
        $team1Data = $this->generateMockTeam1();
        $team2Data = $this->generateMockTeam2();

        $team1 = BattleTeam::fromData($team1Data, 'Tú');
        $team2 = BattleTeam::fromData($team2Data, 'Rival');

        $battle = new TurnBattleAggregate($team1, $team2);
        $this->saveBattle($battle);

        $this->syncViewData($battle);
        $this->log[] = '¡Comienza la batalla!';
        $this->nextActor();
    }

    public function startBattle(): void
    {
        $this->nextActor();
    }

    public function nextActor(): void
    {
        $battle = $this->getBattle();
        if ($battle === null) { return; }

        if (!$battle->turnManager->bothTeamsAlive()) {
            $this->endBattle($battle);
            return;
        }

        if (!$battle->turnManager->hayAlgunoConAccionPendiente()) {
            $battle->turnManager->startNewRound();
            $this->round = $battle->turnManager->round;
            $this->log[] = "--- Ronda {$this->round} ---";
        }

        $actor = $battle->turnManager->getNextActor();
        if ($actor === null) {
            $battle->turnManager->startNewRound();
            $this->round = $battle->turnManager->round;
            $this->log[] = "--- Ronda {$this->round} ---";
            $actor = $battle->turnManager->getNextActor();
            if ($actor === null) {
                $this->endBattle($battle);
                return;
            }
        }

        $this->actingRefId = $actor->id;
        $this->syncViewData($battle, $actor);
        $actorView = $this->findPokemonViewData($actor);

        if ($actorView === null) {
            $this->saveBattle($battle);
            $this->processing = false;
            return;
        }

        $isPlayer = $actorView['team'] === 0;

        if ($isPlayer) {
            $this->currentMoves = array_map(
                fn(BattleMove $m) => $m->toLivewire(),
                $actor->pokemon->moves
            );
            $this->phase = 'player_target';
            $this->processing = false;
        } else {
            $this->processing = true;
            $this->prepareAiAnimation($battle, $actor);
            return;
        }

        $this->turnQueue = $this->buildTurnQueue($battle);
        $this->saveBattle($battle);
    }

    private function endBattle(TurnBattleAggregate $battle): void
    {
        $this->phase = 'battle_over';
        $winner = !$battle->team1->allFainted() ? $battle->team1->name : $battle->team2->name;
        $this->log[] = "¡{$winner} gana la batalla!";
        $this->syncViewData($battle);
        $this->saveBattle($battle);
        $this->processing = false;
        $this->animAttackerId = '';
        $this->animDefenderId = '';
    }

    private function prepareAiAnimation(TurnBattleAggregate $battle, Combatant $actor): void
    {
        $objetivo = $this->chooseAITarget($battle, $actor);
        if ($objetivo === null) { return; }

        $movimiento = $battle->chooseBestMove($actor, $objetivo);
        if ($movimiento === null) { return; }

        $battle->pendingAction = [
            'attackerId' => $actor->id,
            'defenderId' => $objetivo->id,
            'attackerNombre' => $actor->nombre,
            'move' => serialize($movimiento),
        ];

        $this->animTick++;
        $this->animAttackerId = $actor->id;
        $this->animDefenderId = $objetivo->id;
        $this->animAttackerNombre = $actor->nombre;
        $this->animDefenderNombre = $objetivo->nombre;
        $this->animMoveNombre = $movimiento->nombre;
        $this->phase = 'animating';

        $this->syncViewData($battle, $actor);
        $this->saveBattle($battle);
    }

    public function commitAction(): void
    {
        $battle = $this->getBattle();
        if ($battle === null) { return; }

        $pending = $battle->pendingAction;
        if ($pending === null) { return; }

        $actor = $battle->team1->findCombatantById($pending['attackerId'])
            ?? $battle->team2->findCombatantById($pending['attackerId']);
        $objetivo = $battle->team1->findCombatantById($pending['defenderId'])
            ?? $battle->team2->findCombatantById($pending['defenderId']);

        if ($actor === null || $objetivo === null) {
            $battle->pendingAction = null;
            $this->saveBattle($battle);
        $this->animAttackerId = '';
        $this->animDefenderId = '';
        $this->selectedTargetTeam = null;
        $this->selectedTargetIdx = null;
        $this->selectedTargetRefId = '';
        $this->selectedTargetTeam = null;
        $this->selectedTargetIdx = null;
        $this->selectedTargetRefId = '';
            $this->selectedTargetRefId = '';
            $this->nextActor();
            return;
        }

        $movimiento = unserialize($pending['move']);

        $defenderTeam = $this->defenderTeam($battle, $objetivo);
        $action = new BattleAction(
            attacker: $actor,
            defender: $objetivo,
            move: $movimiento,
            fromPosition: $actor->position,
            defenderTeamHasVanguard: $defenderTeam->tieneVanguardiaViva(),
        );

        $daño = $battle->damageChain->calculate($action);
        $isAi = $battle->team2->findCombatantById($pending['attackerId']) !== null;
        $prefix = $isAi ? 'RIVAL: ' : '';
        $logMsg = "{$prefix}{$actor->nombre} usa {$movimiento->nombre} → {$daño} de daño a {$objetivo->nombre}";
        if ($objetivo->estaEnRetaguardia() && $defenderTeam->tieneVanguardiaViva()) {
            $logMsg .= ' (-50% retaguardia)';
        }
        $objetivo->recibirDaño($daño, $movimiento->esEspecial());

        $this->log[] = $logMsg;
        $battle->subject->notifyDamaged($objetivo, $daño);
        $battle->turnManager->consumeAction($actor);

        if (!$objetivo->isAlive()) {
            $this->log[] = "¡{$objetivo->nombre} se ha debilitado!";
            $battle->subject->notifyFainted($objetivo);
        }

        $battle->pendingAction = null;
        $this->animAttackerId = '';
        $this->animDefenderId = '';
        $this->animAttackerNombre = '';
        $this->animDefenderNombre = '';
        $this->animMoveNombre = '';
        $this->selectedTargetTeam = null;
        $this->selectedTargetIdx = null;

        $this->syncViewData($battle, $actor);
        $this->saveBattle($battle);
        $this->nextActor();
    }

    public function previewTarget(int $teamIdx, int $pokemonIdx): void
    {
        $battle = $this->getBattle();
        if ($battle === null) { return; }

        $actor = $this->getCurrentActor($battle);
        if ($actor === null) { return; }

        $target = $this->getTargetFromSelection($battle, $teamIdx, $pokemonIdx);
        if ($target === null) { return; }

        $this->selectedTargetTeam = $teamIdx;
        $this->selectedTargetIdx = $pokemonIdx;
        $this->selectedTargetRefId = $target->id;

        $previews = [];
        foreach ($actor->pokemon->moves as $move) {
            $defenderTeam = $this->defenderTeam($battle, $target);
            $action = new BattleAction(
                attacker: $actor,
                defender: $target,
                move: $move,
                fromPosition: $actor->position,
                defenderTeamHasVanguard: $defenderTeam->tieneVanguardiaViva(),
            );
            $daño = $battle->damageChain->calculate($action);

            $efectividad = $move->tipo->effectiveness($target->pokemon);
            $tieneStab = false;
            foreach ($actor->pokemon->tiposCollection as $tipo) {
                if ($tipo === $move->tipo) {
                    $tieneStab = true;
                    break;
                }
            }

            $previews[] = [
                'nombre' => $move->nombre,
                'tipo' => $move->tipo->value,
                'potencia' => $move->potencia,
                'categoria' => $move->categoria,
                'daño' => $daño,
                'efectividad' => $efectividad,
                'stab' => $tieneStab,
            ];
        }

        $this->currentMoves = $previews;
        $this->phase = 'player_move';
        $this->saveBattle($battle);
    }

    public function selectMove(int $index): void
    {
        $battle = $this->getBattle();
        if ($battle === null) { return; }

        $actor = $this->getCurrentActor($battle);
        if ($actor === null) { return; }

        $target = $this->getTargetFromSelection($battle, $this->selectedTargetTeam, $this->selectedTargetIdx);
        if ($target === null) { return; }

        $move = $actor->pokemon->moves[$index] ?? null;
        if (!$move instanceof BattleMove) { return; }

        $battle->pendingAction = [
            'attackerId' => $actor->id,
            'defenderId' => $target->id,
            'attackerNombre' => $actor->nombre,
            'move' => serialize($move),
        ];

        $this->selectedMoveIdx = null;
        $this->selectedTargetTeam = null;
        $this->selectedTargetIdx = null;
        $this->selectedTargetRefId = '';
        $this->animTick++;
        $this->animAttackerId = $actor->id;
        $this->animDefenderId = $target->id;
        $this->animAttackerNombre = $actor->nombre;
        $this->animDefenderNombre = $target->nombre;
        $this->animMoveNombre = $move->nombre;
        $this->phase = 'animating';

        $this->syncViewData($battle, $actor);
        $this->saveBattle($battle);
    }

    public function cancelTarget(): void
    {
        $this->selectedTargetTeam = null;
        $this->selectedTargetIdx = null;
        $this->selectedTargetRefId = '';
        $this->phase = 'player_target';
    }

    private function chooseAITarget(TurnBattleAggregate $battle, Combatant $actor): ?Combatant
    {
        $enemigo = $battle->team1->findCombatant($actor) !== null
            ? $battle->team2
            : $battle->team1;

        if ($actor->estaEnVanguardia()) {
            $vanguardia = $enemigo->vanguardiaAlive();
            if (!empty($vanguardia)) {
                return $vanguardia[array_rand($vanguardia)];
            }
            $retaguardia = $enemigo->retaguardiaAlive();
            if (!empty($retaguardia)) {
                return $retaguardia[array_rand($retaguardia)];
            }
        }

        if ($actor->estaEnRetaguardia()) {
            $todos = $enemigo->aliveCombatants();
            if (!empty($todos)) {
                return $todos[array_rand($todos)];
            }
        }

        $todos = $enemigo->aliveCombatants();
        return !empty($todos) ? $todos[array_rand($todos)] : null;
    }

    private function getCurrentActor(TurnBattleAggregate $battle): ?Combatant
    {
        $maxSpeed = -1;
        $selected = null;

        foreach ($battle->turnManager->aliveCombatants() as $c) {
            if ($c->accumulatedSpeed > $maxSpeed) {
                $maxSpeed = $c->accumulatedSpeed;
                $selected = $c;
            }
        }

        return $selected;
    }

    private function getTargetFromSelection(TurnBattleAggregate $battle, int $teamIdx, int $pokemonIdx): ?Combatant
    {
        $team = $teamIdx === 0 ? $battle->team1 : $battle->team2;
        return $team->combatants[$pokemonIdx] ?? null;
    }

    private function findPokemonViewData(Combatant $target): ?array
    {
        foreach ($this->team1 as $i => $p) {
            if ($p['refId'] === $target->id) {
                return ['team' => 0, 'index' => $i];
            }
        }
        foreach ($this->team2 as $i => $p) {
            if ($p['refId'] === $target->id) {
                return ['team' => 1, 'index' => $i];
            }
        }
        return null;
    }

    private function syncViewData(TurnBattleAggregate $battle, ?Combatant $actor = null): void
    {
        $this->team1 = array_map(
            fn(Combatant $c) => $c->toViewArray(0),
            $battle->team1->combatants
        );

        $this->team2 = array_map(
            fn(Combatant $c) => $c->toViewArray(1),
            $battle->team2->combatants
        );

        if ($actor === null) return;

        $isPlayer = $battle->team1->findCombatant($actor) !== null;
        if (!$isPlayer) return;

        $canHitRetaguardia = !$actor->estaEnVanguardia() || !$battle->team2->tieneVanguardiaViva();

        foreach ($this->team2 as &$p) {
            $p['canTarget'] = $p['posicion'] !== 'retaguardia' || $canHitRetaguardia;
        }
        unset($p);
    }

    private function buildTurnQueue(TurnBattleAggregate $battle): array
    {
        $alive = $battle->turnManager->aliveCombatants();

        usort($alive, fn(Combatant $a, Combatant $b) =>
            $b->accumulatedSpeed <=> $a->accumulatedSpeed
        );

        $queue = [];
        foreach ($alive as $c) {
            $teamIdx = 0;
            $idx = array_search($c, $battle->team1->combatants, true);
            if ($idx === false) {
                $teamIdx = 1;
                $idx = array_search($c, $battle->team2->combatants, true);
            }
            $queue[] = ['team' => $teamIdx, 'index' => $idx !== false ? $idx : 0];
        }

        return $queue;
    }

    private function defenderTeam(TurnBattleAggregate $battle, Combatant $defender): BattleTeam
    {
        return $battle->team1->findCombatant($defender) !== null
            ? $battle->team1
            : $battle->team2;
    }

    /** @return BattlePokemonData[] */
    private function generateMockTeam1(): array
    {
        return [
            new BattlePokemonData(
                id: 'player_1', nombre: 'Gengar',
                hp: 60, atk: 65, def: 60, spAtk: 130, spDef: 75, speed: 110,
                tipos: [TipoPokemon::FANTASMA], posicion: Position::RETAGUARDIA,
                moves: [
                    new BattleMove('Bola Sombra', 80, TipoPokemon::FANTASMA, 'especial'),
                    new BattleMove('Bomba Lodo', 90, TipoPokemon::VENENO, 'especial'),
                    new BattleMove('Rayo', 90, TipoPokemon::ELECTRICO, 'especial'),
                    new BattleMove('Pulso Umbrío', 80, TipoPokemon::SINIESTRO, 'especial'),
                ],
            ),
            new BattlePokemonData(
                id: 'player_2', nombre: 'Giratina',
                hp: 150, atk: 100, def: 120, spAtk: 100, spDef: 120, speed: 90,
                tipos: [TipoPokemon::DRAGON], posicion: Position::VANGUARDIA,
                moves: [
                    new BattleMove('Garra Umbría', 80, TipoPokemon::FANTASMA, 'fisico'),
                    new BattleMove('Cometa Draco', 130, TipoPokemon::DRAGON, 'especial'),
                    new BattleMove('Esfera Aural', 80, TipoPokemon::LUCHA, 'especial'),
                    new BattleMove('Tierra Viva', 90, TipoPokemon::TIERRA, 'especial'),
                ],
                shiny: true,
            ),
            new BattlePokemonData(
                id: 'player_3', nombre: 'Tyranitar',
                hp: 100, atk: 134, def: 110, spAtk: 95, spDef: 100, speed: 61,
                tipos: [TipoPokemon::SINIESTRO], posicion: Position::VANGUARDIA,
                moves: [
                    new BattleMove('Roca Afilada', 100, TipoPokemon::ROCA, 'fisico'),
                    new BattleMove('Triturar', 80, TipoPokemon::SINIESTRO, 'fisico'),
                    new BattleMove('Terremoto', 100, TipoPokemon::TIERRA, 'fisico'),
                    new BattleMove('Puño Hielo', 75, TipoPokemon::HIELO, 'fisico'),
                ],
                shiny: true,
            ),
        ];
    }

    /** @return BattlePokemonData[] */
    private function generateMockTeam2(): array
    {
        return [
            new BattlePokemonData(
                id: 'enemy_1', nombre: 'Aggron',
                hp: 70, atk: 110, def: 180, spAtk: 60, spDef: 60, speed: 50,
                tipos: [TipoPokemon::ACERO, TipoPokemon::ROCA], posicion: Position::VANGUARDIA,
                moves: [
                    new BattleMove('Cabeza de Hierro', 80, TipoPokemon::ACERO, 'fisico'),
                    new BattleMove('Roca Afilada', 100, TipoPokemon::ROCA, 'fisico'),
                    new BattleMove('Terremoto', 100, TipoPokemon::TIERRA, 'fisico'),
                    new BattleMove('Puño Trueno', 75, TipoPokemon::ELECTRICO, 'fisico'),
                ],
            ),
            new BattlePokemonData(
                id: 'enemy_2', nombre: 'Deoxys',
                hp: 50, atk: 70, def: 160, spAtk: 70, spDef: 160, speed: 90,
                tipos: [TipoPokemon::PSIQUICO], posicion: Position::VANGUARDIA,
                moves: [
                    new BattleMove('Psíquico', 90, TipoPokemon::PSIQUICO, 'especial'),
                    new BattleMove('Rayo', 90, TipoPokemon::ELECTRICO, 'especial'),
                    new BattleMove('Rayo Hielo', 90, TipoPokemon::HIELO, 'especial'),
                    new BattleMove('Pulso Umbrío', 80, TipoPokemon::SINIESTRO, 'especial'),
                ],
                iconName: 'deoxys-defense',
            ),
            new BattlePokemonData(
                id: 'enemy_3', nombre: 'Mewtwo',
                hp: 106, atk: 110, def: 90, spAtk: 154, spDef: 90, speed: 130,
                tipos: [TipoPokemon::PSIQUICO], posicion: Position::RETAGUARDIA,
                moves: [
                    new BattleMove('Psíquico', 90, TipoPokemon::PSIQUICO, 'especial'),
                    new BattleMove('Esfera Aural', 80, TipoPokemon::LUCHA, 'especial'),
                    new BattleMove('Llamarada', 110, TipoPokemon::FUEGO, 'especial'),
                    new BattleMove('Rayo', 90, TipoPokemon::ELECTRICO, 'especial'),
                ],
            ),
        ];
    }

    public function render()
    {
        return view('livewire.combate')
            ->extends('layouts.app')
            ->section('content');
    }
}
