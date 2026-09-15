<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Probabilidades de shiny compartidas entre encuentros salvajes y huevos.
 * aleatorio: callable(int $max): int returning 1..$max (true when result === 1).
 */
final class ProbabilidadesShiny
{
    public const SALVAJE = 2048;
    public const HUEVO = 512;

    public static function esShiny(int $entre, ?callable $aleatorio = null): bool
    {
        $aleatorio ??= static fn (int $max): int => random_int(1, $max);

        return $aleatorio($entre) === 1;
    }
}
