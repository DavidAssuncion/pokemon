<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Items;

/**
 * Definición inmutable de un item del catálogo compartido.
 */
final readonly class DefinicionItem
{
    public function __construct(
        public string $clave,
        public string $nombre,
        public CategoriaItem $categoria,
        public bool $equipable,
        public bool $usableEnCombate,
        public bool $consumible,
        public string $icono,
        public string $descripcion,
    ) {
    }

    public function esBaya(): bool
    {
        return $this->categoria === CategoriaItem::BAYA;
    }

    /**
     * Frontera — shape consumido por la presentación/API.
     *
     * @return array{
     *     clave: string,
     *     nombre: string,
     *     categoria: string,
     *     equipable: bool,
     *     usable_en_combate: bool,
     *     consumible: bool,
     *     icono: string,
     *     descripcion: string,
     *     es_baya: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'categoria' => $this->categoria->value,
            'equipable' => $this->equipable,
            'usable_en_combate' => $this->usableEnCombate,
            'consumible' => $this->consumible,
            'icono' => $this->icono,
            'descripcion' => $this->descripcion,
            'es_baya' => $this->esBaya(),
        ];
    }
}
