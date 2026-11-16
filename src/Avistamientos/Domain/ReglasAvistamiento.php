<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain;

/**
 * Reglas puras del evento global de avistamientos.
 *
 * Cada ventana abarca tres días naturales en la zona horaria del juego y se
 * numera de forma consecutiva desde el ancla. La selección de hábitat y
 * familia es determinista para todos los jugadores.
 */
final class ReglasAvistamiento
{
    public const string ANCLA = '2026-01-01 00:00:00';

    public const string ZONA_HORARIA = 'Europe/Madrid';

    public const int DURACION_DIAS = 3;

    public const float FACTOR_APARICION = 5.0;

    /** @var list<int> */
    public const array HABITATS_EXCLUIDOS = [49];

    public const int MIN_ESPECIES_FAMILIA = 2;

    public const int MIN_ESPECIES_FAMILIA_FALLBACK = 1;
}
