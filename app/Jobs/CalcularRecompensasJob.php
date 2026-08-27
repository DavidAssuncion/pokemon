<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Caramelo;
use App\Models\ExploracionActiva;
use App\Models\Pokemon;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalcularRecompensasJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $exploracionId,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $exploracion = ExploracionActiva::with('team.members.reclutado.pokemon')->find($this->exploracionId);
        if (! $exploracion || ! $exploracion->eventos) {
            return;
        }

        /** @var array<int> $defeated */
        $defeated = $exploracion->eventos['derrotados'] ?? [];

        // Pre-fetch all defeated Pokemon with evolution chain to avoid N+1
        /** @var \Illuminate\Support\EloquentCollection<int, \App\Models\Pokemon> $defeatedPokemon */
        $defeatedPokemon = Pokemon::with('evolutionChain.pokemon')
            ->whereIn('id', $defeated)
            ->get()
            ->keyBy('id');

        // Group by evolution_chain_id and count phases for candy rewards
        /** @var array<int, int> $candyRewards */
        $candyRewards = [];
        foreach ($defeated as $pokemonId) {
            $pokemon = $defeatedPokemon->get($pokemonId);
            if (! $pokemon || ! $pokemon->evolution_chain_id) {
                continue;
            }
            $chainId = $pokemon->evolution_chain_id;
            if (! isset($candyRewards[$chainId])) {
                $candyRewards[$chainId] = 0;
            }
            // Phase = number of pokemon in chain up to this one (by species_id)
            /** @var \Illuminate\Database\Eloquent\Collection<int, Pokemon> $chainPokemon */
            $chainPokemon = $pokemon->evolutionChain->pokemon;
            $phase = $chainPokemon->where('species_id', '<=', $pokemon->species_id)->count();
            $candyRewards[$chainId] += $phase;
        }

        // Store candies
        foreach ($candyRewards as $chainId => $amount) {
            $existing = Caramelo::where('evolution_chain_id', $chainId)->first();
            if ($existing) {
                $existing->increment('cantidad', $amount);
            } else {
                Caramelo::create([
                    'evolution_chain_id' => $chainId,
                    'cantidad' => $amount,
                ]);
            }
        }

        // Distribute experience equally among team members
        $totalExp = count($defeated) * 10; // base exp per pokemon defeated
        $members = $exploracion->team->members;
        if ($members->count() > 0) {
            $expEach = intdiv($totalExp, $members->count());
            foreach ($members as $member) {
                $reclutado = $member->reclutado;
                if ($reclutado) {
                    /** @var array{total: int} $currentExp */
                    $currentExp = $reclutado->exp ?? ['total' => 0];
                    $currentExp['total'] = ($currentExp['total'] ?? 0) + $expEach;
                    $reclutado->update(['exp' => $currentExp]);
                }
            }
        }

        // Mark exploration as complete — frees the team for new explorations
        // and unblocks construction in the habitat
        $exploracion->update(['regreso' => Carbon::now()]);
    }
}
