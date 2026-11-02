<?php

declare(strict_types=1);

namespace Src\Equipos\App;

use App\Models\Team;
use Src\Equipos\Domain\TeamRepositoryInterface;

/**
 * Elimina un equipo. La exploración es individual por reclutado, así que no
 * bloquea el borrado del equipo.
 */
final class EliminarEquipo
{
    public function __construct(
        private readonly TeamRepositoryInterface $teamRepository,
    ) {
    }

    public function ejecutar(Team $team): void
    {
        $this->teamRepository->eliminar($team->id);
    }
}
