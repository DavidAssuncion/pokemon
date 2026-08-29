<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Caramelo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarameloTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_caramelo(): void
    {
        $caramelo = Caramelo::create([
            'evolution_chain_id' => 51,
            'cantidad' => 10,
        ]);

        $this->assertDatabaseHas('caramelos', [
            'evolution_chain_id' => 51,
            'cantidad' => 10,
        ]);
        $this->assertEquals(51, $caramelo->evolution_chain_id);
    }

    public function test_default_cantidad_is_zero(): void
    {
        $caramelo = Caramelo::create([
            'evolution_chain_id' => 51,
        ]);
        $caramelo->refresh();

        $this->assertEquals(0, $caramelo->cantidad);
    }

    public function test_unique_constraint_on_evolution_chain_id(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Caramelo::create(['evolution_chain_id' => 51]);
        Caramelo::create(['evolution_chain_id' => 51]);
    }

    public function test_caramelo_se_puede_crear_sin_fila_en_evolution_chains(): void
    {
        // Regresión bug 23503: la columna evolution_chain_id ya no tiene FK,
        // por lo que una cadena sin fila en la (eliminada) tabla evolution_chains
        // debe insertarse sin error.
        $caramelo = Caramelo::create([
            'evolution_chain_id' => 51,
            'cantidad' => 3,
        ]);

        $this->assertDatabaseHas('caramelos', [
            'evolution_chain_id' => 51,
            'cantidad' => 3,
        ]);
        $this->assertSame(51, $caramelo->evolution_chain_id);
    }
}
