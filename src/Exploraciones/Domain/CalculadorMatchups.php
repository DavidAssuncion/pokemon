<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Src\Exploraciones\Domain\Collections\MatchupCollection;
use Src\Exploraciones\Domain\DataTransferObjects\Matchup;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Tipos\TipoPokemon;
use Src\Shared\Tipos\TypeChart;

/**
 * Matchups de tipos de una expedición para el preview (semáforo de tipos).
 *
 * Para cada miembro y cada tipo del pool calcula dos direcciones:
 * - defensa: efectividad del tipo del pool contra TODOS los tipos del miembro
 *   (el miembro se defiende con ambos tipos).
 * - ataque: prevalece el tipo del miembro más óptimo contra el tipo del pool.
 *
 * Clasificación (prioridad estricta):
 * - severo si ataque === TypeChart::INMUNE (inmunidad ofensiva: no puede dañar).
 * - negativo si defensa > 1.0 (el pool le pega súper eficaz).
 * - positivo si defensa < 1.0 (resiste).
 * - defensa === 1.0 → desempate ofensivo: ataque < 1.0 → negativo; ataque > 1.0 →
 *   positivo; ataque === 1.0 → neutral (se omite del resultado, no se muestra).
 * - La inmunidad defensiva (pool→miembro = TypeChart::INMUNE) es ventaja → positivo (nunca severo).
 *
 * Los matchups neutrales NO se incluyen en el resultado. Orden estable: por miembro
 * y por tipo del pool.
 */
final class CalculadorMatchups
{
    public const POSITIVO = 'positivo';
    public const NEGATIVO = 'negativo';
    public const SEVERO = 'severo';
    public const NEUTRAL = 'neutral';

    /**
     * @param  list<array{tipos: list<TipoPokemon>, enPool: bool, rol: RolExploracion, base: int}>  $miembros
     * @param  list<TipoPokemon>  $tiposPool
     */
    public static function calcular(array $miembros, array $tiposPool): MatchupCollection
    {
        $matchups = new MatchupCollection();

        foreach ($miembros as $miembro) {
            foreach ($tiposPool as $poolTipo) {
                $defensa = self::defensa($poolTipo, $miembro['tipos']);
                $ataque = self::ataqueMaximo($miembro['tipos'], $poolTipo);
                $clasificacion = self::clasificar($defensa, $ataque);

                if ($clasificacion === self::NEUTRAL) {
                    continue;
                }

                $matchups->add(new Matchup(
                    miembroTipos: new StringCollection(array_map(
                        static fn (TipoPokemon $tipo): string => $tipo->label(),
                        $miembro['tipos'],
                    )),
                    poolTipo: $poolTipo->label(),
                    defensa: $defensa,
                    ataque: $ataque,
                    clasificacion: $clasificacion,
                ));
            }
        }

        return $matchups;
    }

    public static function clasificar(float $defensa, float $ataque): string
    {
        if ($ataque === TypeChart::INMUNE) {
            return self::SEVERO;
        }

        if ($defensa > 1.0) {
            return self::NEGATIVO;
        }

        if ($defensa < 1.0) {
            return self::POSITIVO;
        }

        if ($ataque < 1.0) {
            return self::NEGATIVO;
        }

        if ($ataque > 1.0) {
            return self::POSITIVO;
        }

        return self::NEUTRAL;
    }

    /**
     * ¿Hay algún matchup crítico (negativo o severo)? Dispara el riesgo Extremo.
     */
    public static function hayMatchupCritico(MatchupCollection $matchups): bool
    {
        foreach ($matchups as $matchup) {
            if ($matchup->clasificacion === self::NEGATIVO || $matchup->clasificacion === self::SEVERO) {
                return true;
            }
        }

        return false;
    }

    /**
     * El miembro se defiende con TODOS sus tipos: producto de efectividades
     * del tipo del pool contra cada tipo del miembro.
     *
     * @param  list<TipoPokemon>  $tiposMiembro
     */
    private static function defensa(TipoPokemon $poolTipo, array $tiposMiembro): float
    {
        $defensa = 1.0;

        foreach ($tiposMiembro as $tipoMiembro) {
            $defensa *= $poolTipo->effectivenessAgainst($tipoMiembro);
        }

        return $defensa;
    }

    /**
     * Prevalece el tipo del miembro más óptimo: máximo de efectividades
     * de cada tipo del miembro contra el tipo del pool.
     *
     * @param  list<TipoPokemon>  $tiposMiembro
     */
    private static function ataqueMaximo(array $tiposMiembro, TipoPokemon $poolTipo): float
    {
        $ataque = 0.0;

        foreach ($tiposMiembro as $tipoMiembro) {
            $ataque = max($ataque, $tipoMiembro->effectivenessAgainst($poolTipo));
        }

        return $ataque;
    }
}
