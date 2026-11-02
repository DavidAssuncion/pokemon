<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Reglas transversales del equipo de combate.
 *
 * El tamaño estándar es 3 (combates 3v3 y mazmorra 3v1). El límite se valida
 * al guardar miembros y al construir el equipo de batalla; el motor no fija
 * tamaños para permitir invocaciones.
 */
final class ReglasEquipo
{
    public const MIEMBROS = 3;

    public const SLOT_MIN = 1;

    public const SLOT_MAX = 3;
}
