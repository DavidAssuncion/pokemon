<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain;

use Src\Shared\Collections\IntCollection;

/**
 * Plantilla interna de un combate del catálogo (recluta o jefe).
 *
 * No es un contrato de capa: solo alimenta la construcción de `CombateRival`.
 */
final readonly class PlantillaCombate
{
    public function __construct(
        public string $nombre,
        public string $entrenador,
        public string $sprite,
        public string $descripcion,
        public IntCollection $vanguardia,
        public IntCollection $retaguardia,
    ) {
    }
}
