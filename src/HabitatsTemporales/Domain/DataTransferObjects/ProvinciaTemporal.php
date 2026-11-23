<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\DataTransferObjects;

use Src\HabitatsTemporales\Domain\Collections\FaccionResumenCollection;

/**
 * Provincia virtual «Temporal»: agrupa las facciones desbloqueadas pendientes.
 */
final readonly class ProvinciaTemporal
{
    public function __construct(
        public string $nombre,
        public string $descripcion,
        public FaccionResumenCollection $facciones,
    ) {
    }

    public function total(): int
    {
        return $this->facciones->count();
    }

    /**
     * @return array{nombre: string, descripcion: string, total: int, facciones: list<array{slug: string, nombre: string, descripcion: string, imagen_url: string, color: string, oleada_actual: int, combate_actual: int, total_oleadas: int, combates_por_oleada: int, estado: string, motivo_bloqueo: string|null, enlace: string}>}
     */
    public function toArray(): array
    {
        return [
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'total' => $this->total(),
            'facciones' => $this->facciones->toArray(),
        ];
    }
}
