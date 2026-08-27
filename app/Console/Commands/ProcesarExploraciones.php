<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcesarExploracionJob;
use App\Models\ExploracionActiva;
use Illuminate\Console\Command;

class ProcesarExploraciones extends Command
{
    protected $signature = 'exploraciones:procesar';

    protected $description = 'Procesa las exploraciones activas: encuentros, vuelta y recompensas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $exploraciones = ExploracionActiva::whereNull('regreso')
            ->with('team.members', 'habitat')
            ->get();

        foreach ($exploraciones as $exploracion) {
            ProcesarExploracionJob::dispatch($exploracion->id);
        }

        $this->info(sprintf('Exploraciones procesadas: %d', $exploraciones->count()));

        return self::SUCCESS;
    }
}
