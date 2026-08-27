<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\ExploracionActiva;
use Src\Shared\Bus\Command;

/**
 * Reparte todas las recompensas de una exploración (pokedex, capturas,
 * caramelos familia/EV/tipo, EXP) y marca el regreso. Idempotente.
 */
final class FinalizarExploracionCommand implements Command
{
    public function __construct(
        public readonly ExploracionActiva $exploracion,
    ) {
    }
}
