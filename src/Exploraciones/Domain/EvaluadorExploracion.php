<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

/**
 * Evaluador de expediciones (dominio puro). RF-06 (resolución por evento) y
 * RF-08 (categoría final + multiplicadores).
 *
 * Resolución: dificultad = base(subtipo) + peligro×5; capacidad >= dificultad →
 * éxito; entre dificultad−15 y dificultad → éxito con coste (−5/−10 min);
 * < dificultad−15 → desventaja: 15 % salvaje huye · resto derrota (−10 min) ·
 * si < dificultad−30 → retirada probable. Emboscada: Vanguardia detecta
 * (evita o −50 %); vence → −10 min; pierde → −15 min + retirada probable.
 * Contratiempo mitigado por rol (Vanguardia terreno/bloqueo, Combatiente clima).
 */
final class EvaluadorExploracion
{
    public const DIFICULTAD_BASE_NORMAL = 30;
    public const DIFICULTAD_BASE_GRUPO = 45;
    public const DIFICULTAD_BASE_EMBOSCADA = 50;
    public const DIFICULTAD_BASE_EXCEPCIONAL = 55;

    public const MARGEN_EXITO_CON_COSTE = 15;
    public const UMBRAL_DESVENTAJA = 15;
    public const UMBRAL_RETIRADA = 30;

    public const PROBABILIDAD_HUIDA_SALVAJE = 0.15;
    public const PROBABILIDAD_RETIRADA = 0.5;
    public const PROBABILIDAD_EVITAR_EMBOSCADA = 0.5;
    public const PENALIZACION_EMBOSCADA_DETECTADA = 0.5;

    public const COSTE_EXITO_CON_COSTE_MIN = 5;
    public const COSTE_EXITO_CON_COSTE_MAX = 10;
    public const COSTE_DERROTA = 10;
    public const COSTE_EMBOSCADA_VICTORIA = 10;
    public const COSTE_EMBOSCADA_DERROTA = 15;

    public const CATEGORIA_EXITO_EXCEPCIONAL = 'exito_excepcional';
    public const CATEGORIA_EXITO = 'exito';
    public const CATEGORIA_EXITO_PARCIAL = 'exito_parcial';
    public const CATEGORIA_FRACASO = 'fracaso';
    public const CATEGORIA_RETIRADA = 'retirada';

    /** Resoluciones que cuentan como combate para la categoría final. */
    private const RESOLUCIONES_COMBATE = ['victoria', 'victoria_con_coste', 'superada', 'superada_con_cost', 'derrota', 'huida'];

    /** Resoluciones que cuentan como victoria (derrotado obtenido) para la categoría. */
    private const RESOLUCIONES_VICTORIA = ['victoria', 'victoria_con_coste', 'superada'];

    /**
     * RF-06: dificultad de un evento = base(subtipo) + peligro×5.
     */
    public static function dificultad(string $subtipo, int $peligro): int
    {
        $base = match ($subtipo) {
            'grupo' => self::DIFICULTAD_BASE_GRUPO,
            'emboscada' => self::DIFICULTAD_BASE_EMBOSCADA,
            'excepcional' => self::DIFICULTAD_BASE_EXCEPCIONAL,
            default => self::DIFICULTAD_BASE_NORMAL,
        };

        return $base + $peligro * 5;
    }

    /**
     * Resuelve un encuentro (normal/grupo/excepcional) contra la capacidad del
     * equipo. Devuelve la resolución con el tiempo perdido en minutos.
     *
     * @param  list<RolExploracion>  $roles  Modificadores de rol (huida/retirada).
     * @return array{resolucion: string, duration_loss: int, retirada?: bool, reason?: string}
     */
    public static function resolverEncuentro(
        string $subtipo,
        int $capacidad,
        int $peligro,
        callable $aleatorio,
        array $roles = [],
    ): array {
        $dificultad = self::dificultad($subtipo, $peligro);

        if ($capacidad >= $dificultad) {
            return ['resolucion' => 'victoria', 'duration_loss' => 0];
        }

        if ($capacidad >= $dificultad - self::MARGEN_EXITO_CON_COSTE) {
            $coste = $aleatorio() < 0.5 ? self::COSTE_EXITO_CON_COSTE_MIN : self::COSTE_EXITO_CON_COSTE_MAX;

            return ['resolucion' => 'victoria_con_coste', 'duration_loss' => $coste];
        }

        // Desventaja: retirada probable (< dificultad−30) → huida 15 % → derrota −10.
        if ($capacidad < $dificultad - self::UMBRAL_RETIRADA
            && $aleatorio() < self::PROBABILIDAD_RETIRADA * self::multiplicadorRetirada($roles)) {
            return [
                'resolucion' => 'retirada',
                'duration_loss' => 0,
                'retirada' => true,
                'reason' => 'grupo_enemigo',
            ];
        }

        if ($aleatorio() < self::PROBABILIDAD_HUIDA_SALVAJE * self::multiplicadorHuida($roles)) {
            return ['resolucion' => 'huida', 'duration_loss' => 0];
        }

        return ['resolucion' => 'derrota', 'duration_loss' => self::COSTE_DERROTA];
    }

