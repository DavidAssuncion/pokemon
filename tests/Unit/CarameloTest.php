<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Caramelo;
use App\Models\EvolutionChain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarameloTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_caramelo(): void
    {
        $chain = EvolutionChain::create(['data' => '{"stages": 3}']);

        $caramelo = Caramelo::create([
            'evolution_chain_id' => $chain->id,
            'cantidad' => 10,
        ]);

        $this->assertDatabaseHas('caramelos', [
            'evolution_chain_id' => $chain->id,
            'cantidad' => 10,
        ]);
        $this->assertEquals($chain->id, $caramelo->evolution_chain_id);
    }

    public function test_default_cantidad_is_zero(): void
    {
        $chain = EvolutionChain::create(['data' => '{"stages": 3}']);

        $caramelo = Caramelo::create([
            'evolution_chain_id' => $chain->id,
        ]);
        $caramelo->refresh();

        $this->assertEquals(0, $caramelo->cantidad);
    }

    public function test_unique_constraint_on_evolution_chain_id(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $chain = EvolutionChain::create(['data' => '{"stages": 3}']);

        Caramelo::create(['evolution_chain_id' => $chain->id]);
        Caramelo::create(['evolution_chain_id' => $chain->id]);
    }

    public function test_belongs_to_evolution_chain_relationship(): void
    {
        $chain = EvolutionChain::create(['data' => '{"stages": 3}']);

        $caramelo = Caramelo::create([
            'evolution_chain_id' => $chain->id,
            'cantidad' => 5,
        ]);

        $this->assertInstanceOf(EvolutionChain::class, $caramelo->evolutionChain);
        $this->assertEquals($chain->id, $caramelo->evolutionChain->id);
    }
}
