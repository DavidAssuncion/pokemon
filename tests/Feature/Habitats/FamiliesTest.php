<?php

declare(strict_types=1);

namespace Tests\Feature\Habitats;

use App\Models\EvolutionChain;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Habitats\Infra\HabitatRepository;
use Tests\TestCase;

class FamiliesTest extends TestCase
{
    use RefreshDatabase;

    private int $habitatId;
    private int $chainId3Stages;
    private int $chainId2Stages;
    private int $chainId1Stage;

    protected function setUp(): void
    {
        parent::setUp();

        // Create province and habitat
        Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create([
            'id' => 1,
            'name' => 'Bosque',
            'province_id' => 1,
        ]);
        $this->habitatId = $habitat->id;

        // Create Evolution Chain with 3 stages (e.g., Bulbasaur -> Ivysaur -> Venusaur)
        $chain3 = EvolutionChain::create(['data' => '{"stages": 3}']);
        $this->chainId3Stages = $chain3->id;

        $pokemonBulbasaur = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'base_experience' => 64,
            'capture_rate' => 45,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => $this->chainId3Stages,
        ]);
        $pokemonIvysaur = Pokemon::create([
            'id' => 2,
            'name' => 'ivysaur',
            'species_id' => 2,
            'base_experience' => 142,
            'capture_rate' => 45,
            'height' => 10,
            'weight' => 130,
            'evolution_chain_id' => $this->chainId3Stages,
        ]);
        $pokemonVenusaur = Pokemon::create([
            'id' => 3,
            'name' => 'venusaur',
            'species_id' => 3,
            'base_experience' => 236,
            'capture_rate' => 45,
            'height' => 20,
            'weight' => 1000,
            'evolution_chain_id' => $this->chainId3Stages,
        ]);

        PokemonEvolution::create([
            'evolved_species_id' => 1,
            'evolves_from_species_id' => null,
            'minimum_level' => 16,
        ]);
        PokemonEvolution::create([
            'evolved_species_id' => 2,
            'evolves_from_species_id' => 1,
            'minimum_level' => 32,
        ]);
        PokemonEvolution::create([
            'evolved_species_id' => 3,
            'evolves_from_species_id' => 2,
            'minimum_level' => 100,
        ]);

        // Create Evolution Chain with 2 stages (e.g., Rattata -> Raticate)
        $chain2 = EvolutionChain::create(['data' => '{"stages": 2}']);
        $this->chainId2Stages = $chain2->id;

        $pokemonRattata = Pokemon::create([
            'id' => 19,
            'name' => 'rattata',
            'species_id' => 19,
            'base_experience' => 51,
            'capture_rate' => 255,
            'height' => 3,
            'weight' => 35,
            'evolution_chain_id' => $this->chainId2Stages,
        ]);
        $pokemonRaticate = Pokemon::create([
            'id' => 20,
            'name' => 'raticate',
            'species_id' => 20,
            'base_experience' => 145,
            'capture_rate' => 127,
            'height' => 7,
            'weight' => 185,
            'evolution_chain_id' => $this->chainId2Stages,
        ]);

        PokemonEvolution::create([
            'evolved_species_id' => 19,
            'evolves_from_species_id' => null,
            'minimum_level' => 20,
        ]);
        PokemonEvolution::create([
            'evolved_species_id' => 20,
            'evolves_from_species_id' => 19,
            'minimum_level' => 100,
        ]);

        // Create Evolution Chain with 1 stage (e.g., Legendary)
        $chain1 = EvolutionChain::create(['data' => '{"stages": 1}']);
        $this->chainId1Stage = $chain1->id;

        $pokemonMew = Pokemon::create([
            'id' => 151,
            'name' => 'mew',
            'species_id' => 151,
            'base_experience' => 64,
            'capture_rate' => 45,
            'height' => 4,
            'weight' => 40,
            'evolution_chain_id' => $this->chainId1Stage,
        ]);

        PokemonEvolution::create([
            'evolved_species_id' => 151,
            'evolves_from_species_id' => null,
            'minimum_level' => 100,
        ]);
    }

    // ==========================================
    // GET /api/habitats/{id}/families
    // ==========================================

    public function test_obtener_familias_disponibles_retorna_asignadas_y_disponibles(): void
    {
        // Assign 3-stage chain to habitat
        DB::table('pokemon_habitat')->insert([
            ['pokemon_id' => 1, 'habitat_id' => $this->habitatId, 'level' => 1],
            ['pokemon_id' => 2, 'habitat_id' => $this->habitatId, 'level' => 2],
            ['pokemon_id' => 3, 'habitat_id' => $this->habitatId, 'level' => 3],
        ]);

        $response = $this->getJson("/api/habitats/{$this->habitatId}/families");

        $response->assertStatus(200);
        $data = $response->json();

        // Should have assigned families array (the API returns array directly)
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals($this->chainId3Stages, $data[0]['evolution_chain_id']);
        $this->assertEquals(1, $data[0]['base']['id']);
        $this->assertCount(2, $data[0]['evolutions']);
        // Los iconos de las familias se sirven como WebP
        $this->assertSame('/images/iconos_webp/1.webp', $data[0]['base']['icon']);
        $this->assertSame('/images/iconos_webp/2.webp', $data[0]['evolutions'][0]['icon']);
        $this->assertSame('/images/iconos_webp/3.webp', $data[0]['evolutions'][1]['icon']);
    }

    public function test_obtener_familias_sin_habitat_solo_cadenas_vacias(): void
    {
        // No families assigned to any habitat
        $response = $this->getJson('/api/habitats/unassigned-families');

        $response->assertStatus(200);
        $data = $response->json();

        // Should return array of unassigned families
        $this->assertIsArray($data);
        $this->assertCount(3, $data);

        // Should have base and evolutions for each chain
        foreach ($data as $family) {
            $this->assertArrayHasKey('evolution_chain_id', $family);
            $this->assertArrayHasKey('base', $family);
            $this->assertArrayHasKey('evolutions', $family);
            // Los iconos de las familias se sirven como WebP
            $this->assertSame('/images/iconos_webp/'.$family['base']['id'].'.webp', $family['base']['icon']);

            foreach ($family['evolutions'] as $evolution) {
                $this->assertSame('/images/iconos_webp/'.$evolution['id'].'.webp', $evolution['icon']);
            }
        }
    }

    public function test_detalle_habitat_iconos_son_webp(): void
    {
        DB::table('pokemon_habitat')->insert([
            ['pokemon_id' => 1, 'habitat_id' => $this->habitatId, 'level' => 1],
        ]);

        $detail = (new HabitatRepository())->getHabitatDetail($this->habitatId);

        $this->assertSame('/images/iconos_webp/1.webp', $detail->levels[1][0]['icon']);
        $this->assertSame('/images/iconos_webp/1.webp', $detail->toArray()['levels'][1][0]['icon']);
    }

    // ==========================================
    // POST /api/habitats/{id}/families
    // ==========================================

    public function test_asignar_familia_3_etapas_inserta_levels_1_2_3(): void
    {
        $response = $this->postJson("/api/habitats/{$this->habitatId}/families", [
            'evolution_chain_id' => $this->chainId3Stages,
        ]);

        $response->assertStatus(201);
        $data = $response->json();

        $this->assertEquals($this->chainId3Stages, $data['evolution_chain_id']);
        $this->assertEquals($this->habitatId, $data['habitat_id']);
        $this->assertEquals(3, $data['assigned_count']);

        // Verify in database
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 1,
            'habitat_id' => $this->habitatId,
        ]);
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 2,
            'habitat_id' => $this->habitatId,
        ]);
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 3,
            'habitat_id' => $this->habitatId,
        ]);
    }

    public function test_asignar_familia_2_etapas_rattata_inserta_levels_1_2(): void
    {
        $response = $this->postJson("/api/habitats/{$this->habitatId}/families", [
            'evolution_chain_id' => $this->chainId2Stages,
        ]);

        $response->assertStatus(201);
        $data = $response->json();

        $this->assertEquals($this->chainId2Stages, $data['evolution_chain_id']);
        $this->assertEquals($this->habitatId, $data['habitat_id']);
        $this->assertEquals(2, $data['assigned_count']);

        // Verify in database
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 19,
            'habitat_id' => $this->habitatId,
        ]);
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 20,
            'habitat_id' => $this->habitatId,
        ]);
    }

    public function test_asignar_familia_1_etapa_inserta_level_2(): void
    {
        $response = $this->postJson("/api/habitats/{$this->habitatId}/families", [
            'evolution_chain_id' => $this->chainId1Stage,
        ]);

        $response->assertStatus(201);
        $data = $response->json();

        $this->assertEquals($this->chainId1Stage, $data['evolution_chain_id']);
        $this->assertEquals($this->habitatId, $data['habitat_id']);
        $this->assertEquals(1, $data['assigned_count']);

        // Verify in database
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 151,
            'habitat_id' => $this->habitatId,
        ]);
    }

    public function test_assign_family_upsert_no_duplica_si_ya_existe(): void
    {
        // First assignment
        $this->postJson("/api/habitats/{$this->habitatId}/families", [
            'evolution_chain_id' => $this->chainId3Stages,
        ]);

        // Second assignment (should upsert, not duplicate)
        $response = $this->postJson("/api/habitats/{$this->habitatId}/families", [
            'evolution_chain_id' => $this->chainId3Stages,
        ]);

        $response->assertStatus(201);

        // Should only have 3 records, not 6
        $this->assertDatabaseCount('pokemon_habitat', 3);
    }

    public function test_validacion_evolution_chain_inexistente_lanza_excepcion(): void
    {
        $response = $this->postJson("/api/habitats/{$this->habitatId}/families", [
            'evolution_chain_id' => 99999, // Non-existent
        ]);

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('errors', $data);
    }

    public function test_validacion_evolution_chain_sin_pokemon_lanza_excepcion(): void
    {
        // Chain id not assigned to any pokemon (no family members)
        $emptyChain = EvolutionChain::create(['data' => '{}']);

        $response = $this->postJson("/api/habitats/{$this->habitatId}/families", [
            'evolution_chain_id' => $emptyChain->id,
        ]);

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('errors', $data);
    }

    public function test_validacion_habitat_inexistente_lanza_excepcion(): void
    {
        $response = $this->postJson('/api/habitats/99999/families', [
            'evolution_chain_id' => $this->chainId3Stages,
        ]);

        $response->assertStatus(422);
    }

    // ==========================================
    // DELETE /api/habitats/{id}/families/{chainId}
    // ==========================================

    public function test_eliminar_familia_borra_todos_pokemon_cadena(): void
    {
        // First assign the family
        $this->postJson("/api/habitats/{$this->habitatId}/families", [
            'evolution_chain_id' => $this->chainId3Stages,
        ]);

        // Verify assigned
        $this->assertDatabaseCount('pokemon_habitat', 3);

        // Delete the family
        $response = $this->deleteJson("/api/habitats/{$this->habitatId}/families/{$this->chainId3Stages}");

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals($this->chainId3Stages, $data['evolution_chain_id']);
        $this->assertEquals($this->habitatId, $data['habitat_id']);
        $this->assertEquals(3, $data['removed_count']);

        // Verify all removed from database
        $this->assertDatabaseMissing('pokemon_habitat', [
            'pokemon_id' => 1,
            'habitat_id' => $this->habitatId,
        ]);
        $this->assertDatabaseMissing('pokemon_habitat', [
            'pokemon_id' => 2,
            'habitat_id' => $this->habitatId,
        ]);
        $this->assertDatabaseMissing('pokemon_habitat', [
            'pokemon_id' => 3,
            'habitat_id' => $this->habitatId,
        ]);
    }

    public function test_eliminar_familia_inexistente_retorna_422(): void
    {
        $response = $this->deleteJson("/api/habitats/{$this->habitatId}/families/99999");

        $response->assertStatus(422);
    }

    public function test_eliminar_familia_no_asignada_no_borra_nada(): void
    {
        // Chain exists but not assigned to this habitat
        $response = $this->deleteJson("/api/habitats/{$this->habitatId}/families/{$this->chainId3Stages}");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(0, $data['removed_count']);
        $this->assertDatabaseCount('pokemon_habitat', 0);
    }
}
