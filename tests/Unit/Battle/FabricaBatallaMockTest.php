<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Posicion;
use Src\Battle\Infrastructure\FabricaBatallaMock;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Contrato de datos mock: protege los valores exactos que consume la UI
 * (stats, movimientos, objetos, posiciones, shiny) contra mutaciones.
 */
class FabricaBatallaMockTest extends TestCase
{
    private FabricaBatallaMock $fabrica;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fabrica = new FabricaBatallaMock();
    }

    public function test_generate_team1_contiene_gengar(): void
    {
        $gengar = $this->fabrica->generateTeam1()[0];

        $this->assertSame('player_1', $gengar->id);
        $this->assertSame('Gengar', $gengar->nombre);
        $this->assertSame(60, $gengar->hp);
        $this->assertSame(65, $gengar->atk);
        $this->assertSame(60, $gengar->def);
        $this->assertSame(130, $gengar->spAtk);
        $this->assertSame(75, $gengar->spDef);
        $this->assertSame(110, $gengar->speed);
        $this->assertSame([TipoPokemon::FANTASMA, TipoPokemon::VENENO], $gengar->tipos);
        $this->assertSame(Posicion::RETAGUARDIA, $gengar->posicion);
        $this->assertSame(['armor_pierce'], $gengar->effectKeys);
        $this->assertSame('life_orb', $gengar->item);
    }

    public function test_gengar_movimientos(): void
    {
        $gengar = $this->fabrica->generateTeam1()[0];

        $nombres = array_map(fn ($m) => $m->nombre, $gengar->moves);
        $this->assertSame(['Bola Sombra', 'Bomba Lodo', 'Rayo', 'Pulso Umbrío', 'Tóxico', 'Fuego Fatuo'], $nombres);

        $bolaSombra = $gengar->moves[0];
        $this->assertSame(80, $bolaSombra->potencia);
        $this->assertSame(TipoPokemon::FANTASMA, $bolaSombra->tipo);
        $this->assertSame(CategoriaMovimiento::ESPECIAL, $bolaSombra->categoria);

        $toxico = $gengar->moves[4];
        $this->assertSame(EstadoPokemon::POISON, $toxico->statusEffect);
        $this->assertSame(0, $toxico->potencia);

        $fuegoFatuo = $gengar->moves[5];
        $this->assertSame(EstadoPokemon::BURN, $fuegoFatuo->statusEffect);
    }

    public function test_generate_team1_contiene_giratina(): void
    {
        $giratina = $this->fabrica->generateTeam1()[1];

        $this->assertSame('player_2', $giratina->id);
        $this->assertSame('Giratina', $giratina->nombre);
        $this->assertSame(150, $giratina->hp);
        $this->assertSame(100, $giratina->atk);
        $this->assertSame(120, $giratina->def);
        $this->assertSame(100, $giratina->spAtk);
        $this->assertSame(120, $giratina->spDef);
        $this->assertSame(90, $giratina->speed);
        $this->assertSame([TipoPokemon::DRAGON], $giratina->tipos);
        $this->assertSame(Posicion::VANGUARDIA, $giratina->posicion);
        $this->assertTrue($giratina->shiny);
        $this->assertNull($giratina->item);
    }

    public function test_giratina_danza_espada_cambia_attack(): void
    {
        $giratina = $this->fabrica->generateTeam1()[1];

        $danzaEspada = $giratina->moves[2];
        $this->assertSame('Danza Espada', $danzaEspada->nombre);
        $this->assertSame(CategoriaMovimiento::ESTADO, $danzaEspada->categoria);
        $this->assertSame([['stat' => 'attack', 'stages' => 2]], $danzaEspada->selfStatChanges);
    }

    public function test_generate_team1_contiene_tyranitar(): void
    {
        $tyranitar = $this->fabrica->generateTeam1()[2];

        $this->assertSame('player_3', $tyranitar->id);
        $this->assertSame('Tyranitar', $tyranitar->nombre);
        $this->assertSame(100, $tyranitar->hp);
        $this->assertSame(134, $tyranitar->atk);
        $this->assertSame(110, $tyranitar->def);
        $this->assertSame(95, $tyranitar->spAtk);
        $this->assertSame(100, $tyranitar->spDef);
        $this->assertSame(61, $tyranitar->speed);
        $this->assertSame([TipoPokemon::SINIESTRO], $tyranitar->tipos);
        $this->assertSame(['sandstorm_summoner'], $tyranitar->effectKeys);
        $this->assertSame('leftovers', $tyranitar->item);
    }

    public function test_tyranitar_onda_trueno_paraliza(): void
    {
        $tyranitar = $this->fabrica->generateTeam1()[2];

        $ondaTrueno = $tyranitar->moves[3];
        $this->assertSame('Onda Trueno', $ondaTrueno->nombre);
        $this->assertSame(EstadoPokemon::PARALYSIS, $ondaTrueno->statusEffect);
        $this->assertSame(0, $ondaTrueno->potencia);
    }

    public function test_generate_team2_contiene_aggron(): void
    {
        $aggron = $this->fabrica->generateTeam2()[0];

        $this->assertSame('enemy_1', $aggron->id);
        $this->assertSame('Aggron', $aggron->nombre);
        $this->assertSame(70, $aggron->hp);
        $this->assertSame(110, $aggron->atk);
        $this->assertSame(180, $aggron->def);
        $this->assertSame(60, $aggron->spAtk);
        $this->assertSame(60, $aggron->spDef);
        $this->assertSame(50, $aggron->speed);
        $this->assertSame([TipoPokemon::ACERO, TipoPokemon::ROCA], $aggron->tipos);
        $this->assertSame(['regen_def'], $aggron->effectKeys);
        $this->assertNull($aggron->item);
    }

    public function test_aggron_defensa_ferrea_cambia_defense(): void
    {
        $aggron = $this->fabrica->generateTeam2()[0];

        $defensaFerrea = $aggron->moves[3];
        $this->assertSame('Defensa Férrea', $defensaFerrea->nombre);
        $this->assertSame(CategoriaMovimiento::ESTADO, $defensaFerrea->categoria);
        $this->assertSame([['stat' => 'defense', 'stages' => 2]], $defensaFerrea->selfStatChanges);
    }

    public function test_generate_team2_contiene_deoxys(): void
    {
        $deoxys = $this->fabrica->generateTeam2()[1];

        $this->assertSame('enemy_2', $deoxys->id);
        $this->assertSame('Deoxys', $deoxys->nombre);
        $this->assertSame(50, $deoxys->hp);
        $this->assertSame(70, $deoxys->atk);
        $this->assertSame(160, $deoxys->def);
        $this->assertSame(70, $deoxys->spAtk);
        $this->assertSame(160, $deoxys->spDef);
        $this->assertSame(90, $deoxys->speed);
        $this->assertSame([TipoPokemon::PSIQUICO], $deoxys->tipos);
        $this->assertSame('deoxys-defense', $deoxys->iconName);
        $this->assertSame(['niebla_summoner'], $deoxys->effectKeys);
    }

    public function test_deoxys_psicorrayo_confunde(): void
    {
        $deoxys = $this->fabrica->generateTeam2()[1];

        $psicorrayo = $deoxys->moves[2];
        $this->assertSame('Psicorrayo', $psicorrayo->nombre);
        $this->assertSame(EstadoPokemon::CONFUSION, $psicorrayo->statusEffect);
    }

    public function test_generate_team2_contiene_mewtwo(): void
    {
        $mewtwo = $this->fabrica->generateTeam2()[2];

        $this->assertSame('enemy_3', $mewtwo->id);
        $this->assertSame('Mewtwo', $mewtwo->nombre);
        $this->assertSame(106, $mewtwo->hp);
        $this->assertSame(110, $mewtwo->atk);
        $this->assertSame(90, $mewtwo->def);
        $this->assertSame(154, $mewtwo->spAtk);
        $this->assertSame(90, $mewtwo->spDef);
        $this->assertSame(130, $mewtwo->speed);
        $this->assertSame(Posicion::RETAGUARDIA, $mewtwo->posicion);
        $this->assertSame('life_orb', $mewtwo->item);
    }

    public function test_mewtwo_paz_mental_sube_spatk_y_spdef(): void
    {
        $mewtwo = $this->fabrica->generateTeam2()[2];

        $pazMental = $mewtwo->moves[3];
        $this->assertSame('Paz Mental', $pazMental->nombre);
        $this->assertSame(CategoriaMovimiento::ESTADO, $pazMental->categoria);
        $this->assertSame([
            ['stat' => 'spAtk', 'stages' => 1],
            ['stat' => 'spDef', 'stages' => 1],
        ], $pazMental->selfStatChanges);
    }

    public function test_create_battle_cadena_completa(): void
    {
        $battle = $this->fabrica->createBattle();

        $this->assertSame('Tú', $battle->team1->name);
        $this->assertSame('Rival', $battle->team2->name);
        $this->assertCount(3, $battle->team1->combatants());
        $this->assertCount(3, $battle->team2->combatants());

        $gengar = $battle->team1->combatants()[0];
        $this->assertSame('Gengar', $gengar->nombre());
        $this->assertSame('life_orb', $gengar->item());
    }
}
