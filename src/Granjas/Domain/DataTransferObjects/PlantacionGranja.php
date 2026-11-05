<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use Src\Granjas\Domain\Entities\Plantacion;
use Src\Granjas\Domain\ReglasPlantacion;
use Src\Shared\Domain\Items\CatalogoItems;

/**
 * Plantación resuelta para la API/UI: incluye nombre e icono de la baya y el
 * stock/progreso calculados. La presentación no recalcula nada.
 */
final readonly class PlantacionGranja
{
    public function __construct(
        public int $slot,
        public string $berryKey,
        public string $berryNombre,
        public string $berryIcono,
        public string $plantedAt,
        public int $stock,
        public int $stockMax,
        public bool $puedeCosechar,
        public int $progresoPct,
        public string $estado,
    ) {
    }

    public static function desde(Plantacion $plantacion, \DateTimeImmutable $ahora): self
    {
        $definicion = CatalogoItems::definicion($plantacion->berryKey);

        return new self(
            slot: $plantacion->slotIndex,
            berryKey: $plantacion->berryKey,
            berryNombre: $definicion->nombre,
            berryIcono: $definicion->icono,
            plantedAt: $plantacion->plantedAt->format(\DateTimeInterface::ATOM),
            stock: ReglasPlantacion::stockDe($plantacion, $ahora),
            stockMax: ReglasPlantacion::STOCK_MAXIMO,
            puedeCosechar: ReglasPlantacion::puedeCosechar($plantacion, $ahora),
            progresoPct: ReglasPlantacion::progresoPct($plantacion, $ahora),
            estado: ReglasPlantacion::estadoDe($plantacion, $ahora)->value,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'slot' => $this->slot,
            'berry_key' => $this->berryKey,
            'berry_nombre' => $this->berryNombre,
            'berry_icono' => $this->berryIcono,
            'planted_at' => $this->plantedAt,
            'stock' => $this->stock,
            'stock_max' => $this->stockMax,
            'puede_cosechar' => $this->puedeCosechar,
            'progreso_pct' => $this->progresoPct,
            'estado' => $this->estado,
        ];
    }
}
