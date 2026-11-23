<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\DataTransferObjects;

/**
 * Resumen de una facción para la tarjeta de la provincia Temporal.
 */
final readonly class FaccionResumen
{
    public function __construct(
        public string $slug,
        public string $nombre,
        public string $descripcion,
        public string $imagenUrl,
        public string $color,
        public int $oleadaActual,
        public int $combateActual,
        public int $totalOleadas,
        public int $combatesPorOleada,
        public string $estado,
        public ?string $motivoBloqueo,
        public string $enlace,
    ) {
    }

    /**
     * @return array{slug: string, nombre: string, descripcion: string, imagen_url: string, color: string, oleada_actual: int, combate_actual: int, total_oleadas: int, combates_por_oleada: int, estado: string, motivo_bloqueo: string|null, enlace: string}
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'imagen_url' => $this->imagenUrl,
            'color' => $this->color,
            'oleada_actual' => $this->oleadaActual,
            'combate_actual' => $this->combateActual,
            'total_oleadas' => $this->totalOleadas,
            'combates_por_oleada' => $this->combatesPorOleada,
            'estado' => $this->estado,
            'motivo_bloqueo' => $this->motivoBloqueo,
            'enlace' => $this->enlace,
        ];
    }
}
