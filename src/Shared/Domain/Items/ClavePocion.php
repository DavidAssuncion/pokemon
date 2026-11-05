<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Items;

/**
 * Pociones y restauradores fabricables en la forja.
 */
enum ClavePocion: string
{
    case POCION = 'pocion';
    case SUPER = 'super';
    case HIPER = 'hiper';
    case MAXIMA = 'maxima';
    case CURATOTAL = 'curatotal';
    case RESTAURAR_TODO = 'restaurar_todo';
    case REVIVIR = 'revivir';
    case MAX_REVIVIR = 'max_revivir';

    /**
     * Clave canónica de inventario: `pocion:{slug}`.
     */
    public function clave(): string
    {
        return CategoriaItem::POCION->prefijo().$this->value;
    }

    /**
     * Nombre visible en español.
     */
    public function nombre(): string
    {
        return match ($this) {
            self::POCION => 'Poción',
            self::SUPER => 'Superpoción',
            self::HIPER => 'Hiperpoción',
            self::MAXIMA => 'Poción Máxima',
            self::CURATOTAL => 'Cura Total',
            self::RESTAURAR_TODO => 'Restaura Todo',
            self::REVIVIR => 'Revivir',
            self::MAX_REVIVIR => 'Revivir Máximo',
        };
    }

    /**
     * Ruta pública del icono de la poción.
     */
    public function archivoIcono(): string
    {
        return "/images/items/pocion/{$this->value}.webp";
    }
}
