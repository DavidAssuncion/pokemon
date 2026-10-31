<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use App\Enums\StatEnum;
use App\Models\Reclutado;

/**
 * Aplica el delta de EV de un complemento a todos los reclutados de la
 * familia (incluidos los que ya existían). Cap por stat: 512.
 */
final class AplicarEvsFamilia
{
    public function aplicar(int $userId, int $evolutionChainId, StatEnum $stat, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $clave = $stat->key();

        Reclutado::withoutUserScope()
            ->where('user_id', $userId)
            ->whereHas('pokemon', fn ($query) => $query->where('evolution_chain_id', $evolutionChainId))
            ->get()
            ->each(function (Reclutado $reclutado) use ($clave, $delta): void {
                $actual = $reclutado->evs->valorDe($clave);
                $nuevo = max(0.0, min(512.0, $actual + $delta));

                if ($nuevo === $actual) {
                    return;
                }

                $reclutado->evs = $reclutado->evs->conStat($clave, $nuevo);
                $reclutado->save();
            });
    }
}
