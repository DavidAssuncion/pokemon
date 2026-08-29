<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Casts\ExpReclutado;
use App\Models\Pokemon;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpReclutadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_devuelve_cero_cuando_el_valor_es_null(): void
    {
        $exp = ExpReclutado::fromRaw(null);

        $this->assertSame(0, $exp->total());
        $this->assertSame([], $exp->toArray()['tipos']);
    }

    public function test_legacy_con_solo_total_no_tiene_tipos(): void
    {
        $exp = ExpReclutado::fromRaw('{"total": 150}');

        $this->assertSame(150, $exp->total());
        $this->assertSame(0, $exp->expTipo('Agua'));
        $this->assertSame([], $exp->toArray()['tipos']);
    }

    public function test_json_vacio_se_trata_como_total_cero(): void
    {
        $exp = ExpReclutado::fromRaw('{}');

        $this->assertSame(0, $exp->total());
    }

    public function test_exp_tipo_devuelve_la_cantidad_por_tipo(): void
    {
        $exp = new ExpReclutado(total: 500, tipos: ['Agua' => 150]);

        $this->assertSame(150, $exp->expTipo('Agua'));
        $this->assertSame(0, $exp->expTipo('Fuego'));
    }

    public function test_sumar_exp_tipo_no_muta_la_instancia_y_mantiene_el_total(): void
    {
        $exp = new ExpReclutado(total: 150, tipos: ['Agua' => 50]);

        $nuevo = $exp->sumarExpTipo('Fuego', 100);

        $this->assertSame(50, $exp->expTipo('Agua'), 'la instancia original no debe mutar');
        $this->assertSame(150, $exp->total(), 'el total no debe cambiar');
        $this->assertSame(50, $nuevo->expTipo('Agua'), 'un tipo existente no cambia');
        $this->assertSame(100, $nuevo->expTipo('Fuego'), 'un tipo nuevo se crea con la cantidad');
        $this->assertSame(150, $nuevo->total());
    }

    public function test_consumir_tipos_resta_el_umbral_por_tipo(): void
    {
        $exp = new ExpReclutado(total: 1000, tipos: ['Agua' => 300, 'Fuego' => 100, 'Planta' => 200]);

        $consumido = $exp->consumirTipos(['Agua', 'Fuego'], 100);

        $this->assertSame(200, $consumido->expTipo('Agua'));
        $this->assertSame(200, $consumido->expTipo('Planta'), 'los tipos no consumidos no cambian');
        $this->assertSame(1000, $consumido->total());
        $this->assertSame(300, $exp->expTipo('Agua'), 'la instancia original no debe mutar');
    }

    public function test_consumir_tipos_elimina_los_que_llegan_a_cero(): void
    {
        $exp = new ExpReclutado(tipos: ['Agua' => 100, 'Fuego' => 50]);

        $consumido = $exp->consumirTipos(['Agua', 'Fuego'], 100);

        $this->assertSame(0, $consumido->expTipo('Agua'), 'al llegar a 0 el tipo desaparece');
        $this->assertArrayNotHasKey('Agua', $consumido->toArray()['tipos']);
        $this->assertArrayNotHasKey('Fuego', $consumido->toArray()['tipos']);
    }

    public function test_consumir_tipos_ignora_tipos_sin_exp(): void
    {
        $exp = new ExpReclutado(tipos: ['Agua' => 300]);

        $consumido = $exp->consumirTipos(['Fantasma'], 100);

        $this->assertSame(300, $consumido->expTipo('Agua'));
    }

    public function test_to_array_serializa_el_shape_documentado(): void
    {
        $exp = new ExpReclutado(total: 500, tipos: ['Agua' => 150]);

        $this->assertSame(['total' => 500, 'tipos' => ['Agua' => 150]], $exp->toArray());
    }

    public function test_cast_del_modelo_lee_el_json_almacenado(): void
    {
        $user = User::factory()->create();
        $pokemon = $this->createPokemon();
        $reclutado = Reclutado::create([
            'nombre' => 'Vaporeon',
            'pokemon_id' => $pokemon->id,
            'user_id' => $user->id,
            'exp' => json_encode(['total' => 500, 'tipos' => ['Agua' => 150]]),
        ]);

        $this->assertInstanceOf(ExpReclutado::class, $reclutado->exp);
        $this->assertSame(500, $reclutado->exp->total());
        $this->assertSame(150, $reclutado->exp->expTipo('Agua'));
    }

    public function test_cast_del_modelo_persiste_el_vo_mutado(): void
    {
        $user = User::factory()->create();
        $pokemon = $this->createPokemon();
        $reclutado = Reclutado::create([
            'nombre' => 'Vaporeon',
            'pokemon_id' => $pokemon->id,
            'user_id' => $user->id,
            'exp' => ['total' => 500],
        ]);

        $reclutado->exp = $reclutado->exp->sumarExpTipo('Agua', 100);
        $reclutado->save();

        $refreshed = $reclutado->fresh();
        $this->assertSame(100, $refreshed->exp->expTipo('Agua'));
        $this->assertSame(500, $refreshed->exp->total());
    }

    private function createPokemon(): Pokemon
    {
        return Pokemon::create([
            'id' => 134,
            'name' => 'Vaporeon',
            'species_id' => 134,
            'capture_rate' => 45,
            'base_experience' => 184,
            'height' => 10,
            'weight' => 290,
            'evolution_chain_id' => 51,
        ]);
    }
}
