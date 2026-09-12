<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExploracionActivaTest extends TestCase
{
    use RefreshDatabase;

    private function createHabitat(): Habitat
    {
        $province = Province::firstOrCreate(['id' => 1], ['name' => 'Kanto']);

        return Habitat::firstOrCreate(['id' => 1], ['name' => 'Bosque', 'province_id' => $province->id]);
    }

    private function createReclutado(): Reclutado
    {
        $usuario = User::factory()->create();
        $pokemon = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 51,
        ]);

        return Reclutado::create([
            'user_id' => $usuario->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Bulbi',
            'exp' => ['total' => 0],
        ]);
    }

    private function createExploracion(array $atributos = []): ExploracionActiva
    {
        $habitat = $this->createHabitat();
        $reclutado = $this->createReclutado();

        return ExploracionActiva::create(array_merge([
            'user_id' => $reclutado->user_id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ], $atributos));
    }

    public function test_can_create_with_required_fields(): void
    {
        $habitat = $this->createHabitat();
        $reclutado = $this->createReclutado();

        $exploracion = ExploracionActiva::create([
            'user_id' => $reclutado->user_id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);

        $this->assertDatabaseHas('exploraciones_activas', [
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);
        $this->assertEquals($reclutado->id, $exploracion->reclutado_id);
    }

    public function test_nullable_fields_are_null_by_default(): void
    {
        $exploracion = $this->createExploracion();
        $exploracion->refresh();

        $this->assertNull($exploracion->duracion_horas);
        $this->assertNull($exploracion->hora_limite);
        $this->assertNull($exploracion->eventos);
        $this->assertNull($exploracion->inicio_exploracion);
        $this->assertNull($exploracion->llegada_destino);
        $this->assertNull($exploracion->regreso);
    }

    public function test_can_set_nullable_fields(): void
    {
        $habitat = $this->createHabitat();
        $reclutado = $this->createReclutado();
        $now = now();

        $exploracion = ExploracionActiva::create([
            'user_id' => $reclutado->user_id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 2,
            'duracion_horas' => 4,
            'hora_limite' => '23:59:59',
            'indefinido' => true,
            'eventos' => ['encounter' => 'bulbasaur'],
            'inicio_exploracion' => $now,
            'llegada_destino' => $now->addHour(),
        ]);
        $exploracion->refresh();

        $this->assertEquals(4, $exploracion->duracion_horas);
        $this->assertTrue($exploracion->indefinido);
        // D11: eventos con cast 'collection'.
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $exploracion->eventos);
        $this->assertSame('bulbasaur', $exploracion->eventos->get('encounter'));
        $this->assertNotNull($exploracion->inicio_exploracion);
        $this->assertNotNull($exploracion->llegada_destino);
    }

    public function test_belongs_to_reclutado_relationship(): void
    {
        $exploracion = $this->createExploracion();

        $this->assertInstanceOf(Reclutado::class, $exploracion->reclutado);
        $this->assertEquals($exploracion->reclutado_id, $exploracion->reclutado->id);
    }

    public function test_belongs_to_habitat_relationship(): void
    {
        $exploracion = $this->createExploracion();

        $this->assertInstanceOf(Habitat::class, $exploracion->habitat);
        $this->assertEquals($exploracion->habitat_id, $exploracion->habitat->id);
    }

    public function test_eventos_cast_to_array(): void
    {
        $habitat = $this->createHabitat();
        $reclutado = $this->createReclutado();

        $exploracion = ExploracionActiva::create([
            'user_id' => $reclutado->user_id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'eventos' => ['battle' => 'wild', 'result' => 'captured'],
        ]);
        $exploracion->refresh();

        // D11: eventos con cast 'collection'.
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $exploracion->eventos);
        $this->assertSame('wild', $exploracion->eventos->get('battle'));
        $this->assertSame('captured', $exploracion->eventos->get('result'));
    }

    public function test_has_exploraciones_has_many_relationship_on_habitat(): void
    {
        $habitat = $this->createHabitat();
        $this->createExploracion();

        $this->assertCount(1, $habitat->exploraciones);
    }
}
