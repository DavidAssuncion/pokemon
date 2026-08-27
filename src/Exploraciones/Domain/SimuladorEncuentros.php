<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Carbon\CarbonInterface;

final class SimuladorEncuentros
{
    public const PROBABILIDAD_POKEMON = 60;
    public const PROBABILIDAD_CARAMELO_FAMILIA = 20;
    public const PROBABILIDAD_CARAMELO_EV = 20;

    private const STATS_POSIBLES = 6;

    /**
     * Pool ponderado: peso = capture_rate / hatch (a mayor capture_rate más probable,
     * a mayor hatch menos probable). Los hatch nulos o cero se tratan como 1.
     *
     * @param  array<int, array{id: int, capture_rate: int, hatch: int|null}>  $pokemonHabitat
     * @return list<array{id: int, peso: float}>
     */
    public static function poolPonderado(array $pokemonHabitat): array
    {
        $pool = [];
        foreach ($pokemonHabitat as $pokemon) {
            $peso = self::peso($pokemon['capture_rate'], $pokemon['hatch']);
            if ($peso <= 0) {
                continue;
            }
            $pool[] = [
                'id' => $pokemon['id'],
                'peso' => $peso,
            ];
        }

        return $pool;
    }

    /**
     * Selección ponderada con aleatorio inyectable (devuelve float en [0, 1)).
     *
     * @param  list<array{id: int, peso: float}>  $pool
     * @return array{id: int, peso: float}|null
     */
    public static function elegirPonderado(array $pool, callable $aleatorio): ?array
    {
        $total = array_sum(array_column($pool, 'peso'));
        if ($total <= 0) {
            return null;
        }

        $objetivo = $aleatorio() * $total;
        $acumulado = 0.0;
        foreach ($pool as $entrada) {
            $acumulado += $entrada['peso'];
            if ($acumulado > $objetivo) {
                return $entrada;
            }
        }

        return $pool[array_key_last($pool)];
    }

    /**
     * Genera N encuentros con timestamps repartidos dentro de [inicio, fin]:
     * un slot por encuentro y jitter aleatorio dentro de cada slot.
     *
     * @param  list<array{id: int, peso: float}>  $pool
     * @return list<array{tipo: string, pokemon_id?: int, stat?: int, cantidad?: int, timestamp: string}>
     */
    public static function generarEventos(
        array $pool,
        int $numEncuentros,
        CarbonInterface $inicio,
        CarbonInterface $fin,
        ?callable $aleatorio = null,
    ): array {
        if ($numEncuentros <= 0 || $pool === [] || ! $fin->greaterThan($inicio)) {
            return [];
        }

        $aleatorio ??= static fn (): float => mt_rand(0, 999) / 1000;

        $eventos = [];
        $intervaloSegundos = (int) abs($fin->diffInSeconds($inicio));
        $slotSegundos = max(1, intdiv($intervaloSegundos, $numEncuentros));

        for ($i = 0; $i < $numEncuentros; $i++) {
            $slot = $inicio->copy()->addSeconds($i * $slotSegundos);
            $timestamp = $slot->copy()->addSeconds((int) floor($aleatorio() * $slotSegundos));
            if ($timestamp->greaterThan($fin)) {
                $timestamp = $fin->copy();
            }

            $eventos[] = self::generarEvento($pool, $aleatorio(), $aleatorio, $timestamp);
        }

        return $eventos;
    }

    /**
     * @param  list<array{id: int, peso: float}>  $pool
     * @return array{tipo: string, pokemon_id?: int, stat?: int, cantidad?: int, timestamp: string}
     */
    private static function generarEvento(array $pool, float $tiradaTipo, callable $aleatorio, CarbonInterface $timestamp): array
    {
        $base = ['timestamp' => $timestamp->toIso8601String()];
        $porcentaje = $tiradaTipo * 100;

        if ($porcentaje < self::PROBABILIDAD_POKEMON) {
            $elegido = self::elegirPonderado($pool, $aleatorio);
            if ($elegido === null) {
                throw new \LogicException('El pool ponderado está vacío');
            }

            return ['tipo' => 'pokemon', 'pokemon_id' => $elegido['id']] + $base;
        }

        if ($porcentaje < self::PROBABILIDAD_POKEMON + self::PROBABILIDAD_CARAMELO_FAMILIA) {
            $indice = min((int) floor($aleatorio() * count($pool)), count($pool) - 1);
            $elegido = $pool[$indice];

            return ['tipo' => 'caramelo_familia', 'pokemon_id' => $elegido['id'], 'cantidad' => 1] + $base;
        }

        $stat = min(self::STATS_POSIBLES, 1 + (int) floor($aleatorio() * self::STATS_POSIBLES));

        return ['tipo' => 'caramelo_ev', 'stat' => $stat, 'cantidad' => 1] + $base;
    }

    private static function peso(int $captureRate, ?int $hatch): float
    {
        if ($captureRate <= 0) {
            return 0.0;
        }

        $divisor = ($hatch === null || $hatch <= 0) ? 1 : $hatch;

        return $captureRate / $divisor;
    }
}
