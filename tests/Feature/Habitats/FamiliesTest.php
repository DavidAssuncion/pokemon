<?php

declare(strict_types=1);

namespace Tests\Feature\Habitats;

use App\Enums\TipoEnum;
use App\Models\EvolutionChain;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\PokemonType;
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

    /**
     * Asigna una familia al hábitat del setUp (setup compartido por varios tests).
     */
    private function assignChainToHabitat(int $chainId): void
    {
        $this->postJson("/api/habitats/{$this->habitatId}/families", [
            'evolution_chain_id' => $chainId,
        ])->assertStatus(201);
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
        // Las familias asignadas también exponen los tipos de TODA la familia (fix P0: el frontend
        // los conserva al reconstruir la familia devuelta a "no asignadas" tras removeFamily).
        $this->assertArrayHasKey('types', $data[0]);
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

    public function test_tipos_familia_son_union_de_todos_los_miembros_deduplicada_y_ordenada(): void
    {
        // La familia de 3 etapas del setUp (bulbasaur=1, ivysaur=2, venusaur=3) no está asignada a ningún hábitat.
        PokemonType::create(['pokemon_id' => 1, 'type' => TipoEnum::GRASS, 'slot' => 1]);
        PokemonType::create(['pokemon_id' => 2, 'type' => TipoEnum::POISON, 'slot' => 1]);
        PokemonType::create(['pokemon_id' => 3, 'type' => TipoEnum::POISON, 'slot' => 1]);

        $data = $this->getJson('/api/habitats/unassigned-families')->json();
        $family = collect($data)->firstWhere('evolution_chain_id', $this->chainId3Stages);

        // Veneno (id 4) antes que Planta (id 12) por orden de id; un union con dupe daría Veneno×2; base-only daría solo Planta.
        $this->assertSame([['id' => 4, 'name' => 'Veneno'], ['id' => 12, 'name' => 'Planta']], $family['types']);
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
        $this->assertEquals(1, $data['base']['id']);
        $this->assertEquals(1, $data['base']['level']);
        $this->assertCount(2, $data['evolutions']);
        $this->assertEquals(2, $data['evolutions'][0]['level']);
        $this->assertEquals(3, $data['evolutions'][1]['level']);
        // El total de miembros se deriva del body (el contrato ya no expone assigned_count)
        $this->assertCount(3, array_merge([$data['base']], $data['evolutions']));
        $this->assertArrayNotHasKey('assigned_count', $data);

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
        $this->assertEquals(19, $data['base']['id']);
        $this->assertEquals(1, $data['base']['level']);
        $this->assertCount(1, $data['evolutions']);
        $this->assertEquals(20, $data['evolutions'][0]['id']);
        $this->assertEquals(2, $data['evolutions'][0]['level']);
        // El total de miembros se deriva del body (el contrato ya no expone assigned_count)
        $this->assertCount(2, array_merge([$data['base']], $data['evolutions']));
        $this->assertArrayNotHasKey('assigned_count', $data);

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
        $this->assertEquals(151, $data['base']['id']);
        // Cadena de 1 etapa: totalStages === 1 → la base se asigna al nivel 2
        $this->assertEquals(2, $data['base']['level']);
        $this->assertCount(0, $data['evolutions']);
        // El total de miembros se deriva del body (el contrato ya no expone assigned_count)
        $this->assertCount(1, array_merge([$data['base']], $data['evolutions']));
        $this->assertArrayNotHasKey('assigned_count', $data);

        // Verify in database
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 151,
            'habitat_id' => $this->habitatId,
        ]);
    }

    public function test_asignar_familia_ramificada_asigna_nivel_2_a_todas_las_evoluciones(): void
    {
        // Cadena ramificada tipo Eevee: base (133) -> vaporeon (134) y jolteon (135), ambas stage 2.
        // La cadena se crea dentro del test para no alterar test_obtener_familias_sin_habitat_solo_cadenas_vacias.
        $chainBranched = EvolutionChain::create(['data' => '{"stages": 2}']);

        Pokemon::create([
            'id' => 133,
            'name' => 'eevee',
            'species_id' => 133,
            'base_experience' => 65,
            'capture_rate' => 45,
            'height' => 3,
            'weight' => 65,
            'evolution_chain_id' => $chainBranched->id,
        ]);
        Pokemon::create([
            'id' => 134,
            'name' => 'vaporeon',
            'species_id' => 134,
            'base_experience' => 184,
            'capture_rate' => 45,
            'height' => 10,
            'weight' => 290,
            'evolution_chain_id' => $chainBranched->id,
        ]);
        Pokemon::create([
            'id' => 135,
            'name' => 'jolteon',
            'species_id' => 135,
            'base_experience' => 184,
            'capture_rate' => 45,
            'height' => 8,
            'weight' => 245,
            'evolution_chain_id' => $chainBranched->id,
        ]);

        PokemonEvolution::create([
            'evolved_species_id' => 133,
            'evolves_from_species_id' => null,
            'minimum_level' => 1,
        ]);
        PokemonEvolution::create([
            'evolved_species_id' => 134,
            'evolves_from_species_id' => 133,
            'minimum_level' => 25,
        ]);
        PokemonEvolution::create([
            'evolved_species_id' => 135,
            'evolves_from_species_id' => 133,
            'minimum_level' => 25,
        ]);

        $response = $this->postJson("/api/habitats/{$this->habitatId}/families", [
            'evolution_chain_id' => $chainBranched->id,
        ]);

        $response->assertStatus(201);
        $data = $response->json();

        $this->assertEquals($chainBranched->id, $data['evolution_chain_id']);
        $this->assertEquals(133, $data['base']['id']);
        $this->assertEquals(1, $data['base']['level']);
        $this->assertCount(2, $data['evolutions']);
        $this->assertEqualsCanonicalizing([134, 135], array_column($data['evolutions'], 'id'));
        // Ambas evoluciones son stage 2 → nivel 2 real (el frontend ya no debe inferir 2,3,3)
        $this->assertEquals(2, $data['evolutions'][0]['level']);
        $this->assertEquals(2, $data['evolutions'][1]['level']);
        $this->assertArrayNotHasKey('assigned_count', $data);

        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 134,
            'habitat_id' => $this->habitatId,
            'level' => 2,
        ]);
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 135,
            'habitat_id' => $this->habitatId,
            'level' => 2,
        ]);
    }

    public function test_assign_family_upsert_no_duplica_si_ya_existe(): void
    {
        // First assignment
        $this->assignChainToHabitat($this->chainId3Stages);

        // Second assignment (should upsert, not duplicate)
        $this->assignChainToHabitat($this->chainId3Stages);

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
        $this->assignChainToHabitat($this->chainId3Stages);

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

    public function test_eliminar_familia_no_afecta_a_otro_habitat_con_la_misma_familia(): void
    {
        Habitat::create(['id' => 2, 'name' => 'Cueva', 'province_id' => 1]);
        $this->assignChainToHabitat($this->chainId3Stages);
        // La misma familia (bulbasaur=1, ivysaur=2, venusaur=3) también está en el hábitat 2.
        DB::table('pokemon_habitat')->insert([
            ['pokemon_id' => 1, 'habitat_id' => 2, 'level' => 1],
            ['pokemon_id' => 2, 'habitat_id' => 2, 'level' => 2],
            ['pokemon_id' => 3, 'habitat_id' => 2, 'level' => 3],
        ]);

        $response = $this->deleteJson("/api/habitats/{$this->habitatId}/families/{$this->chainId3Stages}");
        $response->assertStatus(200);
        $this->assertSame(3, $response->json()['removed_count']);

        // El DELETE solo afecta al hábitat 1: la misma familia en el hábitat 2 queda intacta.
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 1,
            'habitat_id' => 2,
            'level' => 1,
        ]);
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 2,
            'habitat_id' => 2,
            'level' => 2,
        ]);
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 3,
            'habitat_id' => 2,
            'level' => 3,
        ]);
        $this->assertDatabaseMissing('pokemon_habitat', [
            'pokemon_id' => 1,
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

    // ==========================================
    // PATCH /api/habitats/{habitat}/pokemon/{pokemon}
    // ==========================================

    public function test_mover_pokemon_actualiza_solo_ese_pokemon(): void
    {
        // Assign 3-stage chain (bulbasaur=1, ivysaur=2, venusaur=3)
        DB::table('pokemon_habitat')->insert([
            ['pokemon_id' => 1, 'habitat_id' => $this->habitatId, 'level' => 1],
            ['pokemon_id' => 2, 'habitat_id' => $this->habitatId, 'level' => 2],
            ['pokemon_id' => 3, 'habitat_id' => $this->habitatId, 'level' => 3],
        ]);

        // Move ivysaur (id 2) from level 2 to level 3
        $response = $this->patchJson("/api/habitats/{$this->habitatId}/pokemon/2", [
            'level' => 3,
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals($this->habitatId, $data['habitat_id']);
        $this->assertEquals(2, $data['pokemon_id']);
        $this->assertEquals(2, $data['previous_level']);
        $this->assertEquals(3, $data['new_level']);

        // Only pokemon 2 should change; the rest of the family stays untouched
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 2,
            'habitat_id' => $this->habitatId,
            'level' => 3,
        ]);
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 1,
            'habitat_id' => $this->habitatId,
            'level' => 1,
        ]);
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 3,
            'habitat_id' => $this->habitatId,
            'level' => 3,
        ]);
    }

    public function test_mover_pokemon_no_afecta_a_otro_habitat_con_la_misma_familia(): void
    {
        Habitat::create(['id' => 2, 'name' => 'Cueva', 'province_id' => 1]);
        $this->assignChainToHabitat($this->chainId3Stages);
        // La misma familia (bulbasaur=1, ivysaur=2, venusaur=3) también está en el hábitat 2.
        DB::table('pokemon_habitat')->insert([
            ['pokemon_id' => 1, 'habitat_id' => 2, 'level' => 1],
            ['pokemon_id' => 2, 'habitat_id' => 2, 'level' => 2],
            ['pokemon_id' => 3, 'habitat_id' => 2, 'level' => 3],
        ]);

        $this->patchJson("/api/habitats/{$this->habitatId}/pokemon/2", ['level' => 3])->assertStatus(200);

        // El PATCH solo afecta al hábitat 1: en el hábitat 2 el miembro sigue intacto.
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 2,
            'habitat_id' => 2,
            'level' => 2,
        ]);
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 2,
            'habitat_id' => $this->habitatId,
            'level' => 3,
        ]);
    }

    public function test_mover_pokemon_level_invalido_retorna_422(): void
    {
        DB::table('pokemon_habitat')->insert([
            ['pokemon_id' => 1, 'habitat_id' => $this->habitatId, 'level' => 1],
        ]);

        $response = $this->patchJson("/api/habitats/{$this->habitatId}/pokemon/1", [
            'level' => 4,
        ]);

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('errors', $data);
    }

    public function test_mover_pokemon_habitat_inexistente_retorna_422(): void
    {
        $response = $this->patchJson('/api/habitats/99999/pokemon/1', [
            'level' => 2,
        ]);

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('message', $data);
    }

    public function test_mover_pokemon_no_asignado_a_habitat_retorna_422(): void
    {
        // rattata (id 19) belongs to the 2-stage chain, but no pivot row for this habitat
        $response = $this->patchJson("/api/habitats/{$this->habitatId}/pokemon/19", [
            'level' => 2,
        ]);

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('message', $data);

        // The missing pokemon case raises the same invalid-argument path when habitat exists
        $this->assertDatabaseMissing('pokemon_habitat', [
            'pokemon_id' => 19,
            'habitat_id' => $this->habitatId,
        ]);
    }
}
