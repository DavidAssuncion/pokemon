<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExploracionActiva;
use Illuminate\Console\Command;
use Src\Exploraciones\App\ProcesarExploracionCommand;
use Src\Shared\Bus\CommandBus;

class ProcesarExploraciones extends Command
{
    protected $signature = 'exploraciones:procesar';

    protected $description = 'Procesa las exploraciones activas: encuentros, vuelta y recompensas';

    public function __construct(
        private readonly CommandBus $bus,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Multiplayer: el comando procesa TODOS los usuarios, no solo el
        // autenticado (CLI sin sesión; el scope de BelongsToUser quedaría inactivo,
        // pero se explicita para no depender del estado de auth).
        $exploraciones = ExploracionActiva::withoutUserScope()
            ->whereNull('regreso')
            ->with([
                'reclutado.pokemon.stats',
                'reclutado.pokemon.types',
                'habitat',
            ])
            ->get();

        foreach ($exploraciones as $exploracion) {
            $this->bus->dispatch(new ProcesarExploracionCommand($exploracion));
        }

        $this->info(sprintf('Exploraciones procesadas: %d', $exploraciones->count()));

        return self::SUCCESS;
    }
}
