<?php

declare(strict_types=1);

namespace Src\Granjas\Domain;

use Src\Granjas\Domain\Collections\NivelesComplementoCollection;
use Src\Granjas\Domain\DataTransferObjects\CosteCaramelos;
use Src\Granjas\Domain\DataTransferObjects\EfectoRefugio;
use Src\Granjas\Domain\DataTransferObjects\NivelComplemento;
use Src\Pokemon\Domain\Stats\GeneradorIvs;
use Src\Pokemon\Domain\Stats\RangoIvs;
use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * Cálculos puros de la granja: rango de IVs, factor de tiempo, multiplicadores
 * de refugio, costes y EV de complementos. Sin Eloquent, HTTP ni facades.
 */
final class CalculadoraBonosGranja
{
    public const MAX_NIDO = 5;

    public const MAX_REFUGIO = 5;

    public const MAX_COMPLEMENTO = 5;

    private const REDUCCION_TIEMPO_POR_NIVEL = 0.10;

    private const REDUCCION_TIEMPO_MAXIMA = 0.50;

    public static function rangoIvs(int $nidoNivel): RangoIvs
    {
        return GeneradorIvs::rangoHuevo($nidoNivel);
    }

    public static function factorTiempo(int $nidoNivel): float
    {
        $nivel = max(0, min(self::MAX_NIDO, $nidoNivel));

        return 1.0 - min(
            self::REDUCCION_TIEMPO_MAXIMA,
            self::REDUCCION_TIEMPO_POR_NIVEL * $nivel,
        );
    }

    /**
     * Efecto propio del refugio según nivel y condiciones.
     */
    public static function factorRefugio(int $nivel, bool $insalubres): EfectoRefugio
    {
        $nivel = max(0, min(self::MAX_REFUGIO, $nivel));

        if ($nivel === 5) {
            return new EfectoRefugio(
                factorAparicion: $insalubres ? 0.0 : 1.0,
                factorCaptura: $insalubres ? 0.2 : 3.0,
                excluido: $insalubres,
                exclusivo: ! $insalubres,
            );
        }

        $factor = self::multiplicadorRefugio($nivel, $insalubres);

        return new EfectoRefugio(
            factorAparicion: $factor,
            factorCaptura: $factor,
            excluido: false,
            exclusivo: false,
        );
    }

    public static function evsComplemento(
        NivelesComplementoCollection $niveles,
        bool $insalubres,
    ): StatsValue {
        $evs = StatsValue::ceros();

        if ($insalubres) {
            return $evs;
        }

        /** @var NivelComplemento $nivel */
        foreach ($niveles as $nivel) {
            $clave = $nivel->stat->key();
            $evs = $evs->conStat($clave, $evs->valorDe($clave) + self::evComplemento($nivel->nivel));
        }

        return $evs;
    }

    public static function costeNido(int $nivelSiguiente): int
    {
        return match ($nivelSiguiente) {
            1 => 10,
            2 => 20,
            3 => 30,
            4 => 40,
            5 => 50,
            default => 0,
        };
    }

    public static function costeRefugio(int $nivelSiguiente): int
    {
        return match ($nivelSiguiente) {
            1 => 15,
            2 => 25,
            3 => 35,
            4 => 45,
            5 => 60,
            default => 0,
        };
    }

    public static function costeComplemento(int $nivelSiguiente): CosteCaramelos
    {
        return new CosteCaramelos(
            ev: self::evComplemento($nivelSiguiente),
            familia: match ($nivelSiguiente) {
                1 => 30,
                2 => 40,
                3 => 50,
                4 => 60,
                5 => 70,
                default => 0,
            },
        );
    }

    public static function evComplemento(int $nivel): int
    {
        return match (max(0, min(self::MAX_COMPLEMENTO, $nivel))) {
            1 => 51,
            2 => 102,
            3 => 153,
            4 => 204,
            5 => 255,
            default => 0,
        };
    }

    private static function multiplicadorRefugio(int $nivel, bool $insalubres): float
    {
        return match ($nivel) {
            1 => $insalubres ? 0.8 : 1.25,
            2 => $insalubres ? 0.6667 : 1.5,
            3 => $insalubres ? 0.5 : 2.0,
            4 => $insalubres ? 0.4 : 2.5,
            default => 1.0,
        };
    }
}
