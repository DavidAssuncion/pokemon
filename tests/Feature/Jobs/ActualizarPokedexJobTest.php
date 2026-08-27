<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\ActualizarPokedexJob;
use App\Jobs\RecompilarHabitatJsonJob;
use App\Models\Habitat;
use App\Models\Pokedex;
use App\Models\Pokemon;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ActualizarPokedexJobTest extends TestCase
{
    use RefreshDatabase;

    private int $pokemonId;

    protected function setUp(): void
    {
        parent::setUp();

        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);

        $pokemon = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $habitat->pokemon()->attach($pokemon->id, ['level' => 1]);

        $this->pokemonId = $pokemon->id;
    }

    public function test_avistado_creates_pokedex_entry_with_visto_true(): void
    {
        ActualizarPokedexJob::dispatch($this->pokemonId, 'AVISTADO');

        $pokedex = Pokedex::where('pokemon_id', $this->pokemonId)->first();
        $this->assertNotNull($pokedex);
        $this->assertTrue($pokedex->visto);
        $this->assertFalse($pokedex->atrapado);
    }

    public function test_reclutado_creates_pokedex_entry_with_both_true(): void
    {
        ActualizarPokedexJob::dispatch($this->pokemonId, 'RECLUTADO');

        $pokedex = Pokedex::where('pokemon_id', $this->pokemonId)->first();
        $this->assertNotNull($pokedex);
        $this->assertTrue($pokedex->visto);
        $this->assertTrue($pokedex->atrapado);
    }

    public function test_avistado_does_not_overwrite_reclutado(): void
    {
        // First mark as RECLUTADO
        ActualizarPokedexJob::dispatch($this->pokemonId, 'RECLUTADO');

        $pokedex = Pokedex::where('pokemon_id', $this->pokemonId)->first();
        $this->assertTrue($pokedex->atrapado);

        // Then mark as AVISTADO — atrapado should remain true (M1: never downgrade)
        ActualizarPokedexJob::dispatch($this->pokemonId, 'AVISTADO');

        $pokedex->refresh();
        $this->assertTrue($pokedex->visto);
        $this->assertTrue($pokedex->atrapado);
    }

    public function test_upsert_updates_existing_record(): void
    {
        Pokedex::create([
            'pokemon_id' => $this->pokemonId,
            'visto' => true,
            'atrapado' => false,
        ]);

        ActualizarPokedexJob::dispatch($this->pokemonId, 'RECLUTADO');

        $this->assertEquals(1, Pokedex::where('pokemon_id', $this->pokemonId)->count());
        $pokedex = Pokedex::where('pokemon_id', $this->pokemonId)->first();
        $this->assertTrue($pokedex->atrapado);
    }

    public function test_dispatches_recompilar_habitat_json_job(): void
    {
        Queue::fake();

        ActualizarPokedexJob::dispatch($this->pokemonId, 'AVISTADO');

        Queue::assertPushed(RecompilarHabitatJsonJob::class, 1);
    }
}
