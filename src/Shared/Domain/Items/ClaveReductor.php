<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Items;

/**
 * Objetos de forja que reducen temporalmente una estadística del rival.
 */
enum ClaveReductor: string
{
    case ATAQUE = 'ataque';
    case DEFENSA = 'defensa';
    case ATAQUE_ESPECIAL = 'ataque_especial';
    case DEFENSA_ESPECIAL = 'defensa_especial';
    case VELOCIDAD = 'velocidad';

    /**
     * Clave canónica de inventario: `reductor:{slug}`.
     */
    public function clave(): string
    {
        return CategoriaItem::REDUCTOR->prefijo().$this->value;
    }

    /**
     * Nombre visible en español.
     */
    public function nombre(): string
    {
        return match ($this) {
            self::ATAQUE => 'Reductor de Ataque',
            self::DEFENSA => 'Reductor de Defensa',
            self::ATAQUE_ESPECIAL => 'Reductor de Ataque Especial',
            self::DEFENSA_ESPECIAL => 'Reductor de Defensa Especial',
            self::VELOCIDAD => 'Reductor de Velocidad',
        };
    }

    /**
     * Baya de origen usada como ingrediente y como icono temporal.
     */
    public function bayaOrigen(): ClaveBaya
    {
        return match ($this) {
            self::ATAQUE => ClaveBaya::KELPSY,
            self::DEFENSA => ClaveBaya::QUALOT,
            self::ATAQUE_ESPECIAL => ClaveBaya::HONDEW,
            self::DEFENSA_ESPECIAL => ClaveBaya::GREPA,
            self::VELOCIDAD => ClaveBaya::TAMATO,
        };
    }

    /**
     * Ruta pública del icono. Reutiliza el icono de la baya de origen
     * hasta disponer de assets definitivos.
     */
    public function archivoIcono(): string
    {
        return $this->bayaOrigen()->archivoIcono();
    }
}
