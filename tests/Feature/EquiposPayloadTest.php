<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquiposPayloadTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function createPokemon(int $id, int $baseExperience = 64): Pokemon
    {
        return Pokemon::create([
            'id' => $id,
            'name' => 'pokemon-'.$id,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => $baseExperience,
            'height' => 7,
            'weight' => 69,
        ]);
    }

    private function createStat(int $pokemonId, StatEnum $stat, int $baseStat): PokemonStat
    {
        return PokemonStat::create([
            'pokemon_id' => $pokemonId,
            'stat' => $stat,
            'base_stat' => $baseStat,
            'effort' => 0,
        ]);
    }

    public function test_equipos_reclutados_incluyen_nivel_exp_total_base_experience_es_shiny_y_stats(): void
    {
        $user = $this->actingAsUser();
        $pokemon = $this->createPokemon(1, 64);
        // Se insertan desordenadas (Speed 6 antes que Attack 2): el payload debe
        // devolver la lista ordenada por stat 1-6 (Ataque, Velocidad).
        $this->createStat($pokemon->id, StatEnum::SPEED, 45);
        $this->createStat($pokemon->id, StatEnum::ATTACK, 65);
        PokemonType::create(['pokemon_id' => $pokemon->id, 'type' => TipoEnum::GRASS, 'slot' => 1]);

        $reclutado = Reclutado::create([
            'user_id' => $user->id,
            'nombre' => 'Bulbi',
            'pokemon_id' => $pokemon->id,
            'exp' => ['total' => 100],
            'es_shiny' => true,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        $response = $this->get('/equipos');

        $response->assertOk();
        // El frontend consume @json($reclutados): se valida la forma serializada real.
        $reclutados = json_decode(json_encode($response->viewData('reclutados')), true);
        $this->assertCount(1, $reclutados);

        $item = $reclutados[0];
        // Contrato base existente preservado.
        $this->assertSame($reclutado->id, $item['id']);
        $this->assertSame(1, $item['pokemon_id']);
        $this->assertSame('Bulbi', $item['nombre']);
        $this->assertSame('pokemon-1', $item['pokemon']['name']);
        $this->assertSame('Planta', $item['pokemon']['types'][0]['tipo_nombre']);

        // Nuevas claves aditivas.
        $this->assertSame(2, $item['nivel']); // NivelHelper: 10*2³=80 ≤ 100 < 10*3³
        $this->assertSame(100, $item['exp_total']);
        $this->assertSame(64, $item['base_experience']);
        $this->assertTrue($item['es_shiny']);
        $this->assertSame([
            ['name' => 'Ataque', 'value' => 65],
            ['name' => 'Velocidad', 'value' => 45],
        ], $item['stats']);
    }

    public function test_equipos_sin_stats_devuelve_lista_vacia(): void
    {
        $user = $this->actingAsUser();
        $pokemon = $this->createPokemon(1);

        Reclutado::create([
            'user_id' => $user->id,
            'nombre' => 'Sin Stats',
            'pokemon_id' => $pokemon->id,
            'exp' => ['total' => 0],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        $response = $this->get('/equipos');

        $response->assertOk();
        $reclutados = json_decode(json_encode($response->viewData('reclutados')), true);
        $this->assertCount(1, $reclutados);
        $this->assertSame(1, $reclutados[0]['nivel']);
        $this->assertSame(0, $reclutados[0]['exp_total']);
        $this->assertSame([], $reclutados[0]['stats']);
        $this->assertFalse($reclutados[0]['es_shiny']);
    }

    public function test_team_ids_contiene_ids_de_reclutados_en_equipos(): void
    {
        $user = $this->actingAsUser();
        $this->createPokemon(1);
        $this->createPokemon(2);
        $this->createPokemon(3);

        $team = Team::create(['name' => 'Alpha', 'user_id' => $user->id]);
        $r1 = Reclutado::create([
            'user_id' => $user->id,
            'nombre' => 'A',
            'pokemon_id' => 1,
            'exp' => ['total' => 0],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
        $r2 = Reclutado::create([
            'user_id' => $user->id,
            'nombre' => 'B',
            'pokemon_id' => 2,
            'exp' => ['total' => 0],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
        $r3 = Reclutado::create([
            'user_id' => $user->id,
            'nombre' => 'C',
            'pokemon_id' => 3,
            'exp' => ['total' => 0],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $r1->id, 'slot' => 1, 'behavior' => 'VANGUARDIA']);
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $r2->id, 'slot' => 2, 'behavior' => 'COMBATIENTE']);

        $response = $this->get('/equipos');

        $response->assertOk();
        $teamIds = $response->viewData('teamIds');
        $this->assertIsArray($teamIds);
        $this->assertEqualsCanonicalizing([$r1->id, $r2->id], $teamIds);
        // r3 no está en ningún equipo → no debe aparecer
        $this->assertNotContains($r3->id, $teamIds);
    }

    public function test_team_ids_vacio_cuando_no_hay_equipos(): void
    {
        $this->actingAsUser();
        $this->createPokemon(1);

        Reclutado::create([
            'user_id' => auth()->id(),
            'nombre' => 'Solo',
            'pokemon_id' => 1,
            'exp' => ['total' => 0],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        $response = $this->get('/equipos');

        $response->assertOk();
        $this->assertSame([], $response->viewData('teamIds'));
    }

    public function test_reclutados_en_exploracion_devuelve_ids_de_reclutados_con_exploracion_activa(): void
    {
        $user = $this->actingAsUser();
        $this->createPokemon(1);
        $this->createPokemon(2);

        $r1 = Reclutado::create([
            'user_id' => $user->id,
            'nombre' => 'A',
            'pokemon_id' => 1,
            'exp' => ['total' => 0],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
        $r2 = Reclutado::create([
            'user_id' => $user->id,
            'nombre' => 'B',
            'pokemon_id' => 2,
            'exp' => ['total' => 0],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => $province->id]);

        // r1 en exploración activa (regreso null), r2 no
        ExploracionActiva::create([
            'user_id' => $user->id,
            'reclutado_id' => $r1->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 2,
            'inicio_exploracion' => now(),
            'llegada_destino' => now()->addHour(),
            'regreso' => null,
        ]);

        $response = $this->get('/equipos');

        $response->assertOk();
        $data = $response->viewData('reclutadosEnExploracion');
        $this->assertIsArray($data);
        $this->assertContains($r1->id, $data);
        $this->assertNotContains($r2->id, $data);
    }

    public function test_reclutados_en_exploracion_excluye_exploraciones_con_regreso(): void
    {
        $user = $this->actingAsUser();
        $this->createPokemon(1);

        $r1 = Reclutado::create([
            'user_id' => $user->id,
            'nombre' => 'A',
            'pokemon_id' => 1,
            'exp' => ['total' => 0],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => $province->id]);

        ExploracionActiva::create([
            'user_id' => $user->id,
            'reclutado_id' => $r1->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 2,
            'inicio_exploracion' => now()->subHours(5),
            'llegada_destino' => now()->subHours(3),
            'regreso' => now()->subHours(1),
        ]);

        $response = $this->get('/equipos');

        $response->assertOk();
        $this->assertNotContains($r1->id, $response->viewData('reclutadosEnExploracion'));
    }

    public function test_equipos_en_exploracion_devuelve_team_ids_con_miembros_en_exploracion(): void
    {
        $user = $this->actingAsUser();
        $this->createPokemon(1);
        $this->createPokemon(2);
        $this->createPokemon(3);

        $team1 = Team::create(['name' => 'Alpha', 'user_id' => $user->id]);
        $team2 = Team::create(['name' => 'Beta', 'user_id' => $user->id]);

        $r1 = Reclutado::create([
            'user_id' => $user->id,
            'nombre' => 'A',
            'pokemon_id' => 1,
            'exp' => ['total' => 0],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
        $r2 = Reclutado::create([
            'user_id' => $user->id,
            'nombre' => 'B',
            'pokemon_id' => 2,
            'exp' => ['total' => 0],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
        $r3 = Reclutado::create([
            'user_id' => $user->id,
            'nombre' => 'C',
            'pokemon_id' => 3,
            'exp' => ['total' => 0],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        TeamMember::create(['team_id' => $team1->id, 'pokemon_id' => $r1->id, 'slot' => 1, 'behavior' => 'VANGUARDIA']);
        TeamMember::create(['team_id' => $team2->id, 'pokemon_id' => $r2->id, 'slot' => 1, 'behavior' => 'VANGUARDIA']);
        TeamMember::create(['team_id' => $team2->id, 'pokemon_id' => $r3->id, 'slot' => 2, 'behavior' => 'COMBATIENTE']);

        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => $province->id]);

        // Solo r1 (de team1) está en exploración; r2 y r3 (de team2) no
        ExploracionActiva::create([
            'user_id' => $user->id,
            'reclutado_id' => $r1->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 2,
            'inicio_exploracion' => now(),
            'llegada_destino' => now()->addHour(),
            'regreso' => null,
        ]);

        $response = $this->get('/equipos');

        $response->assertOk();
        $equiposEnExploracion = $response->viewData('equiposEnExploracion');
        $this->assertIsArray($equiposEnExploracion);
        $this->assertContains($team1->id, $equiposEnExploracion);
        $this->assertNotContains($team2->id, $equiposEnExploracion);
        // Array plano de enteros
        foreach ($equiposEnExploracion as $teamId) {
            $this->assertIsInt($teamId);
        }
    }

    public function test_equipos_en_exploracion_vacio_cuando_no_hay_exploraciones(): void
    {
        $this->actingAsUser();
        $this->createPokemon(1);

        $response = $this->get('/equipos');

        $response->assertOk();
        $this->assertSame([], $response->viewData('equiposEnExploracion'));
    }
}
