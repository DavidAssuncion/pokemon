<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Piezas\Enums;

use Src\Shared\Domain\Items\CategoriaItem;
use Src\Shared\Domain\Items\ClaveBaya;
use Src\Shared\Domain\Items\ClavePieza;

/**
 * Tipos de pieza forjable. Es la única fuente del prefijo de inventario, de la
 * categoría del catálogo compartido y de la baya rara que consume cada tipo.
 */
enum TipoPieza: string
{
    case ARMA = 'arma';
    case ARMADURA = 'armadura';
    case AMULETO = 'amuleto';

    public function nombre(): string
    {
        return match ($this) {
            self::ARMA => 'Arma',
            self::ARMADURA => 'Armadura',
            self::AMULETO => 'Amuleto',
        };
    }

    public function categoria(): CategoriaItem
    {
        return match ($this) {
            self::ARMA => CategoriaItem::ARMA,
            self::ARMADURA => CategoriaItem::ARMADURA,
            self::AMULETO => CategoriaItem::AMULETO,
        };
    }

    public function bayaRara(): ClaveBaya
    {
        return match ($this) {
            self::ARMA => ClaveBaya::SALAC,
            self::ARMADURA => ClaveBaya::GANLON,
            self::AMULETO => ClaveBaya::PETAYA,
        };
    }

    public function clave(int $speciesId): string
    {
        return $this->value.':'.$speciesId;
    }

    public function aClavePieza(int $speciesId): ClavePieza
    {
        return ClavePieza::de($this->value, $speciesId);
    }
}
