<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\DataTransferObjects;

/**
 * Hábitat sugerido para preparar un gimnasio: cuántos de sus Pokémon tienen un
 * tipo supereficaz contra el tipo del gimnasio y a partir de qué nivel mínimo
 * se pueden encontrar.
 */
final readonly class HabitatRecomendado
{
    public function __construct(
        public int $habitatId,
        public string $nombre,
        public string $provincia,
        public int $coincidencias,
        public int $total,
        public int $nivelMinimo,
    ) {
    }

    /**
     * @return array{habitat_id: int, nombre: string, provincia: string, coincidencias: int, total: int, nivel_minimo: int}
     */
    public function toArray(): array
    {
        return [
            'habitat_id' => $this->habitatId,
            'nombre' => $this->nombre,
            'provincia' => $this->provincia,
            'coincidencias' => $this->coincidencias,
            'total' => $this->total,
            'nivel_minimo' => $this->nivelMinimo,
        ];
    }
}
