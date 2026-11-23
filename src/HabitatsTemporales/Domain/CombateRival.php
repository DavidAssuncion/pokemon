<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain;

use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;

/**
 * Un combate concreto de una oleada: 3 reclutas o el jefe final.
 */
final readonly class CombateRival
{
    public function __construct(
        public int $numero,
        public string $nombre,
        public string $entrenador,
        public string $descripcion,
        public string $sprite,
        public bool $esJefe,
        public EquipoEtapaGimnasio $equipo,
    ) {
    }
}
