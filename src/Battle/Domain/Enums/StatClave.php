<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

enum StatClave: string
{
    case ATAQUE = 'attack';
    case DEFENSA = 'defense';
    case ATAQUE_ESPECIAL = 'spAtk';
    case DEFENSA_ESPECIAL = 'spDef';
    case VELOCIDAD = 'speed';
    case PRECISION = 'accuracy';
    case EVASION = 'evasion';

    public function label(): string
    {
        return match ($this) {
            self::ATAQUE => 'Ataque',
            self::DEFENSA => 'Defensa',
            self::ATAQUE_ESPECIAL => 'At. Especial',
            self::DEFENSA_ESPECIAL => 'Def. Especial',
            self::VELOCIDAD => 'Velocidad',
            self::PRECISION => 'Precisión',
            self::EVASION => 'Evasión',
        };
    }
}
