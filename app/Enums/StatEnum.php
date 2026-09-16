<?php

declare(strict_types=1);

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
     * Obtener el nombre en español del stat.
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
     * Slug de caramelo EV (contrato del frontend: candy_ev/{slug}.webp).
     */
    public function slug(): string
    {
        return match ($this) {
            self::HP => 'hp',
            self::ATTACK => 'atk',
            self::DEFENSE => 'def',
            self::SPECIAL_ATTACK => 'atksp',
            self::SPECIAL_DEFENSE => 'defsp',
            self::SPEED => 'spd',
        };
    }

    /**
     * Obtener todos los stats como opciones para select.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }

    /**
     * Obtener stat por ID.
     */
    public static function fromId(int $id): ?self
    {
        return self::tryFrom($id);
    }

    /**
     * Obtener nombre en español por ID.
     */
    public static function getNombreEspanol(int $id): ?string
    {
        return self::tryFrom($id)?->label();
    }

    /**
     * Resuelve el stat desde la clave corta canónica del frontend
     * ({hp, atk, def, spAtk, spDef, speed}). null si la clave no es válida.
     */
    public static function fromKey(string $key): ?self
    {
        return match ($key) {
            'hp' => self::HP,
            'atk' => self::ATTACK,
            'def' => self::DEFENSE,
            'spAtk' => self::SPECIAL_ATTACK,
            'spDef' => self::SPECIAL_DEFENSE,
            'speed' => self::SPEED,
            default => null,
        };
    }
}
