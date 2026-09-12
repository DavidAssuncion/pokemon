<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Carbon\CarbonInterface;
use LogicException;
use Src\Exploraciones\Domain\ValueObjects\ColeccionEventosExploracion;
use Src\Exploraciones\Domain\ValueObjects\ColeccionPoolPonderado;
use Src\Exploraciones\Domain\ValueObjects\EventoExploracion;
use Src\Exploraciones\Domain\ValueObjects\PokemonDelPool;
use Src\Exploraciones\Domain\ValueObjects\PokemonPoolPonderado;
use Src\Exploraciones\Domain\ValueObjects\PoolHabitat;
use Src\Shared\Collections\IntCollection;
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
 * Las APIs tipadas (desde PoolHabitat/ColeccionPoolPonderado) son la vía
 * recomendada; los métodos con arrays se conservan como FRONTERA por
 * compatibilidad de contrato (tests unitarios que pasan arrays).
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
     * Pool ponderado: peso = capture_rate / hatch. Frontera — el dominio
     * recomienda generarEventosDesdePool()/poolPonderadoDe() con PoolHabitat.
     *
     * @param  array<int, array{id: int, capture_rate: int, hatch: int|null, tipos?: list<TipoPokemon>, stats?: list<array{stat: int, effort: int}>}>  $pokemonHabitat
     * @return list<array{id: int, peso: float}>
     *
     * @deprecated Frontera (BC con tests unitarios que pasan arrays).
     */
    public static function poolPonderado(array $pokemonHabitat): array
    {
        return self::poolPonderadoDe(PoolHabitat::desdeArray($pokemonHabitat))->aArrays();
    }

    /**
     * Pool ponderado tipado: peso = capture_rate / hatch (a mayor capture_rate
     * más probable, a mayor hatch menos probable).
     */
    public static function poolPonderadoDe(PoolHabitat $pokemonHabitat): ColeccionPoolPonderado
    {
        return $pokemonHabitat->ponderado();
    }

    /**
     * Selección ponderada con aleatorio inyectable (float en [0, 1)).
     * Frontera — el dominio recomienda elegirPonderadoDe() con la colección.
     *
     * @param  list<array{id: int, peso: float}>  $pool
     * @return array{id: int, peso: float}|null
     *
     * @deprecated Frontera (BC con tests unitarios que pasan arrays).
     */
    public static function elegirPonderado(array $pool, callable $aleatorio): ?array
    {
        return self::elegirPonderadoDe(ColeccionPoolPonderado::desdeArray($pool), $aleatorio)?->aArray();
    }

    /**
     * Selección ponderada tipada con aleatorio inyectable (float en [0, 1)).
     */
    public static function elegirPonderadoDe(ColeccionPoolPonderado $pool, callable $aleatorio): ?PokemonPoolPonderado
    {
        return $pool->elegirConAleatorio($aleatorio);
    }

    /**
     * Genera N eventos con timestamps repartidos dentro de [inicio, fin].
     * Frontera — el dominio recomienda generarEventosDesdePool() con PoolHabitat.
     *
     * @param  list<array{id: int, capture_rate: int, hatch: int|null, tipos: list<TipoPokemon>, stats: list<array{stat: int, effort: int}>}>  $pool
     * @return list<array<string, mixed>>
     *
     * @deprecated Frontera (BC con tests unitarios que pasan arrays).
     */
    public static function generarEventos(
        array $pool,
        int $numEncuentros,
        CarbonInterface $inicio,
        CarbonInterface $fin,
        ?callable $aleatorio = null,
        bool $permitirEmboscadas = true,
        bool $permitirExcepcionales = true,
    ): array {
        return self::generarEventosDesdePool(
            PoolHabitat::desdeArray($pool),
            $numEncuentros,
            $inicio,
            $fin,
            $aleatorio,
            $permitirEmboscadas,
            $permitirExcepcionales,
        )->aArrays();
    }

    /**
     * Genera N eventos tipados con timestamps repartidos dentro de [inicio,
     * fin]: un slot por evento y jitter aleatorio dentro de cada slot.
     * El pool completo permite a los hallazgos restringir caramelos EV y de
     * tipo al pool del hábitat.
     */
    public static function generarEventosDesdePool(
        PoolHabitat $pool,
        int $numEncuentros,
        CarbonInterface $inicio,
        CarbonInterface $fin,
        ?callable $aleatorio = null,
        bool $permitirEmboscadas = true,
        bool $permitirExcepcionales = true,
    ): ColeccionEventosExploracion {
        if ($numEncuentros <= 0 || $pool->isEmpty() || ! $fin->greaterThan($inicio)) {
            return new ColeccionEventosExploracion();
        }

        $aleatorio ??= static fn (): float => mt_rand(0, 999) / 1000;

        $poolPonderado = self::poolPonderadoDe($pool);
        if ($poolPonderado->isEmpty()) {
            return new ColeccionEventosExploracion();
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

            $eventos[] = self::generarEventoDesde(
                $pool,
                $poolPonderado,
                $aleatorio(),
                $aleatorio,
                $timestamp,
                $permitirEmboscadas,
                $permitirExcepcionales,
            );
        }

        return new ColeccionEventosExploracion($eventos);
    }

    private static function generarEventoDesde(
        PoolHabitat $pool,
        ColeccionPoolPonderado $poolPonderado,
        float $tiradaTipo,
        callable $aleatorio,
        CarbonInterface $timestamp,
        bool $permitirEmboscadas,
        bool $permitirExcepcionales,
    ): EventoExploracion {
        $timestampIso = $timestamp->toIso8601String();
        $porcentaje = $tiradaTipo * 100;

        if ($porcentaje < self::PROBABILIDAD_ENCUENTRO) {
            return self::eventoEncuentro($poolPonderado, $aleatorio, $permitirEmboscadas, $permitirExcepcionales, $timestampIso);
        }

        if ($porcentaje < self::PROBABILIDAD_ENCUENTRO + self::PROBABILIDAD_HALLAZGO) {
            return self::eventoHallazgo($pool, $poolPonderado, $aleatorio, $timestampIso);
        }

        $hastaEspecial = self::PROBABILIDAD_ENCUENTRO + self::PROBABILIDAD_HALLAZGO + self::PROBABILIDAD_ENCUENTRO_ESPECIAL;

        if ($porcentaje < $hastaEspecial) {
            if (! $permitirEmboscadas) {
                // Emboscada no permitida: el encuentro especial se convierte en
                // hallazgo o evento neutral, nunca en emboscada.
                return $aleatorio() < 0.5
                    ? self::eventoHallazgo($pool, $poolPonderado, $aleatorio, $timestampIso)
                    : self::eventoNeutral($timestampIso);
            }

            return self::eventoEmboscada($poolPonderado, $aleatorio, $timestampIso);
        }

        if ($porcentaje < $hastaEspecial + self::PROBABILIDAD_CONTRATIEMPO) {
            return self::eventoContratiempo($aleatorio, $timestampIso);
        }

        return self::eventoNeutral($timestampIso);
    }

    /**
     * Encuentro con subtipos: 80 % normal · 10 % grupo · 7 % emboscada · 3 % excepcional.
     * El subtipo emboscada se materializa como evento `emboscada` con pokemon_ids.
     * Si las emboscadas no están permitidas el bucket se convierte en grupo y, si
     * los excepcionales no están permitidos, su bucket también pasa a grupo.
     */
    private static function eventoEncuentro(
        ColeccionPoolPonderado $pool,
        callable $aleatorio,
        bool $permitirEmboscadas,
        bool $permitirExcepcionales,
        string $timestamp,
    ): EventoExploracion {
        $rollSubtipo = $aleatorio() * 100;

        if ($rollSubtipo < self::SUBTIPO_EMBOSCADA) {
            if ($permitirEmboscadas) {
                return self::eventoEmboscada($pool, $aleatorio, $timestamp);
            }

            $subtype = 'grupo';
        } elseif ($rollSubtipo < self::SUBTIPO_EMBOSCADA + self::SUBTIPO_EXCEPCIONAL) {
            $subtype = $permitirExcepcionales ? 'excepcional' : 'grupo';
        } elseif ($rollSubtipo < self::SUBTIPO_EMBOSCADA + self::SUBTIPO_EXCEPCIONAL + self::SUBTIPO_GRUPO) {
            $subtype = 'grupo';
        } else {
            $subtype = 'normal';
        }

        $elegido = self::elegirPonderadoDe($pool, $aleatorio);
        if ($elegido === null) {
            throw new LogicException('El pool ponderado está vacío');
        }

        return new EventoExploracion(
            tipo: 'encuentro',
            subtype: $subtype,
            pokemonId: $elegido->id,
            timestamp: $timestamp,
        );
    }

    /**
     * Hallazgo → caramelos (D8): familia (pokemon_id), EV (stat) o tipo (tipo_id).
     * El EV y el tipo se restringen al pool del hábitat: se elige un pokémon del
     * pool (ponderado) y de él un stat con effort>0 o uno de sus tipos.
     * Fallback a valores aleatorios globales si el pool no aporta stats/tipos.
     */
    private static function eventoHallazgo(
        PoolHabitat $pool,
        ColeccionPoolPonderado $poolPonderado,
        callable $aleatorio,
        string $timestamp,
    ): EventoExploracion {
        $roll = $aleatorio() * 100;

        if ($roll < self::HALLAZGO_FAMILIA) {
            $elegido = self::elegirPonderadoDe($poolPonderado, $aleatorio);
            if ($elegido === null) {
                throw new LogicException('El pool ponderado está vacío');
            }

            return new EventoExploracion(
                tipo: 'hallazgo',
                subtype: 'caramelo_familia',
                pokemonId: $elegido->id,
                cantidad: 1,
                timestamp: $timestamp,
            );
        }

        if ($roll < self::HALLAZGO_EV) {
            return new EventoExploracion(
                tipo: 'hallazgo',
                subtype: 'caramelo_ev',
                stat: self::elegirStatDelPool($pool, $aleatorio),
                cantidad: 1,
                timestamp: $timestamp,
            );
        }

        return new EventoExploracion(
            tipo: 'hallazgo',
            subtype: 'caramelo_tipo',
            tipoId: self::elegirTipoDelPool($pool, $aleatorio)->value,
            cantidad: 1,
            timestamp: $timestamp,
        );
    }

    /**
     * Elige un stat con effort>0 de un pokémon del pool (ponderado). Un solo
     * caramelo EV por hallazgo: se elige el pokémon y después UNO de sus stats
     * con effort>0; en sucesivos hallazgos se repartirán los demás. Fallback a
     * stat aleatorio 1-6 si ningún pokémon del pool tiene stats con effort.
     */
    private static function elegirStatDelPool(PoolHabitat $pool, callable $aleatorio): int
    {
        $candidatos = $pool->filter(
            static fn (PokemonDelPool $pokemon): bool => ! $pokemon->stats->isEmpty(),
        );

        if ($candidatos->isEmpty()) {
            return self::statAleatorio($aleatorio);
        }

        $elegido = self::poolPonderadoDe($candidatos)->elegirConAleatorio($aleatorio);
        if ($elegido === null) {
            return self::statAleatorio($aleatorio);
        }

        /** @var PokemonDelPool|null $pokemon */
        $pokemon = $candidatos->first(
            static fn (PokemonDelPool $candidato): bool => $candidato->id === $elegido->id,
        );

        if ($pokemon === null) {
            return self::statAleatorio($aleatorio);
        }

        $stats = $pokemon->stats->toList();
        $elegidoStat = $stats[min(count($stats) - 1, (int) floor($aleatorio() * count($stats)))];

        return $elegidoStat->stat;
    }

    /**
     * Elige un tipo de un pokémon del pool (ponderado). Fallback a tipo
     * aleatorio global si ningún pokémon del pool tiene tipos.
     */
    private static function elegirTipoDelPool(PoolHabitat $pool, callable $aleatorio): TipoPokemon
    {
        $candidatos = $pool->filter(
            static fn (PokemonDelPool $pokemon): bool => ! $pokemon->tipos->isEmpty(),
        );

        if ($candidatos->isEmpty()) {
            return self::tipoAleatorio($aleatorio);
        }

        $elegido = self::poolPonderadoDe($candidatos)->elegirConAleatorio($aleatorio);
        if ($elegido === null) {
            return self::tipoAleatorio($aleatorio);
        }

        /** @var PokemonDelPool|null $pokemon */
        $pokemon = $candidatos->first(
            static fn (PokemonDelPool $candidato): bool => $candidato->id === $elegido->id,
        );

        if ($pokemon === null) {
            return self::tipoAleatorio($aleatorio);
        }

        $tipos = $pokemon->tipos->toList();

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
     */
    private static function eventoEmboscada(ColeccionPoolPonderado $pool, callable $aleatorio, string $timestamp): EventoExploracion
    {
        $num = $aleatorio() < 0.5 ? 2 : 3;
        $ids = [];
        for ($i = 0; $i < $num; $i++) {
            $elegido = self::elegirPonderadoDe($pool, $aleatorio);
            if ($elegido === null) {
                break;
            }
            $ids[] = $elegido->id;
        }

        return new EventoExploracion(
            tipo: 'emboscada',
            subtype: 'emboscada',
            pokemonIds: new IntCollection($ids),
            timestamp: $timestamp,
        );
    }

    private static function eventoContratiempo(callable $aleatorio, string $timestamp): EventoExploracion
    {
        $subtype = self::SUBTIPOS_CONTRATIEMPO[
            min(count(self::SUBTIPOS_CONTRATIEMPO) - 1, (int) floor($aleatorio() * count(self::SUBTIPOS_CONTRATIEMPO)))
        ];

        return new EventoExploracion(tipo: 'contratiempo', subtype: $subtype, timestamp: $timestamp);
    }

    private static function eventoNeutral(string $timestamp): EventoExploracion
    {
        return new EventoExploracion(tipo: 'neutral', detalle: 'evento neutral', timestamp: $timestamp);
    }
}
