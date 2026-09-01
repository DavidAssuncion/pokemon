<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Reclutado;
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
}
