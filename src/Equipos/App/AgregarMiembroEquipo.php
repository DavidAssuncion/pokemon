<?php

declare(strict_types=1);

namespace Src\Equipos\App;

use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Support\Facades\DB;

/**
 * Añade (o reemplaza) un miembro del equipo en un slot.
 *
 * Reglas de negocio:
 * - El slot 1-3 solo puede tener un miembro salvo reemplazo explícito.
 * - Un reclutado no puede estar en dos equipos.
 * - La exploración es individual: no bloquea la edición del equipo.
 */
final class AgregarMiembroEquipo
{
    public function ejecutar(
        Team $team,
        int $reclutadoId,
        int $slot,
        string $behavior,
        bool $replace,
    ): ResultadoMiembroEquipo {
        $ocupante = TeamMember::where('team_id', $team->id)
            ->where('slot', $slot)
            ->first();

        if ($ocupante !== null && ! $replace) {
            return ResultadoMiembroEquipo::rechazado('Slot ya ocupado');
        }

        $already = TeamMember::where('pokemon_id', $reclutadoId)->exists();
        if ($already) {
            return ResultadoMiembroEquipo::rechazado('Pokémon ya está en un equipo');
        }

        $member = DB::transaction(function () use ($team, $reclutadoId, $slot, $behavior, $ocupante, $replace): TeamMember {
            if ($ocupante !== null && $replace) {
                $ocupante->delete();
            }

            return TeamMember::create([
                'team_id' => $team->id,
                'pokemon_id' => $reclutadoId,
                'slot' => $slot,
                'behavior' => $behavior,
            ]);
        });

        return ResultadoMiembroEquipo::creado($member, $ocupante?->id);
    }
}
