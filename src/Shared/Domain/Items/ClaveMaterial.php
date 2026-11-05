<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Items;

/**
 * Materiales básicos de forja.
 */
enum ClaveMaterial: string
{
    case RECIPIENTE = 'recipiente';

    /**
     * Clave canónica de inventario: `material:{slug}`.
     */
    public function clave(): string
    {
        return CategoriaItem::MATERIAL->prefijo().$this->value;
    }

    /**
     * Nombre visible en español.
     */
    public function nombre(): string
    {
        return match ($this) {
            self::RECIPIENTE => 'Recipiente',
        };
    }

    /**
     * Ruta pública del icono del material.
     */
    public function archivoIcono(): string
    {
        return "/images/items/material/{$this->value}.webp";
    }
}
