<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ActualizarPokedexJob;
use App\Jobs\CapturarPokemonJob;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Src\Reclutamiento\App\ServicioCaptura;
use Tests\TestCase;

class ServicioCapturaTest extends TestCase
{
    use RefreshDatabase;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = User::factory()->create()->id;

        Province::create(['id' => 1, 'name' => 'Kanto']);
        Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
    }

    public function test_dispatches_jobs_for_each_defeated_pokemon(): void
    {
        // Jobs síncronos (sin ShouldQueue): Bus::fake() registra el dispatch
        // sin ejecutarlo; Queue::fake() ya no aplica (dispatchNow no toca la cola).
        Bus::fake();

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
        $servicio->procesarCapturas([1, 2], $this->userId);

        Bus::assertDispatched(ActualizarPokedexJob::class, 2);
        Bus::assertDispatched(CapturarPokemonJob::class, 2);

        // Verify the capture chances are based on capture_rate / 255
        Bus::assertDispatched(CapturarPokemonJob::class, function ($job) {
            return $job->userId === $this->userId
                && $job->pokemonId === 1
                && abs($job->captureChance - 45 / 255) < 0.001;
        });
        Bus::assertDispatched(CapturarPokemonJob::class, function ($job) {
            return $job->userId === $this->userId
                && $job->pokemonId === 2
                && abs($job->captureChance - 190 / 255) < 0.001;
        });
    }

    public function test_skips_nonexistent_pokemon(): void
    {
        Bus::fake();

        $servicio = new ServicioCaptura();
        $servicio->procesarCapturas([999], $this->userId);

        Bus::assertNotDispatched(ActualizarPokedexJob::class);
        Bus::assertNotDispatched(CapturarPokemonJob::class);
    }

    public function test_marks_pokemon_as_avistado(): void
    {
        Bus::fake();

        Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $servicio = new ServicioCaptura();
        $servicio->procesarCapturas([1], $this->userId);

        Bus::assertDispatched(ActualizarPokedexJob::class, function ($job) {
            return $job->userId === $this->userId
                && $job->pokemonId === 1
                && $job->estado === 'AVISTADO';
        });
    }
}
