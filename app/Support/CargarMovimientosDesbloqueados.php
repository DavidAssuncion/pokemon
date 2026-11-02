<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Move;
use App\Models\PlayerMoveUnlock;

/**
 * Carga los slugs de los movimientos que un jugador ha desbloqueado en sus
 * Sendas de tipo.
 *
 * La consulta ignora el scope de usuario porque el consumidor puede no ser el
 * usuario autenticado (jobs, simulaciones o combates ya encolados). El pool es
 * solo de slugs: el selector rival cruza después esta lista con las especies
 * que realmente pueden aprender cada movimiento.
 */
final class CargarMovimientosDesbloqueados
{
    /**
     * @return list<string>
     */
    public function para(int $userId): array
    {
        $moveIds = PlayerMoveUnlock::query()
            ->withoutGlobalScope(PlayerMoveUnlock::USER_SCOPE_NAME)
            ->where('user_id', $userId)
            ->pluck('move_id');

        if ($moveIds->isEmpty()) {
            return [];
        }

        $slugs = [];
        foreach (Move::findMany($moveIds->all()) as $move) {
            if ($move->slug !== '') {
                $slugs[] = $move->slug;
            }
        }

        sort($slugs);

        return $slugs;
    }
}