    /**
     * Resuelve una emboscada. La Vanguardia detecta: evita (50 %) o afronta con
     * penalización del 50 % de capacidad. Vencer cuesta −10 min; perder −15 min
     * y deja retirada probable.
     *
     * @param  list<RolExploracion>  $roles
     * @return array{resolucion: string, duration_loss: int, dificultad: int, retirada_probable?: bool}
     */
    public static function resolverEmboscada(
        bool $detectadaPorVanguardia,
        int $capacidad,
        int $peligro,
        callable $aleatorio,
        array $roles = [],
    ): array {
        if ($detectadaPorVanguardia && $aleatorio() < self::PROBABILIDAD_EVITAR_EMBOSCADA) {
            return ['resolucion' => 'evitada', 'duration_loss' => 0, 'dificultad' => self::dificultad('emboscada', $peligro)];
        }

        $capacidadEfectiva = $detectadaPorVanguardia
            ? (int) floor($capacidad * self::PENALIZACION_EMBOSCADA_DETECTADA)
            : $capacidad;

        $resolucion = self::resolverEncuentro('emboscada', $capacidadEfectiva, $peligro, $aleatorio, $roles);

        if (in_array($resolucion['resolucion'], ['victoria', 'victoria_con_coste'], true)) {
            return [
                'resolucion' => 'superada',
                'duration_loss' => self::COSTE_EMBOSCADA_VICTORIA,
                'dificultad' => self::dificultad('emboscada', $peligro),
            ];
        }

        return [
            'resolucion' => 'superada_con_cost',
            'duration_loss' => self::COSTE_EMBOSCADA_DERROTA,
            'retirada_probable' => true,
            'dificultad' => self::dificultad('emboscada', $peligro),
        ];
    }

    /**
     * Resuelve un contratiempo (desorientacion −15, terreno −10, clima −10,
     * bloqueo −15) con mitigación por rol (Vanguardia terreno/bloqueo,
     * Combatiente clima).
     *
     * @param  list<RolExploracion>  $roles
     * @return array{resolucion: string, duration_loss: int}
     */
    public static function resolverContratiempo(string $subtipo, array $roles): array
    {
        $base = match ($subtipo) {
            'desorientacion', 'bloqueo' => 15,
            'terreno', 'clima' => 10,
            default => 10,
        };

        $mitigado = false;
        foreach ($roles as $rol) {
            if ($rol->mitigaContratiempo($subtipo)) {
                $mitigado = true;
                break;
            }
        }

        if ($mitigado) {
            $base = (int) ceil($base * 0.5);
        }

        return ['resolucion' => 'mitigado', 'duration_loss' => $base];
    }

    /**
     * RF-08: categoría final de la expedición a partir de los eventos resueltos.
     * - retirada presente → retirada.
     * - sin combates → exito.
     * - ratio victorias/combates ≥ 0.85 con un excepcional vencido → exito_excepcional.
     * - ≥ 0.6 → exito; ≥ 0.3 → exito_parcial; resto → fracaso.
     *
     * @param  list<array<string, mixed>>  $eventos
     */
    public static function categoriaFinal(array $eventos): string
    {
        $resoluciones = array_values(array_filter(
            array_column($eventos, 'resolucion'),
            static fn (mixed $resolucion): bool => is_string($resolucion) && $resolucion !== ''
        ));

        if (in_array(self::CATEGORIA_RETIRADA, $resoluciones, true)) {
            return self::CATEGORIA_RETIRADA;
        }

        $combates = array_values(array_filter(
            $resoluciones,
            static fn (string $resolucion): bool => in_array($resolucion, self::RESOLUCIONES_COMBATE, true),
        ));

        if ($combates === []) {
            return self::CATEGORIA_EXITO;
        }

        $victorias = count(array_filter(
            $combates,
            static fn (string $resolucion): bool => in_array($resolucion, self::RESOLUCIONES_VICTORIA, true),
        ));
        $ratio = $victorias / count($combates);

        if ($ratio >= 0.85 && self::vencioExcepcional($eventos)) {
            return self::CATEGORIA_EXITO_EXCEPCIONAL;
        }

        if ($ratio >= 0.6) {
            return self::CATEGORIA_EXITO;
        }

        if ($ratio >= 0.3) {
            return self::CATEGORIA_EXITO_PARCIAL;
        }

        return self::CATEGORIA_FRACASO;
    }

