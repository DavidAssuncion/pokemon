<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExploracionActiva;
use Illuminate\Console\Command;
use Src\Exploraciones\App\ProcesarExploracionService;

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
            app(ProcesarExploracionService::class)->procesar($exploracion);
        }

        $this->info(sprintf('Exploraciones procesadas: %d', $exploraciones->count()));

        return self::SUCCESS;
    }
}
