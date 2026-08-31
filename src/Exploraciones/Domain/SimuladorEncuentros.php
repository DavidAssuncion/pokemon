<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Carbon\CarbonInterface;
use LogicException;
use Src\Shared\Tipos\TipoPokemon;

/**
 * RF-04: simulador de encuentros de una expedición (dominio puro).
 *
 * Probabilidades por evento: 45 % encuentro · 20 % hallazgo · 15 % encuentro
 * especial (emboscada) · 10 % contratiempo · 10 % evento neutral.
 * Subtipos de encuentro: 80 % normal · 10 % grupo · 7 % emboscada · 3 %
 * excepcional. Hallazgo → caramelos (familia/EV/tipo). Sin huida plana (la
 * huida la condiciona el EvaluadorExploracion a la desventaja, D10).
 *
 * Mantiene el seam `callable $aleatorio` y poolPonderado/elegirPonderado.
 */
final class SimuladorEncuentros
{
    public const PROBABILIDAD_ENCUENTRO = 45;
    public const PROBABILIDAD_HALLAZGO = 20;
    public const PROBABILIDAD_ENCUENTRO_ESPECIAL = 15;
    public const PROBABILIDAD_CONTRATIEMPO = 10;
    public const PROBABILIDAD_NEUTRAL = 10;

    public const SUBTIPO_NORMAL = 80;
    public const SUBTIPO_GRUPO = 10;
    public const SUBTIPO_EMBOSCADA = 7;
    public const SUBTIPO_EXCEPCIONAL = 3;

    private const STATS_POSIBLES = 6;
    private const SUBTIPOS_CONTRATIEMPO = ['desorientacion', 'terreno', 'clima', 'bloqueo'];
    private const HALLAZGO_FAMILIA = 33.33;
    private const HALLAZGO_EV = 66.66;

    /**
     * Pool ponderado: peso = capture_rate / hatch (a mayor capture_rate más probable,
     * a mayor hatch menos probable). Los hatch nulos o cero se tratan como 1.
     * Solo consume id/capture_rate/hatch del pool (tipos y stats se ignoran aquí).
     *
     * @param  array<int, array{id: int, capture_rate: int, hatch: int|null, tipos?: list<TipoPokemon>, stats?: list<array{stat: int, effort: int}>}>  $pokemonHabitat
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
     * Genera N eventos con timestamps repartidos dentro de [inicio, fin]:
     * un slot por evento y jitter aleatorio dentro de cada slot.
     * El pool completo (id/capture_rate/hatch/tipos/stats) permite a los
     * hallazgos restringir caramelos EV y de tipo al pool del hábitat.
     *
     * @param  list<array{id: int, capture_rate: int, hatch: int|null, tipos: list<TipoPokemon>, stats: list<array{stat: int, effort: int}>}>  $pool
     * @return list<array<string, mixed>>
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

        $poolPonderado = self::poolPonderado($pool);
        if ($poolPonderado === []) {
            return [];
        }

        $eventos = [];
        $intervaloSegundos = (int) abs($fin->diffInSeconds($inicio));
        $slotSegundos = max(1, intdiv($intervaloSegundos, $numEncuentros));

        for ($i = 0; $i < $numEncuentros; $i++) {
            $slot = $inicio->copy()->addSeconds($i * $slotSegundos);
            $timestamp = $slot->copy()->addSeconds((int) floor($aleatorio() * $slotSegundos));
            if ($timestamp->greaterThan($fin)) {
                $timestamp = $fin->copy();
            }

            $eventos[] = self::generarEvento($pool, $poolPonderado, $aleatorio(), $aleatorio, $timestamp);
        }

        return $eventos;
    }

    /**
     * @param  list<array{id: int, capture_rate: int, hatch: int|null, tipos: list<TipoPokemon>, stats: list<array{stat: int, effort: int}>}>  $pool
     * @param  list<array{id: int, peso: float}>  $poolPonderado
     * @return array<string, mixed>
     */
    private static function generarEvento(
        array $pool,
        array $poolPonderado,
        float $tiradaTipo,
        callable $aleatorio,
        CarbonInterface $timestamp,
    ): array {
        $base = ['timestamp' => $timestamp->toIso8601String()];
        $porcentaje = $tiradaTipo * 100;

        if ($porcentaje < self::PROBABILIDAD_ENCUENTRO) {
            return self::eventoEncuentro($poolPonderado, $aleatorio) + $base;
        }

        if ($porcentaje < self::PROBABILIDAD_ENCUENTRO + self::PROBABILIDAD_HALLAZGO) {
            return self::eventoHallazgo($pool, $poolPonderado, $aleatorio) + $base;
        }

        if ($porcentaje < self::PROBABILIDAD_ENCUENTRO + self::PROBABILIDAD_HALLAZGO + self::PROBABILIDAD_ENCUENTRO_ESPECIAL) {
            return self::eventoEmboscada($poolPonderado, $aleatorio) + $base;
        }

        if ($porcentaje < self::PROBABILIDAD_ENCUENTRO + self::PROBABILIDAD_HALLAZGO + self::PROBABILIDAD_ENCUENTRO_ESPECIAL + self::PROBABILIDAD_CONTRATIEMPO) {
            return self::eventoContratiempo($aleatorio) + $base;
        }

        return ['tipo' => 'neutral', 'detalle' => 'evento neutral'] + $base;
    }

