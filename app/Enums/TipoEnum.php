<?php

namespace App\Enums;

enum TipoEnum: int
{
    case NORMAL = 1;
    case FIGHTING = 2;
    case FLYING = 3;
    case POISON = 4;
    case GROUND = 5;
    case ROCK = 6;
    case BUG = 7;
    case GHOST = 8;
    case STEEL = 9;
    case FIRE = 10;
    case WATER = 11;
    case GRASS = 12;
    case ELECTRIC = 13;
    case PSYCHIC = 14;
    case ICE = 15;
    case DRAGON = 16;
    case DARK = 17;
    case FAIRY = 18;

    /**
     * Obtener el nombre en español del tipo
     */
    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::FIGHTING => 'Lucha',
            self::FLYING => 'Volador',
            self::POISON => 'Veneno',
            self::GROUND => 'Tierra',
            self::ROCK => 'Roca',
            self::BUG => 'Bicho',
            self::GHOST => 'Fantasma',
            self::STEEL => 'Acero',
            self::FIRE => 'Fuego',
            self::WATER => 'Agua',
            self::GRASS => 'Planta',
            self::ELECTRIC => 'Eléctrico',
            self::PSYCHIC => 'Psíquico',
            self::ICE => 'Hielo',
            self::DRAGON => 'Dragón',
            self::DARK => 'Siniestro',
            self::FAIRY => 'Hada',
        };
    }

    /**
     * Obtener todos los tipos como opciones para select
     * Retorna array [id => nombre_español]
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }

    /**
     * Obtener tipo por ID
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
