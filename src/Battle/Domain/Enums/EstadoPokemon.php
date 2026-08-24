<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Representa los estados alterados que puede sufrir un Pokémon en batalla.
 */
enum EstadoPokemon: string
{
    case NONE = 'none';
    case BURN = 'burn';
    case POISON = 'poison';
    case BAD_POISON = 'bad_poison';
    case PARALYSIS = 'paralysis';
    case SLEEP = 'sleep';
    case FREEZE = 'freeze';
    case CONFUSION = 'confusion';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'ninguno',
            self::BURN => 'quemadura',
            self::POISON => 'envenenamiento',
            self::BAD_POISON => 'envenenamiento grave',
            self::PARALYSIS => 'parálisis',
            self::SLEEP => 'sueño',
            self::FREEZE => 'congelación',
            self::CONFUSION => 'confusión',
        };
    }

    /**
     * Indica si el estado causa daño por ronda (quemadura, veneno).
     */
    public function causaDanoPorRonda(): bool
    {
        return match ($this) {
            self::BURN, self::POISON, self::BAD_POISON => true,
            default => false,
        };
    }
}
