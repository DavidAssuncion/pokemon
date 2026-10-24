<?php

declare(strict_types=1);

namespace Src\Equipos\App;

use App\Models\Team;
use App\Models\TeamMember;
use Src\Equipos\Domain\ValueObjects\ObjetosEquipo;

/**
 * Actualiza el objeto equipado de los reclutados de un equipo.
 *
 * El objeto vive en `reclutados.obj_equipados` como lista de una sola clave.
 * La validación del catálogo y del equipo queda en el controlador/FormRequest.
 */
final class ActualizarObjetosEquipo
{
    public function actualizar(Team $team, ObjetosEquipo $items, int $userId): ObjetosEquipo
    {
        $miembros = TeamMember::where('team_id', $team->id)
            ->with('reclutado')
            ->get();

        $actuales = ObjetosEquipo::desde([]);

        foreach ($miembros as $member) {
            $reclutado = $member->reclutado;
            abort_unless($reclutado?->user_id === $userId, 404);

            if ($items->tieneSlot($member->slot)) {
                $itemKey = $items->porSlot($member->slot);

                $reclutado->update([
                    'obj_equipados' => $itemKey !== '' ? [$itemKey] : [],
                ]);
            }

            $objEquipados = $reclutado->obj_equipados;
            $itemActual = is_array($objEquipados) ? ($objEquipados[0] ?? '') : '';

            $actuales = $actuales->conSlot(
                $member->slot,
                is_string($itemActual) ? $itemActual : '',
            );
        }

        return $actuales;
    }
}
