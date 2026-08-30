<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Province;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExploracionActivaTest extends TestCase
{
    use RefreshDatabase;

    private function createHabitat(): Habitat
    {
        Province::create(['id' => 1, 'name' => 'Kanto']);

        return Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
    }

    public function test_can_create_with_required_fields(): void
    {
        $habitat = $this->createHabitat();
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        $exploracion = ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);

        $this->assertDatabaseHas('exploraciones_activas', [
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);
        $this->assertEquals($team->id, $exploracion->equipo_id);
    }

    public function test_nullable_fields_are_null_by_default(): void
    {
        $habitat = $this->createHabitat();
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        $exploracion = ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);
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
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);
        $now = now();

        $exploracion = ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
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

    public function test_belongs_to_team_relationship(): void
    {
        $habitat = $this->createHabitat();
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        $exploracion = ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);

        $this->assertInstanceOf(Team::class, $exploracion->team);
        $this->assertEquals($team->id, $exploracion->team->id);
    }

    public function test_belongs_to_habitat_relationship(): void
    {
        $habitat = $this->createHabitat();
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        $exploracion = ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);

        $this->assertInstanceOf(Habitat::class, $exploracion->habitat);
        $this->assertEquals($habitat->id, $exploracion->habitat->id);
    }

    public function test_eventos_cast_to_array(): void
    {
        $habitat = $this->createHabitat();
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        $exploracion = ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
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
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);

        $this->assertCount(1, $habitat->exploraciones);
    }
}
