<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\CapturarPokemonJob;
use App\Models\Pokemon;
use App\Models\Reclutable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapturarPokemonJobTest extends TestCase
{
    use RefreshDatabase;

    private int $pokemonId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->userId = $user->id;

        $pokemon = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $this->pokemonId = $pokemon->id;
    }

    public function test_capture_with_100_percent_chance_always_succeeds(): void
    {
        CapturarPokemonJob::dispatch($this->userId, $this->pokemonId, 1.0);

        $reclutable = Reclutable::where('user_id', $this->userId)
            ->where('pokemon_id', $this->pokemonId)
            ->first();
        $this->assertNotNull($reclutable);
        $this->assertEquals(1, $reclutable->cantidad);
    }

    public function test_capture_with_0_percent_chance_never_succeeds(): void
    {
        CapturarPokemonJob::dispatch($this->userId, $this->pokemonId, 0.0);

        $reclutable = Reclutable::where('user_id', $this->userId)
            ->where('pokemon_id', $this->pokemonId)
            ->first();
        $this->assertNull($reclutable);
    }

    public function test_increment_cantidad_on_existing_reclutable(): void
    {
        Reclutable::create(['user_id' => $this->userId, 'pokemon_id' => $this->pokemonId, 'cantidad' => 3]);

        CapturarPokemonJob::dispatch($this->userId, $this->pokemonId, 1.0);

        $reclutable = Reclutable::where('user_id', $this->userId)
            ->where('pokemon_id', $this->pokemonId)
            ->first();
        $this->assertEquals(4, $reclutable->cantidad);
    }

    public function test_creates_reclutable_if_not_exists(): void
    {
        $this->assertDatabaseCount('reclutables', 0);

        CapturarPokemonJob::dispatch($this->userId, $this->pokemonId, 1.0);

        $this->assertDatabaseCount('reclutables', 1);
    }

    public function test_multiple_jobs_accumulate_correctly(): void
    {
        CapturarPokemonJob::dispatch($this->userId, $this->pokemonId, 1.0);
        CapturarPokemonJob::dispatch($this->userId, $this->pokemonId, 1.0);
        CapturarPokemonJob::dispatch($this->userId, $this->pokemonId, 1.0);

        $reclutable = Reclutable::where('user_id', $this->userId)
            ->where('pokemon_id', $this->pokemonId)
            ->first();
        $this->assertEquals(3, $reclutable->cantidad);
    }

    public function test_captura_de_un_usuario_no_crea_filas_para_otro(): void
    {
        $otroUsuario = User::factory()->create();

        CapturarPokemonJob::dispatch($this->userId, $this->pokemonId, 1.0);

        $this->assertDatabaseMissing('reclutables', [
            'user_id' => $otroUsuario->id,
            'pokemon_id' => $this->pokemonId,
        ]);
        $this->assertDatabaseHas('reclutables', [
            'user_id' => $this->userId,
            'pokemon_id' => $this->pokemonId,
            'cantidad' => 1,
        ]);
    }
}
