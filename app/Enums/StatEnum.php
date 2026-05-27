<?php

namespace App\Enums;

enum StatEnum: int
{
    case HP = 1;
    case ATTACK = 2;
    case DEFENSE = 3;
    case SPECIAL_ATTACK = 4;
    case SPECIAL_DEFENSE = 5;
    case SPEED = 6;

    /**
     * Obtener el nombre en español del stat
     */
    public function label(): string
    {
        return match ($this) {
            self::HP => 'PS (HP)',
            self::ATTACK => 'Ataque',
            self::DEFENSE => 'Defensa',
            self::SPEED => 'Velocidad',
            self::SPECIAL_ATTACK => 'Ataque Especial',
            self::SPECIAL_DEFENSE => 'Defensa Especial',
        };
    }

    /**
     * Obtener todos los stats como opciones para select
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }

    /**
     * Obtener stat por ID
     */
    public static function fromId(int $id): ?self
    {
        return self::tryFrom($id);
    }

    /**
     * Obtener nombre en español por ID
     */
    public static function getNombreEspanol(int $id): ?string
    {
        return self::tryFrom($id)?->label();
    }
}