    /**
     * Encuentro con subtipos: 80 % normal · 10 % grupo · 7 % emboscada · 3 % excepcional.
     * El subtipo emboscada se materializa como evento `emboscada` con pokemon_ids.
     *
     * @param  list<array{id: int, peso: float}>  $pool
     * @return array<string, mixed>
     */
    private static function eventoEncuentro(array $pool, callable $aleatorio): array
    {
        $rollSubtipo = $aleatorio() * 100;

        if ($rollSubtipo < self::SUBTIPO_EMBOSCADA) {
            return self::eventoEmboscada($pool, $aleatorio);
        }

        if ($rollSubtipo < self::SUBTIPO_EMBOSCADA + self::SUBTIPO_EXCEPCIONAL) {
            $subtype = 'excepcional';
        } elseif ($rollSubtipo < self::SUBTIPO_EMBOSCADA + self::SUBTIPO_EXCEPCIONAL + self::SUBTIPO_GRUPO) {
            $subtype = 'grupo';
        } else {
            $subtype = 'normal';
        }

        $elegido = self::elegirPonderado($pool, $aleatorio);
        if ($elegido === null) {
            throw new LogicException('El pool ponderado está vacío');
        }

        return ['tipo' => 'encuentro', 'subtype' => $subtype, 'pokemon_id' => $elegido['id']];
    }

    /**
     * Hallazgo → caramelos (D8): familia (pokemon_id), EV (stat) o tipo (tipo_id).
     * El EV y el tipo se restringen al pool del hábitat: se elige un pokémon del
     * pool (ponderado) y de él un stat con effort>0 o uno de sus tipos.
     * Fallback a valores aleatorios globales si el pool no aporta stats/tipos.
     *
     * @param  list<array{id: int, capture_rate: int, hatch: int|null, tipos: list<TipoPokemon>, stats: list<array{stat: int, effort: int}>}>  $pool
     * @param  list<array{id: int, peso: float}>  $poolPonderado
     * @return array<string, mixed>
     */
    private static function eventoHallazgo(array $pool, array $poolPonderado, callable $aleatorio): array
    {
        $roll = $aleatorio() * 100;

        if ($roll < self::HALLAZGO_FAMILIA) {
            $elegido = self::elegirPonderado($poolPonderado, $aleatorio);
            if ($elegido === null) {
                throw new LogicException('El pool ponderado está vacío');
            }

            return ['tipo' => 'hallazgo', 'subtype' => 'caramelo_familia', 'pokemon_id' => $elegido['id'], 'cantidad' => 1];
        }

        if ($roll < self::HALLAZGO_EV) {
            $stat = self::elegirStatDelPool($pool, $aleatorio);

            return ['tipo' => 'hallazgo', 'subtype' => 'caramelo_ev', 'stat' => $stat, 'cantidad' => 1];
        }

        $tipo = self::elegirTipoDelPool($pool, $aleatorio);

        return ['tipo' => 'hallazgo', 'subtype' => 'caramelo_tipo', 'tipo_id' => $tipo->value, 'cantidad' => 1];
    }

