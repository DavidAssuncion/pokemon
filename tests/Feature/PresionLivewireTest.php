<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Combate;
use App\Support\BattleSessionService;
use Livewire\Livewire;
use Src\Battle\Domain\Effects\EfectoDanioFinTurno;
use Src\Battle\Domain\Enums\ObjetivoEfecto;
use Src\Battle\Presentation\DTOAccionBatalla;
use Src\Battle\Presentation\DTOMovimientoBatalla;
use Tests\TestCase;

class PresionLivewireTest extends TestCase
{
    public function test_commit_action_refresca_las_barreras_tras_presion(): void
    {
        $component = Livewire::test(Combate::class);
        $battleId = $component->get('battleId');

        $session = $this->app->make(BattleSessionService::class);
        $battle = $session->cargar($battleId);
        $this->assertNotNull($battle);

        $portador = $battle->team1->combatientesCollection()->vivos()->en(0);
        $rival = $battle->team2->combatientesCollection()->vivos()->en(0);
        $this->assertNotNull($portador);
        $this->assertNotNull($rival);

        $maxFis = $rival->pokemon()->battleStats()->defenseHp;
        $rival->setDefensaHpActual($maxFis);
        $rival->setDefensaEspHpActual($rival->pokemon()->battleStats()->spDefenseHp);

        $portador->effects()->add(new EfectoDanioFinTurno(
            clave: 'presion',
            fraccion: 0.125,
            objetivo: ObjetivoEfecto::ESCUDOS_ENEMIGOS,
            afectaEscudos: true,
        ));

        foreach ($battle->turnManager()->allCombatants() as $c) {
            $battle->turnManager()->consumeAction($c);
        }
        $portador->setVecesActuadoEstaRonda(0);

        $movimiento = $portador->pokemon()->moves()->get(0);
        $this->assertNotNull($movimiento);
        $battle->setPendingAction(new DTOAccionBatalla(
            type: 'attack',
            actorId: $portador->id(),
            defenderId: $rival->id(),
            attackerNombre: $portador->nombre(),
            move: DTOMovimientoBatalla::desdeDominio($movimiento),
        ));

        $session->guardar($battleId, $battle);

        $component->call('commitAction');

        $team2 = $component->get('team2');
        $this->assertLessThan($maxFis, $team2[0]['defHp']);

        $eventos = $component->get('eventos');
        $this->assertNotEmpty($eventos);
        $fin = end($eventos);
        $this->assertSame('fin', $fin['tipo']);
        $this->assertArrayHasKey($rival->id(), $fin['estado']);
        $this->assertLessThan($maxFis, $fin['estado'][$rival->id()]['defHp']);
    }
}
