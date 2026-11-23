<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Habitats\App\RecalcularNivelesMinimosHabitat;

/**
 * Recalcula el nivel mínimo de jugador de cada spawn de hábitat.
 *
 * Respeta los spawns con `manual = true` (ajuste del administrador). Sin
 * `--habitat` recorre todos los hábitats.
 */
final class RecalcularNivelesHabitat extends Command
{
    protected $signature = 'habitats:recalcular-niveles
                            {--habitat= : ID del hábitat a recalcular (opcional)}';

    protected $description = 'Recalcula el nivel mínimo de jugador de los spawns de hábitat';

    public function handle(RecalcularNivelesMinimosHabitat $recalcular): int
    {
        $habitat = $this->option('habitat');

        $actualizados = $habitat !== null
            ? $recalcular->paraHabitat((int) $habitat)
            : $recalcular->todos();

        $this->info("Spawns actualizados: {$actualizados}");

        return self::SUCCESS;
    }
}