    /** Multiplicador de recompensas por categoría (RF-08). */
    public static function multiplicador(string $categoria): float
    {
        return match ($categoria) {
            self::CATEGORIA_EXITO_EXCEPCIONAL => 1.2,
            self::CATEGORIA_EXITO => 1.0,
            self::CATEGORIA_EXITO_PARCIAL => 0.7,
            self::CATEGORIA_FRACASO => 0.25,
            self::CATEGORIA_RETIRADA => 1.0,
            default => 1.0,
        };
    }

    /**
     * RF-07: un evento cuenta como victoria (derrotado) si su resolución es
     * 'victoria' o si NO tiene resolución (retrocompat de bitácoras antiguas).
     * Solo los eventos de combate (encuentro/pokemon/emboscada) pueden ser
     * derrotas: hallazgos y neutros nunca generan derrotado.
     *
     * @param  array<string, mixed>  $evento
     */
    public static function esVictoria(array $evento): bool
    {
        $tipo = $evento['tipo'] ?? null;
        $resolucion = $evento['resolucion'] ?? null;

        if ($resolucion === null && ! in_array($tipo, ['encuentro', 'pokemon', 'emboscada'], true)) {
            return false;
        }

        return $resolucion === null || $resolucion === 'victoria';
    }

    /**
     * ¿El evento es un encuentro con pokémon que puede reportar avistamiento?
     *
     * @param  array<string, mixed>  $evento
     */
    public static function esAvistamiento(array $evento): bool
    {
        $tipo = $evento['tipo'] ?? null;

        return in_array($tipo, ['pokemon', 'encuentro', 'emboscada', 'huida'], true);
    }

    /**
     * IDs de pokémon de un evento (pokemon_id o pokemon_ids).
     *
     * @param  array<string, mixed>  $evento
     * @return list<int>
     */
    public static function pokemonIdsDelEvento(array $evento): array
    {
        if (isset($evento['pokemon_ids']) && is_array($evento['pokemon_ids'])) {
            return array_values(array_map('intval', $evento['pokemon_ids']));
        }

        if (isset($evento['pokemon_id'])) {
            return [(int) $evento['pokemon_id']];
        }

        return [];
    }

    /**
     * @param  list<RolExploracion>  $roles
     */
    private static function multiplicadorHuida(array $roles): float
    {
        $multiplicador = 1.0;
        foreach ($roles as $rol) {
            $multiplicador *= $rol->multiplicadorHuidas();
        }

        return $multiplicador;
    }

    /**
     * @param  list<RolExploracion>  $roles
     */
    private static function multiplicadorRetirada(array $roles): float
    {
        $multiplicador = 1.0;
        foreach ($roles as $rol) {
            $multiplicador *= $rol->multiplicadorRetirada();
        }

        return $multiplicador;
    }

    /**
     * ¿Se venció un evento excepcional (subtype excepcional/especial o emboscada)?
     *
     * @param  list<array<string, mixed>>  $eventos
     */
    private static function vencioExcepcional(array $eventos): bool
    {
        foreach ($eventos as $evento) {
            $tipo = $evento['tipo'] ?? '';
            $subtype = $evento['subtype'] ?? '';
            $resolucion = $evento['resolucion'] ?? '';

            $esRaro = $tipo === 'emboscada' || in_array($subtype, ['excepcional', 'especial'], true);

            if ($esRaro && in_array($resolucion, ['victoria', 'superada'], true)) {
                return true;
            }
        }

        return false;
    }
}
