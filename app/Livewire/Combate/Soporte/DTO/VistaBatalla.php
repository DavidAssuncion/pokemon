<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte\DTO;

/**
 * Proyección efímera del agregado hacia el estado público del componente.
 */
final readonly class VistaBatalla
{
    /**
     * @param  list<string>  $logNuevo
     * @param  list<array<string, mixed>>  $team1
     * @param  list<array<string, mixed>>  $team2
     * @param  list<array<string, mixed>>  $objetosCombate
     * @param  list<array{clave: string, label: string, acumulaciones: int}>  $trampas1
     * @param  list<array{clave: string, label: string, acumulaciones: int}>  $trampas2
     */
    public function __construct(
        public string $weather,
        public array $logNuevo,
        public array $team1,
        public array $team2,
        public array $objetosCombate,
        public array $trampas1 = [],
        public array $trampas2 = [],
    ) {
    }
}
