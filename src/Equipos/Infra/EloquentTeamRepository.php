<?php

declare(strict_types=1);

namespace Src\Equipos\Infra;

use App\Models\Team;
use Src\Equipos\Domain\TeamAggregate;
use Src\Equipos\Domain\TeamRepositoryInterface;

class EloquentTeamRepository implements TeamRepositoryInterface
{
    public function obtenerTodos(): array
    {
        return Team::with('members.reclutado.pokemon')->get()->all();
    }

    public function obtenerPorId(int $id): ?TeamAggregate
    {
        $team = Team::with('members.reclutado.pokemon')->find($id);

        if ($team === null) {
            return null;
        }

        return $this->toDomain($team);
    }

    public function guardar(TeamAggregate $team): void
    {
        Team::updateOrCreate(
            ['id' => $team->id],
            ['name' => $team->name],
        );
    }

    public function eliminar(int $id): void
    {
        Team::destroy($id);
    }

    private function toDomain(Team $team): TeamAggregate
    {
        return new TeamAggregate(
            id: $team->id,
            name: $team->name,
            members: $team->members->all(),
        );
    }
}
