<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Src\Shared\Tipos\TipoPokemon;

/**
 * RF-11: riesgo y advertencias de una expedición para el preview (dominio puro).
 *
 * Devuelve el contrato JSON del preview: peligro_estrellas, afinidad,
 * advertencias[], roles[], riesgo (Bajo/Medio/Alto/Extremo) y
 * recompensa_esperada. Sin probabilidades numéricas.
 */
final class CalculadorRiesgo
{
    public const RIESGO_BAJO = 'Bajo';
    public const RIESGO_MEDIO = 'Medio';
    public const RIESGO_ALTO = 'Alto';
    public const RIESGO_EXTREMO = 'Extremo';

    /**
     * @param  list<TipoPokemon>  $tiposPool  Tipos de los pokémon del hábitat al nivel.
     * @param  list<array{tipos: list<TipoPokemon>, enPool: bool, rol: RolExploracion, base: int}>  $miembros
     * @return array{
     *     peligro_estrellas: int,
     *     afinidad: int,
     *     advertencias: list<string>,
     *     roles: list<string>,
     *     matchups: list<array{
     *         miembro_tipos: list<string>,
     *         pool_tipo: string,
     *         defensa: float,
     *         ataque: float,
     *         clasificacion: string,
     *     }>,
     *     riesgo: string,
     *     recompensa_esperada: string,
     * }
     */
    public static function evaluar(int $peligro, int $nivel, int $capacidad, array $tiposPool, array $miembros): array
    {
        $peligro = max(CalculadorPeligro::PELIGRO_MIN, min(CalculadorPeligro::PELIGRO_MAX, $peligro));
        $dificultad = CalculadorPeligro::peligroZona($peligro, $nivel);

        $afinidades = [];
        $roles = [];
        foreach ($miembros as $miembro) {
            $afinidades[] = CalculadorCapacidadEquipo::afinidadDeMiembro($miembro['tipos'], $tiposPool, $miembro['enPool']);
            $roles[] = $miembro['rol']->value;
        }

        $matchups = CalculadorMatchups::calcular($miembros, $tiposPool);

        // Advertencias: SOLO mensajes no de tipo. El semáforo de tipos vive en matchups.
        $advertencias = [];
        if ($capacidad < $dificultad) {
            $advertencias[] = "Pokémon débiles para el nivel {$nivel}";
        }

        // Fracaso asegurado: matchup crítico (negativo/severo) O debilidad de nivel.
        $riesgo = CalculadorMatchups::hayMatchupCritico($matchups) || $advertencias !== []
            ? self::RIESGO_EXTREMO
            : self::riesgoPorCapacidad($capacidad, $dificultad);

        if ($advertencias === []) {
            $advertencias[] = 'Equipo bien preparado para esta zona';
        }

        $afinidad = $afinidades === [] ? 0 : (int) round(array_sum($afinidades) / count($afinidades));

        return [
            'peligro_estrellas' => $peligro,
            'afinidad' => $afinidad,
            'advertencias' => $advertencias,
            'roles' => $roles,
            'matchups' => $matchups,
            'riesgo' => $riesgo,
            'recompensa_esperada' => self::recompensaEsperada($riesgo),
        ];
    }

    private static function riesgoPorCapacidad(int $capacidad, int $dificultad): string
    {
        if ($capacidad >= $dificultad * 1.5) {
            return self::RIESGO_BAJO;
        }

        if ($capacidad >= $dificultad) {
            return self::RIESGO_MEDIO;
        }

        return self::RIESGO_ALTO;
    }

    private static function recompensaEsperada(string $riesgo): string
    {
        return match ($riesgo) {
            self::RIESGO_BAJO => 'alta',
            self::RIESGO_MEDIO => 'normal',
            self::RIESGO_ALTO => 'baja',
            default => 'mínima',
        };
    }
}
