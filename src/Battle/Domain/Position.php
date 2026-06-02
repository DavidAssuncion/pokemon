<?php

namespace Src\Battle\Domain;

enum Position: string
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
