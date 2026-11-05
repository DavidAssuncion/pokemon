<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Items;

/**
 * Los 7 bonguris del catálogo compartido.
 */
enum ClaveBonguri: string
{
    case BLANCO = 'blanco';
    case AMARILLO = 'amarillo';
    case NEGRO = 'negro';
    case VERDE = 'verde';
    case ROSA = 'rosa';
    case AZUL = 'azul';
    case ROJO = 'rojo';

    /**
     * Clave canónica de inventario: `bonguri:{color}`.
     */
    public function clave(): string
    {
        return CategoriaItem::BONGURI->prefijo().$this->value;
    }

    /**
     * Nombre visible en español, capitalizado.
     */
    public function nombre(): string
    {
        return match ($this) {
            self::BLANCO => 'Blanco',
            self::AMARILLO => 'Amarillo',
            self::NEGRO => 'Negro',
            self::VERDE => 'Verde',
            self::ROSA => 'Rosa',
            self::AZUL => 'Azul',
            self::ROJO => 'Rojo',
        };
    }

    /**
     * Provincia de origen del bonguri, tal y como aparece en el catálogo.
     */
    public function origen(): string
    {
        return match ($this) {
            self::BLANCO => 'Galicia',
            self::AMARILLO => 'Euskadi',
            self::NEGRO => 'Barcelona',
            self::VERDE => 'Extremadura',
            self::ROSA => 'Nova Alacant',
            self::AZUL => 'Mutxamel',
            self::ROJO => 'Andalucía',
        };
    }

    /**
     * Ruta pública del icono PNG.
     */
    public function archivoIcono(): string
    {
        return "/images/bonguri/80px-Bonguri_{$this->value}_EP.png";
    }

    /**
     * Color de respaldo cuando el hábitat no tiene bonguri asignado
     * (provincia "Vacío").
     */
    public static function porDefecto(): self
    {
        return self::BLANCO;
    }
}
