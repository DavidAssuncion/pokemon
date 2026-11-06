<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte\DTO;

/**
 * Resultado de aplicar una acción de combate ya resuelta: líneas de log y
 * eventos de animación. No contiene estado de sesión ni de componente.
 */
final readonly class ResultadoAccionBatalla
{
    /**
     * @param  list<string>  $log
     * @param  list<array<string, mixed>>  $eventos
     */
    public function __construct(
        public array $log,
        public array $eventos,
    ) {
    }
}
