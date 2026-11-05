<?php

declare(strict_types=1);

namespace Src\Granjas\Domain;

use Src\Granjas\Domain\Entities\Plantacion;
use Src\Granjas\Domain\Enums\EstadoPlantacion;

/**
 * Reglas puras de crecimiento y capacidad de las plantaciones.
 *
 * No hay cron: el stock disponible se deriva de los segundos transcurridos
 * desde `planted_at` menos las cosechas acumuladas (`harvest_count`).
 */
final class ReglasPlantacion
{
    public const int HUECOS_POR_NIVEL = 2;
    public const int HORAS_CRECIMIENTO = 24;
    public const int STOCK_MAXIMO = 3;
    public const int COSTE_PLANTAR = 1;
    public const float BONUS_PESO_POR_HUECO = 0.20;
    public const float TOPE_FACTOR = 2.0;

    private const int SEGUNDOS_POR_HORA = 3600;
    private const int SEGUNDOS_POR_PORCENTAJE = 864;

    public static function huecosDe(int $nivelNido): int
    {
        return max(0, $nivelNido * self::HUECOS_POR_NIVEL);
    }

    public static function stockDe(Plantacion $plantacion, \DateTimeImmutable $ahora): int
    {
        $transcurridas = intdiv(
            self::segundosDesde($plantacion, $ahora),
            self::SEGUNDOS_POR_HORA * self::HORAS_CRECIMIENTO,
        );

        return max(0, min(self::STOCK_MAXIMO, $transcurridas - $plantacion->harvestCount));
    }

    public static function progresoPct(Plantacion $plantacion, \DateTimeImmutable $ahora): int
    {
        if (self::stockDe($plantacion, $ahora) > 0) {
            return 100;
        }

        $resto = self::segundosDesde($plantacion, $ahora)
            % (self::SEGUNDOS_POR_HORA * self::HORAS_CRECIMIENTO);

        return (int) floor($resto / self::SEGUNDOS_POR_PORCENTAJE);
    }

    public static function estadoDe(Plantacion $plantacion, \DateTimeImmutable $ahora): EstadoPlantacion
    {
        return self::stockDe($plantacion, $ahora) > 0
            ? EstadoPlantacion::LISTA
            : EstadoPlantacion::CRECIENDO;
    }

    public static function puedeCosechar(Plantacion $plantacion, \DateTimeImmutable $ahora): bool
    {
        return self::stockDe($plantacion, $ahora) > 0;
    }

    /**
     * Factor de peso de bayas salvajes por número de plantas de la misma
     * granja/hábitat: min(2.0, 1 + 0.20 * unidades).
     */
    public static function factorPlantacion(int $unidades): float
    {
        return min(
            self::TOPE_FACTOR,
            1.0 + self::BONUS_PESO_POR_HUECO * $unidades,
        );
    }

    private static function segundosDesde(Plantacion $plantacion, \DateTimeImmutable $ahora): int
    {
        return max(0, $ahora->getTimestamp() - $plantacion->plantedAt->getTimestamp());
    }
}
