<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Reclutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CapturarPokemonJob implements ShouldQueue
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
        public float $captureChance = 0.3,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $roll = mt_rand(1, 100) / 100;
        if ($roll <= $this->captureChance) {
            $existing = Reclutable::where('pokemon_id', $this->pokemonId)->first();
            if ($existing) {
                $existing->increment('cantidad');
            } else {
                Reclutable::create([
                    'pokemon_id' => $this->pokemonId,
                    'cantidad' => 1,
                ]);
            }
        }
    }
}
