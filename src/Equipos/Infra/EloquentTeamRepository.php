<?php

declare(strict_types=1);

namespace Src\Equipos\Infra;

use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Support\Facades\DB;
use Src\Equipos\Domain\TeamAggregate;
use Src\Equipos\Domain\TeamRepositoryInterface;

class EloquentTeamRepository implements TeamRepositoryInterface
{
    public function obtenerTodos(): array
    {
        return Team::with('members.reclutado.pokemon')
            ->get()
            ->map(fn (Team $team): TeamAggregate => $this->toDomain($team))
            ->all();
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
        DB::transaction(function () use ($team): void {
            $eloquent = Team::updateOrCreate(
                ['id' => $team->id],
                ['name' => $team->name, 'user_id' => $team->userId],
            );

            $this->sincronizarMiembros($eloquent, $team->members);
        });
    }

    /**
     * Upsert de los miembros del agregado y borrado de los que ya no están.
     *
     * @param  array<int, \App\Models\TeamMember>  $members
     */
    private function sincronizarMiembros(Team $team, array $members): void
    {
        foreach ($members as $miembro) {
            TeamMember::updateOrCreate(
                ['team_id' => $team->id, 'pokemon_id' => $miembro->pokemon_id],
                ['slot' => $miembro->slot, 'behavior' => $miembro->behavior],
            );
        }

        $idsMantener = array_map(fn (TeamMember $miembro): int => $miembro->pokemon_id, $members);

        if ($idsMantener === []) {
            $team->members()->delete();
        } else {
            $team->members()->whereNotIn('pokemon_id', $idsMantener)->delete();
        }
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
            userId: $team->user_id,
            members: $team->members->all(),
        );
    }
}
