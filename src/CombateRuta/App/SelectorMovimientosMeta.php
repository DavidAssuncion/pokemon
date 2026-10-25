<?php

declare(strict_types=1);

namespace Src\CombateRuta\App;

use App\Models\Move;
use App\Models\Pokemon;
use App\Models\PokemonType;
use Src\Battle\Domain\ReglasBatalla;

/**
 * Construye el loadout "meta" de un Pokémon salvaje de ruta.
 *
 * Los salvajes no deben cargar el learnset completo de su especie (hasta 150
 * movimientos): eso hincha el árbol de acciones de la IA y diluye el combate.
 * Este selector elige entre 6 y 8 movimientos (6 en ruta nivel 1, 7 en nivel 2
 * y 8 en nivel 3) priorizando daño, STAB, cobertura de tipos y un par de
 * movimientos de utilidad.
 */
final class SelectorMovimientosMeta
{
    private const MOVIMIENTOS_MINIMOS = 6;

    private const MOVIMIENTOS_MAXIMOS = 8;

    private const UTILIDAD_MAXIMA = 2;

    private const CATEGORIA_ESTADO = 'estado';

    private const POTENCIA_FIJA_ASUMIDA = 50.0;

    /**
     * @return list<string> Slugs de los movimientos elegidos.
     */
    public function seleccionar(Pokemon $pokemon, int $nivelRuta, ?int $nivelDesbloqueo = null): array
    {
        $pokemon->loadMissing(['moves', 'types']);

        $candidatos = $this->candidatos($pokemon, $nivelDesbloqueo);
        if ($candidatos === []) {
            return [];
        }

        return $this->elegir(
            $candidatos,
            $this->tiposDe($pokemon),
            $this->limite($nivelRuta, count($candidatos)),
        );
    }

    /**
     * Movimientos disponibles para el nivel del jugador (los bloqueados no
     * pueden aparecer en el loadout del salvaje).
     *
     * @return list<Move>
     */
    private function candidatos(Pokemon $pokemon, ?int $nivelDesbloqueo): array
    {
        $candidatos = [];

        foreach ($pokemon->moves as $move) {
            if ($nivelDesbloqueo !== null && $move->nivel_requerido > $nivelDesbloqueo) {
                continue;
            }

            // Los movimientos que sacrifican al usuario (Explosión, Memento,
            // Deseo Final…) no forman parte de un loadout meta de salvaje.
            if (is_array($move->data) && ($move->data['autodestruccion'] ?? false) === true) {
                continue;
            }

            $candidatos[] = $move;
        }

        return $candidatos;
    }

    /**
     * Tipos de la especie indexados por id (para el bonus de STAB).
     *
     * @return array<int, true>
     */
    private function tiposDe(Pokemon $pokemon): array
    {
        $tipos = [];

        foreach ($pokemon->types as $tipo) {
            /** @var PokemonType $tipo */
            $tipos[$tipo->type->value] = true;
        }

        return $tipos;
    }

    /**
     * Cantidad de movimientos según el nivel de ruta: 6, 7 u 8.
     */
    private function limite(int $nivelRuta, int $disponibles): int
    {
        $deseados = self::MOVIMIENTOS_MINIMOS + max(0, min(2, $nivelRuta - 1));

        return min($deseados, self::MOVIMIENTOS_MAXIMOS, $disponibles);
    }

