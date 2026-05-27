<?php

namespace Tests\Feature;

use Src\Battle\Domain\BattleAggregate;
use Src\Battle\Domain\BattleSrv;
use Src\Pokemon\Domain\PokemonEntity;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TipoPokemon;
use Src\Shared\Tipos\TiposCollection;
use Tests\TestCase;

class PokemonBattleTest extends TestCase
{
    public BattleAggregate $battleAggregate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->battleAggregate = new BattleAggregate(
            new BattleSrv(),
            null,
            $this->generarPokemonTest1(),
            $this->generarPokemonTest2()
        );
    }

    public function test_battle(): void
    {
        $this->battleAggregate->battleSrv->atacar(
            $this->battleAggregate->pokemon,
            $this->battleAggregate->pokemonRival,
            $this->battleAggregate->pokemon->moves
        );
        $this->battleAggregate->battleSrv->atacar(
            $this->battleAggregate->pokemonRival,
            $this->battleAggregate->pokemon,
            $this->battleAggregate->pokemonRival->moves
        );
        $this->assertTrue(true);
    }

    private function generarPokemonTest1(): PokemonEntity
    {
        return new PokemonEntity(
            new StatsValue(80, 100, 123, 122, 120, 80),
            new StatsValue(),
            [
                'nombre' => 'Planta',
                'tipo' => 'Planta',
                'potencia' => 90,
            ],
            new TiposCollection([TipoPokemon::PLANTA, TipoPokemon::VENENO])
        );
    }

    private function generarPokemonTest2(): PokemonEntity
    {
        return new PokemonEntity(
            new StatsValue(79, 83, 100, 85, 105, 78),
            new StatsValue(),
            [
                'nombre' => 'Agua',
                'tipo' => 'Agua',
                'potencia' => 90,
            ],
            new TiposCollection([TipoPokemon::AGUA])
        );
    }
}
