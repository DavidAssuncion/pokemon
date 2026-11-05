<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Items;

use Src\Shared\Tipos\TipoPokemon;

/**
 * Objetos de forja que reducen el daño recibido de un tipo de ataque.
 */
enum ClaveResistencia: string
{
    case NORMAL = 'normal';
    case LUCHA = 'lucha';
    case VOLADOR = 'volador';
    case VENENO = 'veneno';
    case TIERRA = 'tierra';
    case ROCA = 'roca';
    case BICHO = 'bicho';
    case FANTASMA = 'fantasma';
    case ACERO = 'acero';
    case FUEGO = 'fuego';
    case AGUA = 'agua';
    case PLANTA = 'planta';
    case ELECTRICO = 'electrico';
    case PSIQUICO = 'psiquico';
    case HIELO = 'hielo';
    case DRAGON = 'dragon';
    case SINIESTRO = 'siniestro';
    case HADA = 'hada';

    /**
     * Clave canónica de inventario: `resistencia:{slug}`.
     */
    public function clave(): string
    {
        return CategoriaItem::RESISTENCIA->prefijo().$this->value;
    }

    /**
     * Nombre visible en español.
     */
    public function nombre(): string
    {
        return 'Resistencia a '.$this->tipo()->label();
    }

    /**
     * Ruta pública del icono de tipo.
     */
    public function archivoIcono(): string
    {
        return '/images/type/'.$this->value.'.webp';
    }

    /**
     * Tipo de Pokémon al que protege esta resistencia.
     */
    public function tipo(): TipoPokemon
    {
        return match ($this) {
            self::NORMAL => TipoPokemon::NORMAL,
            self::LUCHA => TipoPokemon::LUCHA,
            self::VOLADOR => TipoPokemon::VOLADOR,
            self::VENENO => TipoPokemon::VENENO,
            self::TIERRA => TipoPokemon::TIERRA,
            self::ROCA => TipoPokemon::ROCA,
            self::BICHO => TipoPokemon::BICHO,
            self::FANTASMA => TipoPokemon::FANTASMA,
            self::ACERO => TipoPokemon::ACERO,
            self::FUEGO => TipoPokemon::FUEGO,
            self::AGUA => TipoPokemon::AGUA,
            self::PLANTA => TipoPokemon::PLANTA,
            self::ELECTRICO => TipoPokemon::ELECTRICO,
            self::PSIQUICO => TipoPokemon::PSIQUICO,
            self::HIELO => TipoPokemon::HIELO,
            self::DRAGON => TipoPokemon::DRAGON,
            self::SINIESTRO => TipoPokemon::SINIESTRO,
            self::HADA => TipoPokemon::HADA,
        };
    }

    /**
     * Resistencia correspondiente a un tipo de Pokémon.
     */
    public static function desdeTipo(TipoPokemon $tipo): self
    {
        return match ($tipo) {
            TipoPokemon::NORMAL => self::NORMAL,
            TipoPokemon::LUCHA => self::LUCHA,
            TipoPokemon::VOLADOR => self::VOLADOR,
            TipoPokemon::VENENO => self::VENENO,
            TipoPokemon::TIERRA => self::TIERRA,
            TipoPokemon::ROCA => self::ROCA,
            TipoPokemon::BICHO => self::BICHO,
            TipoPokemon::FANTASMA => self::FANTASMA,
            TipoPokemon::ACERO => self::ACERO,
            TipoPokemon::FUEGO => self::FUEGO,
            TipoPokemon::AGUA => self::AGUA,
            TipoPokemon::PLANTA => self::PLANTA,
            TipoPokemon::ELECTRICO => self::ELECTRICO,
            TipoPokemon::PSIQUICO => self::PSIQUICO,
            TipoPokemon::HIELO => self::HIELO,
            TipoPokemon::DRAGON => self::DRAGON,
            TipoPokemon::SINIESTRO => self::SINIESTRO,
            TipoPokemon::HADA => self::HADA,
        };
    }
}
