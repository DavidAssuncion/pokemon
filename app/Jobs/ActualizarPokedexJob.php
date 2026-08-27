<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Pokedex;
use App\Models\Pokemon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ActualizarPokedexJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $pokemonId,
        /** @var 'AVISTADO'|'RECLUTADO' */
        public string $estado,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $visto = true;
        $atrapado = $this->estado === 'RECLUTADO';

        // If already captured, don't downgrade to sighted-only
        if (! $atrapado) {
            $existing = Pokedex::where('pokemon_id', $this->pokemonId)->first();
            if ($existing && $existing->atrapado) {
                return;
            }
        }

        Pokedex::updateOrCreate(
            ['pokemon_id' => $this->pokemonId],
            ['visto' => $visto, 'atrapado' => $atrapado]
        );

        // Get the habitats of this pokemon and dispatch recompilation
        $pokemon = Pokemon::find($this->pokemonId);
        if ($pokemon) {
            foreach ($pokemon->habitats as $habitat) {
                RecompilarHabitatJsonJob::dispatch($habitat->id);
            }
        }
    }
}
