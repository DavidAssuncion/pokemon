<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Perfil de IV/EV de un rival según su tier de dificultad.
 *
 * Los valores canónicos viven en PoliticaIvsEvs (fuente única); este enum solo
 * expone cuántos stats reciben el máximo y qué valores lleva el resto.
 */
enum PerfilIvsEvs: string
{
    case Nulo = 'nulo';
    case TopUno = 'top_uno';
    case TopDos = 'top_dos';
    case Completo = 'completo';

    /**
     * Número de stats que reciben el valor máximo (los mejores por base).
     */
    public function cuantosMaximos(): int
    {
        return match ($this) {
            self::Nulo => 0,
            self::TopUno => 1,
            self::TopDos => 2,
            self::Completo => 6,
        };
    }

    public function ivMaximo(): int
    {
        return PoliticaIvsEvs::IV_MAXIMO;
    }

    public function evMaximo(): int
    {
        return PoliticaIvsEvs::EV_MAXIMO;
    }

    /**
     * IV de los stats que no entran en el top-N.
     */
    public function ivResto(): int
    {
        return match ($this) {
            self::Nulo, self::TopUno => 0,
            self::TopDos => PoliticaIvsEvs::IV_MITAD,
            self::Completo => PoliticaIvsEvs::IV_MAXIMO,
        };
    }

    /**
     * EV de los stats que no entran en el top-N.
     */
    public function evResto(): int
    {
        return match ($this) {
            self::Nulo, self::TopUno => 0,
            self::TopDos => PoliticaIvsEvs::EV_MITAD,
            self::Completo => PoliticaIvsEvs::EV_MAXIMO,
        };
    }
}
