<?php

declare(strict_types=1);

namespace Src\Huevos\Domain;

/**
 * Límites y mínimos del sistema de huevos.
 *
 * Los estados se derivan de timestamps; estos valores solo definen cuántos
 * huevos pueden coexistir y cuántos reclutados hacen falta para poner uno.
 */
final class ReglasHuevo
{
    public const MAX_GESTANDO = 1;

    public const MAX_INCUBANDO = 1;

    public const MAX_ACTIVO_POR_FAMILIA = 1;

    public const RECLUTADOS_MINIMOS = 2;
}
