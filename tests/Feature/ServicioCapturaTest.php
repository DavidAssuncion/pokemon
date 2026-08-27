<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ActualizarPokedexJob;
use App\Jobs\CapturarPokemonJob;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Src\Reclutamiento\App\ServicioCaptura;
use Tests\TestCase;

class ServicioCapturaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Province::create(['id' => 1, 'name' => 'Kanto']);
        Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
    }

    public function test_dispatches_jobs_for_each_defeated_pokemon(): void
    {
        Queue::fake();

        $pokemon1 = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);
        $pokemon2 = Pokemon::create([
            'id' => 2,
            'name' => 'charmander',
            'species_id' => 4,
            'capture_rate' => 190,
            'base_experience' => 62,
            'height' => 6,
            'weight' => 85,
        ]);

        $servicio = new ServicioCaptura();
        $servicio->procesarCapturas([1, 2]);

        Queue::assertPushed(ActualizarPokedexJob::class, 2);
        Queue::assertPushed(CapturarPokemonJob::class, 2);

        // Verify the capture chances are based on capture_rate / 255
        Queue::assertPushed(CapturarPokemonJob::class, function ($job) {
            return $job->pokemonId === 1 && abs($job->captureChance - 45 / 255) < 0.001;
        });
        Queue::assertPushed(CapturarPokemonJob::class, function ($job) {
            return $job->pokemonId === 2 && abs($job->captureChance - 190 / 255) < 0.001;
        });
    }

    public function test_skips_nonexistent_pokemon(): void
    {
        Queue::fake();

        $servicio = new ServicioCaptura();
        $servicio->procesarCapturas([999]);

        Queue::assertNotPushed(ActualizarPokedexJob::class);
        Queue::assertNotPushed(CapturarPokemonJob::class);
    }

    public function test_marks_pokemon_as_avistado(): void
    {
        Queue::fake();

        $pokemon = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $servicio = new ServicioCaptura();
        $servicio->procesarCapturas([1]);

        Queue::assertPushed(ActualizarPokedexJob::class, function ($job) {
            return $job->pokemonId === 1 && $job->estado === 'AVISTADO';
        });
    }
}