    /**
     * Selección voraz: en cada hueco toma la acción de mayor puntuación.
     *
     * @param  list<Move>  $candidatos
     * @param  array<int, true>  $tiposPokemon
     * @return list<string>
     */
    private function elegir(array $candidatos, array $tiposPokemon, int $limite): array
    {
        $elegidos = [];
        $idsElegidos = [];
        $tiposCubiertos = [];
        $ofensivos = 0;
        $utilidades = 0;

        while (count($elegidos) < $limite) {
            $mejor = null;
            $mejorPuntuacion = null;
            $huecosRestantes = $limite - count($elegidos);

            foreach ($candidatos as $move) {
                if (isset($idsElegidos[$move->id])) {
                    continue;
                }

                $puntuacion = $this->puntuar(
                    $move,
                    $tiposPokemon,
                    $tiposCubiertos,
                    $huecosRestantes,
                    $ofensivos,
                    $utilidades,
                );

                if ($mejorPuntuacion === null || $puntuacion > $mejorPuntuacion) {
                    $mejor = $move;
                    $mejorPuntuacion = $puntuacion;
                }
            }

            if ($mejor === null) {
                break;
            }

            $elegidos[] = $mejor->slug;
            $idsElegidos[$mejor->id] = true;

            if ($this->esOfensivo($mejor)) {
                $tiposCubiertos[$mejor->type_id] = true;
                $ofensivos++;
            } else {
                $utilidades++;
            }
        }

        return $elegidos;
    }

    /**
     * Puntuación de un movimiento para el loadout del salvaje.
     *
     * @param  array<int, true>  $tiposPokemon
     * @param  array<int, true>  $tiposCubiertos
     */
    private function puntuar(
        Move $move,
        array $tiposPokemon,
        array $tiposCubiertos,
        int $huecosRestantes,
        int $ofensivos,
        int $utilidades,
    ): float {
        if ($this->esOfensivo($move)) {
            return $this->puntuarOfensivo($move, $tiposPokemon, $tiposCubiertos);
        }

        return $this->puntuarUtilidad($move, $huecosRestantes, $ofensivos, $utilidades);
    }

    /**
     * @param  array<int, true>  $tiposPokemon
     * @param  array<int, true>  $tiposCubiertos
     */
    private function puntuarOfensivo(Move $move, array $tiposPokemon, array $tiposCubiertos): float
    {
        $potencia = $move->base_power > 0
            ? (float) $move->base_power
            : self::POTENCIA_FIJA_ASUMIDA;

        $golpes = max(1.0, ($move->multihit_min + $move->multihit_max) / 2);
        $precision = $move->accuracy === null || $move->accuracy <= 0
            ? 1.0
            : max(0.3, min(1.0, $move->accuracy / 100));

        $puntuacion = $potencia * $golpes * $precision;

        if (isset($tiposPokemon[$move->type_id])) {
            $puntuacion *= ReglasBatalla::BONUS_STAB;
        }

        // Cobertura: un tipo que el loadout todavía no tiene vale más.
        if (! isset($tiposCubiertos[$move->type_id])) {
            $puntuacion *= 1.25;
        }

        if ($move->priority > 0) {
            $puntuacion += $move->priority * 15.0;
        }

        if ($move->recoil_percent > 0) {
            $puntuacion *= 0.9;
        }

        if ($move->drain_percent > 0) {
            $puntuacion *= 1.05;
        }

        return $puntuacion;
    }

    private function puntuarUtilidad(
        Move $move,
        int $huecosRestantes,
        int $ofensivos,
        int $utilidades,
    ): float {
        $puntuacion = 40.0;

        if (is_string($move->status_effect) && $move->status_effect !== '') {
            $puntuacion += 25.0;
        }

        if (is_string($move->effect_key) && $move->effect_key !== '') {
            $puntuacion += 15.0;
        }

        if ($move->heal_percent > 0) {
            $puntuacion += 15.0;
        }

        if (is_array($move->self_effects) && $move->self_effects !== []) {
            $puntuacion += 10.0;
        }

        // Sin ataques todavía, la utilidad espera su turno.
        if ($ofensivos === 0) {
            $puntuacion *= 0.5;
        }

        // Reserva como máximo dos huecos para utilidad; si ya están cubiertos,
        // solo entra una utilidad excepcionalmente buena.
        if ($utilidades >= self::UTILIDAD_MAXIMA) {
            $puntuacion *= 0.25;
        } elseif ($huecosRestantes <= self::UTILIDAD_MAXIMA) {
            $puntuacion *= 2.0;
        }

        return $puntuacion;
    }

    private function esOfensivo(Move $move): bool
    {
        return $move->category !== self::CATEGORIA_ESTADO;
    }
}
