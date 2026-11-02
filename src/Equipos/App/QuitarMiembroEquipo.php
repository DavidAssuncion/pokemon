<?php

declare(strict_types=1);

namespace Src\Equipos\App;

use App\Models\TeamMember;

/**
 * Quita un miembro del equipo. La autorización de propiedad (anti-IDOR)
 * queda en el controlador.
 * La exploración es individual por reclutado: no bloquea la edición.
 */
final class QuitarMiembroEquipo
{
    public function ejecutar(TeamMember $member): void
    {
        $member->delete();
    }
}
