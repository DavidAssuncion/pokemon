<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Favorito;
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

class ExploracionesFavoritosEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create(['experiencia' => 10 * 10 ** 3]);
        $this->actingAs($this->usuario);
    }

    private function crearPokemon(int $id, int $evolutionChain = 1): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $id,
            'name' => 'pokemon-'.$id,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => $evolutionChain,
        ]);

        $mapa = [
            'hp' => StatEnum::HP,
            'atk' => StatEnum::ATTACK,
            'def' => StatEnum::DEFENSE,
            'spAtk' => StatEnum::SPECIAL_ATTACK,
            'spDef' => StatEnum::SPECIAL_DEFENSE,
            'speed' => StatEnum::SPEED,
        ];
        foreach ($mapa as $clave => $stat) {
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

    private function crearReclutado(int $pokemonId, string $nombre = 'Reclutado'): Reclutado
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
    public function test_toggle_global_marca_y_desmarca_con_count(): void
    {
        $this->crearPokemon(1);
        $reclutado = $this->crearReclutado(1, 'Pika');

        // Marcar global (habitat_id null).
        $response = $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => null]);
        $response->assertOk()->assertJson(['favorito' => true, 'count' => 1]);

        $this->assertDatabaseHas('favoritos', [
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => null,
        ]);

        // Desmarcar.
        $response = $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => null]);
        $response->assertOk()->assertJson(['favorito' => false, 'count' => 0]);

        $this->assertDatabaseMissing('favoritos', [
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
        ]);

        // Volver a marcar.
        $response = $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => null]);
        $response->assertOk()->assertJson(['favorito' => true, 'count' => 1]);
    }

    #[Test]
    public function test_toggle_global_limite_6(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->crearPokemon($i);
            $reclutado = $this->crearReclutado($i, 'P'.$i);
            $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => null])->assertOk();
        }

        $this->crearPokemon(7);
        $reclutado7 = $this->crearReclutado(7, 'P7');

        $this->postJson("/api/reclutados/{$reclutado7->id}/toggle-favorito", ['habitat_id' => null])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function test_toggle_por_habitat_marca_y_desmarca(): void
    {
        $habitat = $this->crearHabitat(1);
        $this->crearPokemon(1);
        $reclutado = $this->crearReclutado(1, 'Pika');

        $response = $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => $habitat->id]);
        $response->assertOk()->assertJson(['favorito' => true, 'count' => 1]);

        $this->assertDatabaseHas('favoritos', [
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
        ]);

        $response = $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => $habitat->id]);
        $response->assertOk()->assertJson(['favorito' => false, 'count' => 0]);

        $this->assertDatabaseMissing('favoritos', [
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
        ]);
    }

    #[Test]
    public function test_toggle_por_habitat_limite_6_por_habitat(): void
    {
        $habitat = $this->crearHabitat(1);

        for ($i = 1; $i <= 6; $i++) {
            $this->crearPokemon($i);
            $reclutado = $this->crearReclutado($i, 'P'.$i);
            $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => $habitat->id])->assertOk();
        }

        $this->crearPokemon(7);
        $reclutado7 = $this->crearReclutado(7, 'P7');

        $this->postJson("/api/reclutados/{$reclutado7->id}/toggle-favorito", ['habitat_id' => $habitat->id])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function test_toggle_por_habitat_no_cuenta_contra_globales(): void
    {
        $habitat = $this->crearHabitat(1);
        $this->crearPokemon(1);
        $reclutado = $this->crearReclutado(1);

        // Un favorito por hábitat no debe contar como global.
        $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => $habitat->id])->assertOk();

        $this->assertSame(0, Favorito::countGlobales($this->usuario->id));
        $this->assertSame(1, Favorito::countParaHabitat($this->usuario->id, $habitat->id));
    }

    #[Test]
    public function test_listar_globales_vacio(): void
    {
        $response = $this->getJson('/api/reclutados/favoritos');
        $response->assertOk();
        $this->assertSame([], $response->json());
    }

    #[Test]
    public function test_listar_globales_con_datos_serializados(): void
    {
        $this->crearPokemon(1);
        $reclutado = $this->crearReclutado(1, 'Fav');
        $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => null])->assertOk();

        $response = $this->getJson('/api/reclutados/favoritos');

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertSame('Fav', $data[0]['nombre']);
        $this->assertArrayHasKey('nivel', $data[0]);
        $this->assertArrayHasKey('stats', $data[0]);
    }

    #[Test]
    public function test_listar_por_habitat_vacio(): void
    {
        $habitat = $this->crearHabitat(1);

        $response = $this->getJson('/api/reclutados/favoritos?habitat_id='.$habitat->id);
        $response->assertOk();
        $this->assertSame([], $response->json());
    }

    #[Test]
    public function test_listar_por_habitat_devuelve_solo_ese_habitat(): void
    {
        $habitat1 = $this->crearHabitat(1);
        $habitat2 = $this->crearHabitat(2);

        $this->crearPokemon(1);
        $reclutado1 = $this->crearReclutado(1, 'DelHab1');
        $this->crearPokemon(2);
        $reclutado2 = $this->crearReclutado(2, 'DelHab2');

        $this->postJson("/api/reclutados/{$reclutado1->id}/toggle-favorito", ['habitat_id' => $habitat1->id])->assertOk();
        $this->postJson("/api/reclutados/{$reclutado2->id}/toggle-favorito", ['habitat_id' => $habitat2->id])->assertOk();

        $response = $this->getJson('/api/reclutados/favoritos?habitat_id='.$habitat1->id);
        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertSame('DelHab1', $data[0]['nombre']);
    }

    #[Test]
    public function test_legacy_habitat_toggle_favorito_es_no_op(): void
    {
        $habitat = $this->crearHabitat(1);

        $response = $this->postJson("/api/habitats/{$habitat->id}/toggle-favorito");

        $response->assertOk()->assertJson(['favorito' => false, 'count' => 0]);
        $this->assertDatabaseCount('favoritos', 0);
    }
}
