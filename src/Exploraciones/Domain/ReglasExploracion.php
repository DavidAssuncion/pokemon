<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

/**
 * Reglas de diseño de las exploraciones.
 *
 * - Cada encuentro requiere 30 minutos de tiempo efectivo: la cadencia se
 *   calcula por tiempo transcurrido (no por tick) con un máximo de 12 eventos
 *   por procesamiento.
 * - El descanso recupera 1% de HP por minuto real.
 * - Economía individual (Fase 1): todas las recompensas se reducen al 50% y la
 *   EXP se reparte 50% cuenta / 50% reclutado vía EconomiaRecompensas. Ya no
 *   existe reparto 80% entre 3 miembros en el flujo activo.
 */
final class ReglasExploracion
{
    public const MINUTOS_POR_ENCUENTRO = 30;

    /** Máximo de eventos programados por procesamiento (fuente: CadenciaEventos). */
    public const MAX_EVENTOS_POR_PROCESAMIENTO = CadenciaEventos::MAX_EVENTOS_POR_PROCESAMIENTO;

    public const HP_POR_MINUTO_DESCANSO = 1;

    /** Factor global de la economía individual (Fase 1): recompensas al 50 %. */
    public const FACTOR_RECOMPENSAS = 0.5;

    /** Fracción de EXP que recibe la cuenta en la economía individual. */
    public const FRACCION_EXP_INDIVIDUAL = 0.5;
}
