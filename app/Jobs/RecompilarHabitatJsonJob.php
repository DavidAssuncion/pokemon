<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Habitat;
use App\Models\Pokedex;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecompilarHabitatJsonJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $habitatId,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $habitat = Habitat::find($this->habitatId);
        if (! $habitat) {
            return;
        }

        /** @var \Illuminate\Support\Collection<int, array{pokemon_id: int, nombre: string, visto: bool, atrapado: bool}> $pokemonData */
        $pokemonIds = $habitat->pokemon->pluck('id')->all();
        /** @var \Illuminate\Support\EloquentCollection<int, \App\Models\Pokedex> $pokedexMap */
        $pokedexMap = Pokedex::whereIn('pokemon_id', $pokemonIds)->get()->keyBy('pokemon_id');

        $pokemonData = $habitat->pokemon->map(function ($p) use ($pokedexMap) {
            $pokedex = $pokedexMap->get($p->id);

            return [
                'pokemon_id' => $p->id,
                'nombre' => $p->name,
                'visto' => $pokedex ? $pokedex->visto : false,
                'atrapado' => $pokedex ? $pokedex->atrapado : false,
            ];
        })->toArray();

        $habitat->update(['pokemons' => $pokemonData]);
    }
}
