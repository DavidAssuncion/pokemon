<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\DataTransferObjects;

use Src\HabitatsTemporales\Domain\Collections\CombateVistaCollection;

/**
 * Detalle de una facción con la oleada actual completa (4 combates) y el
 * combate actual. Las oleadas futuras no se envían.
 */
final readonly class FaccionDetalle
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
        public OleadaActualVista $oleada,
        public ?CombateVista $combate,
        public CombateVistaCollection $combates,
    ) {
    }

    /**
     * @return array{faccion: array{slug: string, nombre: string, descripcion: string, imagen_url: string, color: string, oleada_actual: int, combate_actual: int, total_oleadas: int, combates_por_oleada: int, estado: string, motivo_bloqueo: string|null}, oleada: array{numero: int, estado: string, motivo_bloqueo: string|null}, combate: array{numero: int, nombre: string, entrenador: string, descripcion: string, sprite_url: string, es_jefe: bool, estado: string, motivo_bloqueo: string|null}|null, combates: list<array{numero: int, nombre: string, entrenador: string, descripcion: string, sprite_url: string, es_jefe: bool, estado: string, motivo_bloqueo: string|null}>}
     */
    public function toArray(): array
    {
        return [
            'faccion' => [
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
            ],
            'oleada' => $this->oleada->toArray(),
            'combate' => $this->combate?->toArray(),
            'combates' => $this->combates->toArray(),
        ];
    }
}
