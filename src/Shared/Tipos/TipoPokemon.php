<?php

declare(strict_types=1);

namespace Src\Shared\Tipos;

use Src\Pokemon\Domain\PokemonEntity;

enum TipoPokemon: int
{
    case NORMAL = 1;
    case LUCHA = 2;
    case VOLADOR = 3;
    case VENENO = 4;
    case TIERRA = 5;
    case ROCA = 6;
    case BICHO = 7;
    case FANTASMA = 8;
    case ACERO = 9;
    case FUEGO = 10;
    case AGUA = 11;
    case PLANTA = 12;
    case ELECTRICO = 13;
    case PSIQUICO = 14;
    case HIELO = 15;
    case DRAGON = 16;
    case SINIESTRO = 17;
    case HADA = 18;

    public function effectivenessAgainst(self $defensor): float
    {
        return TypeChart::getEffectiveness($this, $defensor);
    }

    /** Nombre en español del tipo (para mensajes de dominio, p. ej. preview). */
    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::LUCHA => 'Lucha',
            self::VOLADOR => 'Volador',
            self::VENENO => 'Veneno',
            self::TIERRA => 'Tierra',
            self::ROCA => 'Roca',
            self::BICHO => 'Bicho',
            self::FANTASMA => 'Fantasma',
            self::ACERO => 'Acero',
            self::FUEGO => 'Fuego',
            self::AGUA => 'Agua',
            self::PLANTA => 'Planta',
            self::ELECTRICO => 'Eléctrico',
            self::PSIQUICO => 'Psíquico',
            self::HIELO => 'Hielo',
            self::DRAGON => 'Dragón',
            self::SINIESTRO => 'Siniestro',
            self::HADA => 'Hada',
        };
    }

    public function effectiveness(PokemonEntity $pokemon): float
    {
        $multiplicador = 1.0;

        foreach ($pokemon->tiposCollection() as $defensor) {
            $multiplicador *= $this->effectivenessAgainst($defensor);
        }

        return $multiplicador;
    }
}
