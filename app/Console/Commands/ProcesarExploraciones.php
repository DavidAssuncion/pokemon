<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Exploraciones\App\ProcesarExploracionesPendientes;

class ProcesarExploraciones extends Command
{
    protected $signature = 'exploraciones:procesar';

    protected $description = 'Procesa las exploraciones activas: encuentros, vuelta y recompensas';

    public function __construct(
        private readonly ProcesarExploracionesPendientes $pendientes,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Multiplayer: comparte el servicio y el lock por exploración con la
        // página y con `recoger`. `paraTodas()` usa withoutUserScope() porque
        // en CLI no hay sesión; el throttle de 30 s evita solapamientos.
        $resultado = $this->pendientes->paraTodas();

        $this->info(sprintf(
            'Exploraciones procesadas: %d, omitidas: %d, fallidas: %d',
            $resultado->procesadas,
            $resultado->omitidas,
            $resultado->fallidas,
        ));

        return self::SUCCESS;
    }
}
