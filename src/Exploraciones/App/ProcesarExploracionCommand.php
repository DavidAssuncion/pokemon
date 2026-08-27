<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\ExploracionActiva;
use Src\Shared\Bus\Command;

/**
 * Tick de exploración: genera encuentros pendientes y, si toca, despacha
 * FinalizarExploracionCommand a través del bus.
 */
final class ProcesarExploracionCommand implements Command
{
    public function __construct(
        public readonly ExploracionActiva $exploracion,
        public readonly bool $forzarRegreso = false,
    ) {
    }
}
