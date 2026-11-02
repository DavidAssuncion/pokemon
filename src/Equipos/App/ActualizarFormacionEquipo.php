<?php

declare(strict_types=1);

namespace Src\Equipos\App;

use App\Models\Team;

/**
 * Persiste la formación por slot (vanguardia/retaguardia) del equipo.
 *
 * La exploración es individual por reclutado: el equipo sigue siendo editable
 * aunque alguno de sus miembros esté explorando.
 */
final class ActualizarFormacionEquipo
{
    public function ejecutar(Team $team, ?array $formacion): void
    {
        $team->update(['formacion' => $formacion]);
    }
}
