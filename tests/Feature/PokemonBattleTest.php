<?php

declare(strict_types=1);

namespace Tests\Feature;

use Src\Battle\Domain\BattleAggregate;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\MovimientoBatalla;
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
            null,
            $this->generarPokemonTest1(),
            $this->generarPokemonTest2()
        );
    }

    public function test_battle_aggregate_creation(): void
    {
        $this->assertNotNull($this->battleAggregate->pokemon);
        $this->assertNotNull($this->battleAggregate->pokemonRival);
        $this->assertCount(1, $this->battleAggregate->pokemon->moves()->all());
        $this->assertCount(1, $this->battleAggregate->pokemonRival->moves()->all());
    }

    private function generarPokemonTest1(): PokemonEntity
    {
        return new PokemonEntity(
            new StatsValue(80, 100, 123, 122, 120, 80),
            new StatsValue(),
            [
                new MovimientoBatalla('Planta', 90, TipoPokemon::PLANTA, CategoriaMovimiento::ESPECIAL),
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
                new MovimientoBatalla('Agua', 90, TipoPokemon::AGUA, CategoriaMovimiento::ESPECIAL),
            ],
            new TiposCollection([TipoPokemon::AGUA])
        );
    }
}