    /**
     * Elige un stat con effort>0 de un pokémon del pool (ponderado). Un solo
     * caramelo EV por hallazgo: se elige el pokémon y después UNO de sus stats
     * con effort>0; en sucesivos hallazgos se repartirán los demás. Fallback a
     * stat aleatorio 1-6 si ningún pokémon del pool tiene stats con effort.
     *
     * @param  list<array{id: int, capture_rate: int, hatch: int|null, tipos: list<TipoPokemon>, stats: list<array{stat: int, effort: int}>}>  $pool
     */
    private static function elegirStatDelPool(array $pool, callable $aleatorio): int
    {
        $candidatos = array_values(array_filter(
            $pool,
            static fn (array $pokemon): bool => $pokemon['stats'] !== [],
        ));

        if ($candidatos === []) {
            return self::statAleatorio($aleatorio);
        }

        $elegido = self::elegirPonderado(self::poolPonderado($candidatos), $aleatorio);
        if ($elegido === null) {
            return self::statAleatorio($aleatorio);
        }

        $pokemon = null;
        foreach ($candidatos as $candidato) {
            if ($candidato['id'] === $elegido['id']) {
                $pokemon = $candidato;
                break;
            }
        }

        if ($pokemon === null) {
            return self::statAleatorio($aleatorio);
        }

        $stats = $pokemon['stats'];
        $elegidoStat = $stats[min(count($stats) - 1, (int) floor($aleatorio() * count($stats)))];

        return $elegidoStat['stat'];
    }

    /**
     * Elige un tipo de un pokémon del pool (ponderado). Fallback a tipo
     * aleatorio global si ningún pokémon del pool tiene tipos.
     *
     * @param  list<array{id: int, capture_rate: int, hatch: int|null, tipos: list<TipoPokemon>, stats: list<array{stat: int, effort: int}>}>  $pool
     */
    private static function elegirTipoDelPool(array $pool, callable $aleatorio): TipoPokemon
    {
        $candidatos = array_values(array_filter(
            $pool,
            static fn (array $pokemon): bool => $pokemon['tipos'] !== [],
        ));

        if ($candidatos === []) {
            return self::tipoAleatorio($aleatorio);
        }

        $elegido = self::elegirPonderado(self::poolPonderado($candidatos), $aleatorio);
        if ($elegido === null) {
            return self::tipoAleatorio($aleatorio);
        }

        $pokemon = null;
        foreach ($candidatos as $candidato) {
            if ($candidato['id'] === $elegido['id']) {
                $pokemon = $candidato;
                break;
            }
        }

        if ($pokemon === null) {
            return self::tipoAleatorio($aleatorio);
        }

        $tipos = $pokemon['tipos'];

        return $tipos[min(count($tipos) - 1, (int) floor($aleatorio() * count($tipos)))];
    }

    private static function statAleatorio(callable $aleatorio): int
    {
        return min(self::STATS_POSIBLES, 1 + (int) floor($aleatorio() * self::STATS_POSIBLES));
    }

    private static function tipoAleatorio(callable $aleatorio): TipoPokemon
    {
        $tipos = TipoPokemon::cases();

        return $tipos[min(count($tipos) - 1, (int) floor($aleatorio() * count($tipos)))];
    }

    /**
     * Emboscada: 2–3 pokémon del pool (grupo enemigo).
     *
     * @param  list<array{id: int, peso: float}>  $pool
     * @return array<string, mixed>
     */
    private static function eventoEmboscada(array $pool, callable $aleatorio): array
    {
        $num = $aleatorio() < 0.5 ? 2 : 3;
        $ids = [];
        for ($i = 0; $i < $num; $i++) {
            $elegido = self::elegirPonderado($pool, $aleatorio);
            if ($elegido === null) {
                break;
            }
            $ids[] = $elegido['id'];
        }

        return ['tipo' => 'emboscada', 'subtype' => 'emboscada', 'pokemon_ids' => $ids];
    }

    /**
     * @return array<string, mixed>
     */
    private static function eventoContratiempo(callable $aleatorio): array
    {
        $subtype = self::SUBTIPOS_CONTRATIEMPO[
            min(count(self::SUBTIPOS_CONTRATIEMPO) - 1, (int) floor($aleatorio() * count(self::SUBTIPOS_CONTRATIEMPO)))
        ];

        return ['tipo' => 'contratiempo', 'subtype' => $subtype];
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
