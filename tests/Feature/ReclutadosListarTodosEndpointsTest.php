<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReclutadosListarTodosEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create(['experiencia' => 10 * 10 ** 3]);
        $this->actingAs($this->usuario);
    }

    private function crearPokemon(int $id, string $name = 'pikachu'): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $id,
            'name' => $name,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 1,
        ]);

        $mapa = [
            'hp' => StatEnum::HP,
            'atk' => StatEnum::ATTACK,
            'def' => StatEnum::DEFENSE,
            'spAtk' => StatEnum::SPECIAL_ATTACK,
            'spDef' => StatEnum::SPECIAL_DEFENSE,
            'speed' => StatEnum::SPEED,
        ];
        foreach ($mapa as $stat) {
            PokemonStat::create([
                'pokemon_id' => $pokemon->id,
                'stat' => $stat,
                'base_stat' => 60,
                'effort' => 0,
            ]);
        }

        PokemonType::create(['pokemon_id' => $pokemon->id, 'type' => TipoEnum::NORMAL, 'slot' => 1]);

        return $pokemon;
    }

    private function crearReclutado(int $pokemonId, ?string $nombre = 'Reclutado'): Reclutado
    {
        return Reclutado::create([
            'user_id' => $this->usuario->id,
            'pokemon_id' => $pokemonId,
            'nombre' => $nombre,
            'exp' => ['total' => 10 * 5 ** 3],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
    }

    private function crearHabitat(int $id, int $peligro = 1): Habitat
    {
        $province = Province::create(['id' => 100 + $id, 'name' => 'Provincia-'.$id]);

        return Habitat::create(['id' => $id, 'name' => 'Habitat-'.$id, 'province_id' => $province->id, 'peligro' => $peligro]);
    }

    #[Test]
    public function test_listar_todos_devuelve_reclutados_serializados_del_usuario(): void
    {
        $this->crearPokemon(1, 'pikachu');
        $this->crearReclutado(1, 'Pika');
        $this->crearPokemon(2, 'charmander');
        $this->crearReclutado(2, 'Char');

        $response = $this->getJson('/api/reclutados');

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(2, $data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('nombre', $data[0]);
        $this->assertArrayHasKey('pokemon_id', $data[0]);
        $this->assertArrayHasKey('nivel', $data[0]);
        $this->assertSame('Pika', $data[0]['nombre']);
    }

    #[Test]
    public function test_listar_todos_no_devuelve_reclutados_de_otros_usuarios(): void
    {
        $otro = User::factory()->create();
        $this->crearPokemon(1);
        Reclutado::create([
            'user_id' => $otro->id,
            'pokemon_id' => 1,
            'nombre' => 'Ajeno',
            'exp' => ['total' => 0],
        ]);
        $this->crearReclutado(1, 'Propio');

        $response = $this->getJson('/api/reclutados');

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertSame('Propio', $data[0]['nombre']);
    }

    #[Test]
    public function test_serializer_fallback_nombre_null_al_nombre_del_pokemon(): void
    {
        $this->crearPokemon(1, 'bulbasaur');
        $this->crearReclutado(1, null);

        $response = $this->getJson('/api/reclutados');

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertSame('bulbasaur', $data[0]['nombre']);
    }

    #[Test]
    public function test_listar_favoritos_por_habitat_devuelve_formato_esperado(): void
    {
        $habitat = $this->crearHabitat(1);
        $this->crearPokemon(1, 'squirtle');
        $reclutado = $this->crearReclutado(1, 'Squi');
        $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => $habitat->id])->assertOk();

        $response = $this->getJson('/api/reclutados/favoritos?habitat_id='.$habitat->id);

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('nombre', $data[0]);
        $this->assertArrayHasKey('pokemon_id', $data[0]);
        $this->assertArrayHasKey('nivel', $data[0]);
        $this->assertSame('Squi', $data[0]['nombre']);
    }

    #[Test]
    public function test_listar_favoritos_globales_sin_habitat_id(): void
    {
        $this->crearPokemon(1, 'eevee');
        $reclutado = $this->crearReclutado(1, 'Eve');
        $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => null])->assertOk();

        $response = $this->getJson('/api/reclutados/favoritos');

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertSame('Eve', $data[0]['nombre']);
    }
}
