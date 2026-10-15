<?php

declare(strict_types=1);

namespace Tests\Feature;

use Src\Battle\Domain\FabricaBatallaInterface;
use Tests\TestCase;

/**
 * Test de batalla migrado de BattleAggregate (deprecated) a AgregadoBatalla + FabricaBatallaMock.
 * Verifica creación correcta y acceso a movimientos vía getter.
 */
class PokemonBattleTest extends TestCase
{
    public function test_battle_with_fabrica_mock_creates_2_teams_3_combatants(): void
    {
        $battle = $this->app->make(FabricaBatallaInterface::class)->createBattle();

        $this->assertCount(3, $battle->team1->combatants());
        $this->assertCount(3, $battle->team2->combatants());
        $this->assertSame('Tú', $battle->team1->name);
        $this->assertSame('Rival', $battle->team2->name);
    }

    public function test_combatants_have_moves_accessible_via_getter(): void
    {
        $battle = $this->app->make(FabricaBatallaInterface::class)->createBattle();

        $first = $battle->team1->combatants()[0];
        $this->assertNotEmpty($first->pokemon()->moves()->all());

        // Verificar que todos los combatientes tienen movimientos accesibles
        foreach ($battle->team1->combatants() as $combatant) {
            $this->assertNotEmpty($combatant->pokemon()->moves()->all());
        }

        foreach ($battle->team2->combatants() as $combatant) {
            $this->assertNotEmpty($combatant->pokemon()->moves()->all());
        }
    }
}
