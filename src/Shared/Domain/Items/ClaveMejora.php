<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Items;

/**
 * Objetos de forja que mejoran temporalmente una estadística en combate.
 */
enum ClaveMejora: string
{
    case ATAQUE = 'ataque';
    case DEFENSA = 'defensa';
    case ATAQUE_ESPECIAL = 'ataque_especial';
    case DEFENSA_ESPECIAL = 'defensa_especial';
    case VELOCIDAD = 'velocidad';
    case PRECISION = 'precision';
    case CRITICO = 'critico';

    /**
     * Clave canónica de inventario: `mejora:{slug}`.
     */
    public function clave(): string
    {
        return CategoriaItem::MEJORA->prefijo().$this->value;
    }

    /**
     * Nombre visible en español.
     */
    public function nombre(): string
    {
        return match ($this) {
            self::ATAQUE => 'Mejora de Ataque',
            self::DEFENSA => 'Mejora de Defensa',
            self::ATAQUE_ESPECIAL => 'Mejora de Ataque Especial',
            self::DEFENSA_ESPECIAL => 'Mejora de Defensa Especial',
            self::VELOCIDAD => 'Mejora de Velocidad',
            self::PRECISION => 'Mejora de Precisión',
            self::CRITICO => 'Mejora de Crítico',
        };
    }

    /**
     * Baya de origen usada como ingrediente de forja.
     */
    public function bayaOrigen(): ClaveBaya
    {
        return match ($this) {
            self::ATAQUE => ClaveBaya::LIECHI,
            self::DEFENSA => ClaveBaya::GANLON,
            self::VELOCIDAD => ClaveBaya::SALAC,
            self::ATAQUE_ESPECIAL => ClaveBaya::PETAYA,
            self::DEFENSA_ESPECIAL => ClaveBaya::APICOT,
            self::CRITICO => ClaveBaya::LANSAT,
            self::PRECISION => ClaveBaya::MICLE,
        };
    }

    /**
     * Ruta pública del icono de la mejora.
     */
    public function archivoIcono(): string
    {
        return "/images/items/mejora/{$this->value}.webp";
    }
}
