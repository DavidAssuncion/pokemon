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

        $advertencias = self::advertenciasDeTipos($miembros, $tiposPool);

        if ($capacidad < $dificultad) {
            $advertencias[] = "Pokémon débiles para el nivel {$nivel}";
        }

        $riesgo = $advertencias === []
            ? self::riesgoPorCapacidad($capacidad, $dificultad)
            : self::RIESGO_EXTREMO;

        if ($advertencias === []) {
            $advertencias[] = 'Equipo bien preparado para esta zona';
        }

        $afinidad = $afinidades === [] ? 0 : (int) round(array_sum($afinidades) / count($afinidades));

        return [
            'peligro_estrellas' => $peligro,
            'afinidad' => $afinidad,
            'advertencias' => $advertencias,
            'roles' => $roles,
            'riesgo' => $riesgo,
            'recompensa_esperada' => self::recompensaEsperada($riesgo),
        ];
    }

    /**
     * Advertencia por tipo sin ventaja contra el pool (Fracaso asegurado).
     *
     * @param  list<array{tipos: list<TipoPokemon>, enPool: bool, rol: RolExploracion, base: int}>  $miembros
     * @param  list<TipoPokemon>  $tiposPool
     * @return list<string>
     */
    private static function advertenciasDeTipos(array $miembros, array $tiposPool): array
    {
        $advertencias = [];

        foreach ($miembros as $miembro) {
            foreach ($miembro['tipos'] as $tipo) {
                $tipoResistente = self::tipoQueMasResiste($tipo, $tiposPool);
                if ($tipoResistente !== null) {
                    $advertencias[] = "Pokémon de tipo {$tipo->label()} en zona con Pokémon {$tipoResistente->label()}";
                }
            }
        }

        return $advertencias;
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

    /**
     * Tipo del pool que más resiste al tipo del miembro (efectividad mínima,
     * incluida inmunidad). null si el miembro es súper-eficaz contra todo el pool.
     *
     * @param  list<TipoPokemon>  $tiposPool
     */
    private static function tipoQueMasResiste(TipoPokemon $ataque, array $tiposPool): ?TipoPokemon
    {
        $peor = null;
        $peorEfectividad = PHP_FLOAT_MAX;

        foreach ($tiposPool as $defensa) {
            $efectividad = $ataque->effectivenessAgainst($defensa);
            if ($efectividad <= 1.0 && $efectividad < $peorEfectividad) {
                $peor = $defensa;
                $peorEfectividad = $efectividad;
            }
        }

        return $peor;
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
