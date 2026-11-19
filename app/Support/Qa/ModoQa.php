<?php

declare(strict_types=1);

namespace App\Support\Qa;

/**
 * Política única del modo QA. El flag POKEDEX_UNLOCK_ALL no habilita
 * mutaciones por sí solo: siempre exige además un entorno local/testing.
 */
final class ModoQa
{
    public static function entornoPermitido(): bool
    {
        return app()->environment('local', 'testing');
    }

    public static function asegurarEntornoPermitido(): void
    {
        abort_unless(self::entornoPermitido(), 404);
    }

    public static function accionesPokedexHabilitadas(bool $unlockAll): bool
    {
        return $unlockAll && self::entornoPermitido();
    }
}
