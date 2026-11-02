<?php

declare(strict_types=1);

namespace Src\Equipos\App;

use App\Models\TeamMember;

/**
 * Actualiza el behavior (rol de exploración) de un miembro.
 * La autorización de propiedad (anti-IDOR) queda en el controlador.
 * La exploración es individual por reclutado: el equipo no se bloquea.
 */
final class ActualizarRolMiembro
{
    public function ejecutar(TeamMember $member, string $behavior): void
    {
        $member->update(['behavior' => $behavior]);
    }
}
