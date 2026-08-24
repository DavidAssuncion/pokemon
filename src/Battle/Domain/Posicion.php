<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

enum Posicion: string
{
    case VANGUARDIA = 'vanguardia';
    case RETAGUARDIA = 'retaguardia';

    public function opuesta(): self
    {
        return match ($this) {
            self::VANGUARDIA => self::RETAGUARDIA,
            self::RETAGUARDIA => self::VANGUARDIA,
        };
    }
}
