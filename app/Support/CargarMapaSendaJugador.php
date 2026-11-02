<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PlayerTypePath;
use Src\Shared\Domain\Sendas\MapaSendaJugador;

/**
 * Carga los niveles de Senda de tipo de un jugador desde
 * `player_type_paths`.
 *
 * El nivel se deriva de la experiencia acumulada (`exp`) con la curva
 * vigente; la columna `level` está obsoleta y no se consulta.
 *
 * La consulta ignora el scope de usuario porque el consumidor puede no ser
 * el usuario autenticado (jobs, simulaciones, combates ya encolados).
 */
final class CargarMapaSendaJugador
{
    public function para(int $userId): MapaSendaJugador
    {
        $experiencias = PlayerTypePath::query()
            ->withoutGlobalScope(PlayerTypePath::USER_SCOPE_NAME)
            ->where('user_id', $userId)
            ->pluck('exp', 'type_id')
            ->all();

        if ($experiencias === []) {
            return MapaSendaJugador::vacio();
        }

        $niveles = [];

        foreach ($experiencias as $tipoId => $exp) {
            $niveles[(int) $tipoId] = SendaProgreso::nivel((int) $exp);
        }

        $beneficios = config('sendas.beneficios', []);

        return MapaSendaJugador::desdeArray(
            $niveles,
            is_array($beneficios) ? $beneficios : [],
        );
    }
}
