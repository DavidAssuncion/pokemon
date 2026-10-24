<?php

declare(strict_types=1);

namespace Src\Equipos\App;

use App\Models\Team;
use App\Models\TeamMember;

/**
 * Actualiza el objeto equipado de los reclutados de un equipo.
 *
 * El objeto vive en `reclutados.obj_equipados` como lista de una sola clave.
 * La validación del catálogo y del equipo queda en el controlador/FormRequest.
 */
final class ActualizarObjetosEquipo
{
    /**
     * @param  array<string|int, string|null>  $items  slot => item_key
     * @return array<int|string, string> slot => item_key
     */
    public function actualizar(Team $team, array $items, int $userId): array
    {
        foreach ($items as $slot => $itemKey) {
            $member = TeamMember::where('team_id', $team->id)
                ->where('slot', (int) $slot)
                ->first();

            if ($member === null) {
                continue;
            }

            $reclutado = $member->reclutado;
            abort_unless($reclutado?->user_id === $userId, 404);

            $reclutado->update([
                'obj_equipados' => is_string($itemKey) && $itemKey !== '' ? [$itemKey] : [],
            ]);
        }

        return TeamMember::where('team_id', $team->id)
            ->with('reclutado')
            ->get()
            ->mapWithKeys(function (TeamMember $member): array {
                $items = $member->reclutado?->obj_equipados;
                $item = is_array($items) ? ($items[0] ?? '') : '';

                return [(string) $member->slot => is_string($item) ? $item : ''];
            })
            ->all();
    }
}
