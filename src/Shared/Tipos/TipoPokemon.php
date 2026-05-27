<?php

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
        return match ($this) {
            self::NORMAL => match ($defensor) {
                self::ROCA, self::ACERO => 0.5,
                self::FANTASMA => 0.0,
                default => 1.0,
            },
            self::LUCHA => match ($defensor) {
                self::NORMAL, self::ROCA, self::ACERO, self::HIELO, self::SINIESTRO => 2.0,
                self::VOLADOR, self::VENENO, self::BICHO, self::PSIQUICO, self::HADA => 0.5,
                self::FANTASMA => 0.0,
                default => 1.0,
            },
            self::VOLADOR => match ($defensor) {
                self::LUCHA, self::BICHO, self::PLANTA => 2.0,
                self::ROCA, self::ACERO, self::ELECTRICO => 0.5,
                default => 1.0,
            },
            self::VENENO => match ($defensor) {
                self::PLANTA, self::HADA => 2.0,
                self::VENENO, self::TIERRA, self::ROCA, self::FANTASMA => 0.5,
                self::ACERO => 0.0,
                default => 1.0,
            },
            self::TIERRA => match ($defensor) {
                self::VENENO, self::ROCA, self::ACERO, self::FUEGO, self::ELECTRICO => 2.0,
                self::BICHO, self::PLANTA => 0.5,
                self::VOLADOR => 0.0,
                default => 1.0,
            },
            self::ROCA => match ($defensor) {
                self::VOLADOR, self::BICHO, self::FUEGO, self::HIELO => 2.0,
                self::LUCHA, self::TIERRA, self::ACERO => 0.5,
                default => 1.0,
            },
            self::BICHO => match ($defensor) {
                self::PLANTA, self::PSIQUICO, self::SINIESTRO => 2.0,
                self::LUCHA, self::VOLADOR, self::VENENO, self::FANTASMA, self::ACERO, self::FUEGO, self::HADA => 0.5,
                default => 1.0,
            },
            self::FANTASMA => match ($defensor) {
                self::FANTASMA, self::PSIQUICO => 2.0,
                self::SINIESTRO => 0.5,
                self::NORMAL => 0.0,
                default => 1.0,
            },
            self::ACERO => match ($defensor) {
                self::ROCA, self::HIELO, self::HADA => 2.0,
                self::ACERO, self::FUEGO, self::AGUA, self::ELECTRICO => 0.5,
                default => 1.0,
            },
            self::FUEGO => match ($defensor) {
                self::PLANTA, self::HIELO, self::BICHO, self::ACERO => 2.0,
                self::FUEGO, self::AGUA, self::ROCA, self::DRAGON => 0.5,
                default => 1.0,
            },
            self::AGUA => match ($defensor) {
                self::TIERRA, self::ROCA, self::FUEGO => 2.0,
                self::AGUA, self::PLANTA, self::DRAGON => 0.5,
                default => 1.0,
            },
            self::PLANTA => match ($defensor) {
                self::TIERRA, self::ROCA, self::AGUA => 2.0,
                self::VOLADOR, self::VENENO, self::BICHO, self::FUEGO, self::PLANTA, self::DRAGON, self::ACERO => 0.5,
                default => 1.0,
            },
            self::ELECTRICO => match ($defensor) {
                self::VOLADOR, self::AGUA => 2.0,
                self::PLANTA, self::ELECTRICO, self::DRAGON => 0.5,
                self::TIERRA => 0.0,
                default => 1.0,
            },
            self::PSIQUICO => match ($defensor) {
                self::LUCHA, self::VENENO => 2.0,
                self::ACERO, self::PSIQUICO => 0.5,
                self::SINIESTRO => 0.0,
                default => 1.0,
            },
            self::HIELO => match ($defensor) {
                self::VOLADOR, self::TIERRA, self::PLANTA, self::DRAGON => 2.0,
                self::ACERO, self::FUEGO, self::AGUA, self::HIELO => 0.5,
                default => 1.0,
            },
            self::DRAGON => match ($defensor) {
                self::DRAGON => 2.0,
                self::ACERO => 0.5,
                self::HADA => 0.0,
                default => 1.0,
            },
            self::SINIESTRO => match ($defensor) {
                self::FANTASMA, self::PSIQUICO => 2.0,
                self::LUCHA, self::SINIESTRO, self::HADA => 0.5,
                default => 1.0,
            },
            self::HADA => match ($defensor) {
                self::LUCHA, self::DRAGON, self::SINIESTRO => 2.0,
                self::VENENO, self::ACERO, self::FUEGO => 0.5,
                default => 1.0,
            },
            default => 1.0,
        };
    }

    public function effectiveness(PokemonEntity $pokemon): float
    {
        $multiplicador = 1.0;

        foreach ($pokemon->tiposCollection as $defensor) {
            $multiplicador *= $this->effectivenessAgainst($defensor);
        }

        return $multiplicador;
    }
}
