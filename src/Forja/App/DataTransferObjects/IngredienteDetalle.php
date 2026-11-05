<?php

declare(strict_types=1);

namespace Src\Forja\App\DataTransferObjects;

/**
 * Ingrediente de una receta con su stock actual para el jugador.
 */
final readonly class IngredienteDetalle
{
    public function __construct(
        public string $clave,
        public string $nombre,
        public string $icono,
        public int $requerido,
        public int $disponible,
        public ?string $origen = null,
    ) {
    }

    /**
     * Frontera — shape consumido por la API/UI.
     *
     * @return array{
     *     clave: string,
     *     nombre: string,
     *     icono: string,
     *     requerido: int,
     *     disponible: int,
     *     origen: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'icono' => $this->icono,
            'requerido' => $this->requerido,
            'disponible' => $this->disponible,
            'origen' => $this->origen,
        ];
    }
}
