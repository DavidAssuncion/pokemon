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

    /** Slug español del tipo (path de assets: /images/type/{slug}.webp, /images/medallas/{slug}.webp). */
    public function slug(): string
    {
        return strtolower($this->name);
    }

    /** Color hexadecimal representativo del tipo. */
    public function color(): string
    {
        return match ($this) {
            self::NORMAL => '#A8A878',
            self::LUCHA => '#C03028',
            self::VOLADOR => '#A890F0',
            self::VENENO => '#A040A0',
            self::TIERRA => '#E0C068',
            self::ROCA => '#B8A038',
            self::BICHO => '#A8B820',
            self::FANTASMA => '#705898',
            self::ACERO => '#B8B8D0',
            self::FUEGO => '#F08030',
            self::AGUA => '#6890F0',
            self::PLANTA => '#78C850',
            self::ELECTRICO => '#F8D030',
            self::PSIQUICO => '#F85888',
            self::HIELO => '#98D8D8',
            self::DRAGON => '#7038F8',
            self::SINIESTRO => '#705848',
            self::HADA => '#EE99AC',
        };
    }

    /** Nombre específico de la medalla del gimnasio de este tipo. */
    public function medalla(): string
    {
        return match ($this) {
            self::NORMAL => 'Planicie',
            self::LUCHA => 'Puño',
            self::VOLADOR => 'Céfiro',
            self::VENENO => 'Ponzoña',
            self::TIERRA => 'Tormenta',
            self::ROCA => 'Roca',
            self::BICHO => 'Colmena',
            self::FANTASMA => 'Reliquia',
            self::ACERO => 'Mineral',
            self::FUEGO => 'Volcan',
            self::AGUA => 'Cascada',
            self::PLANTA => 'Bosque',
            self::ELECTRICO => 'Faro',
            self::PSIQUICO => 'Mente',
            self::HIELO => 'Carámbano',
            self::DRAGON => 'Dragón',
            self::SINIESTRO => 'Retorcida',
            self::HADA => 'Bondad',
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
