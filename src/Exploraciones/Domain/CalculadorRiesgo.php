<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Src\Exploraciones\Domain\DataTransferObjects\EvaluacionRiesgo;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Tipos\TipoPokemon;

/**
 * RF-11: riesgo y advertencias de una expedición para el preview (dominio puro).
 *
 * Devuelve un EvaluacionRiesgo: peligro_estrellas, afinidad, advertencias,
 * roles, matchups, riesgo (Bajo/Medio/Alto/Extremo) y recompensa_esperada.
 * Sin probabilidades numéricas. `toArray()` queda como frontera JSON.
 */
final class CalculadorRiesgo
{
    public const RIESGO_BAJO = 'Bajo';
    public const RIESGO_MEDIO = 'Medio';
    public const RIESGO_ALTO = 'Alto';
    public const RIESGO_EXTREMO = 'Extremo';

    /** Capacidad necesaria para considerar el riesgo bajo (1.5× dificultad). */
    private const FACTOR_RIESGO_BAJO = 1.5;

    /**
     * @param  list<TipoPokemon>  $tiposPool  Tipos de los pokémon del hábitat al nivel.
     * @param  list<array{tipos: list<TipoPokemon>, enPool: bool, rol: RolExploracion, base: int}>  $miembros
     */
    public static function evaluar(int $peligro, int $nivel, int $capacidad, array $tiposPool, array $miembros): EvaluacionRiesgo
    {
        $peligro = max(CalculadorPeligro::PELIGRO_MIN, min(CalculadorPeligro::PELIGRO_MAX, $peligro));
        $dificultad = CalculadorPeligro::peligroZona($peligro, $nivel);

        $afinidades = [];
        $roles = new StringCollection();
        foreach ($miembros as $miembro) {
            $afinidades[] = CalculadorCapacidadEquipo::afinidadDeMiembro($miembro['tipos'], $tiposPool, $miembro['enPool']);
            $roles->add($miembro['rol']->value);
        }

        $matchups = CalculadorMatchups::calcular($miembros, $tiposPool);

        // Advertencias: SOLO mensajes no de tipo. El semáforo de tipos vive en matchups.
        $advertencias = new StringCollection();
        if ($capacidad < $dificultad) {
            $advertencias->add("Pokémon débiles para el nivel {$nivel}");
        }

        // Fracaso asegurado: matchup crítico (negativo/severo) O debilidad de nivel.
        $riesgo = CalculadorMatchups::hayMatchupCritico($matchups) || ! $advertencias->isEmpty()
            ? self::RIESGO_EXTREMO
            : self::riesgoPorCapacidad($capacidad, $dificultad);

        if ($advertencias->isEmpty()) {
            $advertencias->add('Equipo bien preparado para esta zona');
        }

        $afinidad = $afinidades === [] ? 0 : (int) round(array_sum($afinidades) / count($afinidades));

        return new EvaluacionRiesgo(
            peligroEstrellas: $peligro,
            afinidad: $afinidad,
            advertencias: $advertencias,
            roles: $roles,
            matchups: $matchups,
            riesgo: $riesgo,
            recompensaEsperada: self::recompensaEsperada($riesgo),
        );
    }

    private static function riesgoPorCapacidad(int $capacidad, int $dificultad): string
    {
        if ($capacidad >= $dificultad * self::FACTOR_RIESGO_BAJO) {
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
