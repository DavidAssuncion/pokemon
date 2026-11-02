<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Flag TEMPORAL de QA: desbloquea la Pokédex y los hábitats para ver todos
 * los Pokémon aunque no hayan sido avistados.
 *
 * Se activa con POKEDEX_UNLOCK_ALL=true o, solo en local/testing, con ?unlock=1.
 * Fuera de esos entornos, APP_DEBUG no habilita el flag.
 */
final class DesbloqueoPokedex
{
    public static function activo(Request $request): bool
    {
        if ((bool) config('pokedex.unlock_all')) {
            return true;
        }

        return $request->boolean('unlock')
            && app()->environment('local', 'testing');
    }
}
