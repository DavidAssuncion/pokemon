<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\RecompilarHabitatJsonJob;
use App\Models\Habitat;
use App\Models\Pokedex;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecompilarHabitatJsonJobTest extends TestCase
{
    use RefreshDatabase;

    private int $habitatId;
    private int $pokemonId1;
    private int $pokemonId2;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = User::factory()->create()->id;

        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        $this->habitatId = $habitat->id;

        $pokemon1 = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);
        $this->pokemonId1 = $pokemon1->id;

        $pokemon2 = Pokemon::create([
            'id' => 2,
            'name' => 'charmander',
            'species_id' => 4,
            'capture_rate' => 45,
            'base_experience' => 62,
            'height' => 6,
            'weight' => 85,
        ]);
        $this->pokemonId2 = $pokemon2->id;

        $habitat->pokemon()->attach([$pokemon1->id, $pokemon2->id], ['level' => 1]);
    }

    public function test_compiles_pokemon_json_with_no_pokedex_entries(): void
    {
        RecompilarHabitatJsonJob::dispatch($this->habitatId);

        $habitat = Habitat::find($this->habitatId);
        $this->assertNotNull($habitat->pokemons);
        $this->assertCount(2, $habitat->pokemons);

        $names = array_column($habitat->pokemons, 'nombre');
        $this->assertContains('bulbasaur', $names);
        $this->assertContains('charmander', $names);

        // All should be visto=false, atrapado=false
        foreach ($habitat->pokemons as $p) {
            $this->assertFalse($p['visto']);
            $this->assertFalse($p['atrapado']);
        }
    }

    public function test_compiles_pokemon_json_with_pokedex_entries(): void
    {
        Pokedex::create(['user_id' => $this->userId, 'pokemon_id' => $this->pokemonId1, 'visto' => true, 'atrapado' => true]);
        Pokedex::create(['user_id' => $this->userId, 'pokemon_id' => $this->pokemonId2, 'visto' => true, 'atrapado' => false]);

        RecompilarHabitatJsonJob::dispatch($this->habitatId);

        $habitat = Habitat::find($this->habitatId);
        $this->assertNotNull($habitat->pokemons);

        $bulbaData = collect($habitat->pokemons)->firstWhere('nombre', 'bulbasaur');
        $this->assertTrue($bulbaData['visto']);
        $this->assertTrue($bulbaData['atrapado']);

        $charData = collect($habitat->pokemons)->firstWhere('nombre', 'charmander');
        $this->assertTrue($charData['visto']);
        $this->assertFalse($charData['atrapado']);
    }

    public function test_nonexistent_habitat_does_not_throw(): void
    {
        // Should not throw any exception
        RecompilarHabitatJsonJob::dispatch(99999);

        $this->assertTrue(true); // No exception = pass
    }
}
