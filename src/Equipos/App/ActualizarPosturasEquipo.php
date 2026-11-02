<?php

declare(strict_types=1);

namespace Src\Equipos\App;

use App\Models\Team;

/**
 * Persiste la postura por slot (atacar/guardia/cubrir) del equipo.
 *
 * La exploración es individual por reclutado: el equipo sigue siendo editable
 * aunque alguno de sus miembros esté explorando.
 */
final class ActualizarPosturasEquipo
{
    public function ejecutar(Team $team, ?array $posturas): void
    {
        $team->update(['posturas' => $posturas]);
    }
}
