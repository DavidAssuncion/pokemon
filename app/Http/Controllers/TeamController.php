<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Src\Equipos\Domain\TeamAggregate;
use Src\Equipos\Domain\TeamRepositoryInterface;

class TeamController extends Controller
{
    public function __construct(
        private readonly TeamRepositoryInterface $teamRepository,
    ) {
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        if ($request->wantsJson()) {
            $team = Team::create(['name' => $data['name']]);

            return response()->json([
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'members' => [],
                ],
            ]);
        }

        // Use repository for persistence
        $this->teamRepository->guardar(new TeamAggregate(
            id: 0,
            name: $data['name'],
        ));

        return redirect()->back();
    }

    public function update(Request $request, Team $team): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $team->update(['name' => $data['name']]);

        if ($request->wantsJson()) {
            return response()->json([
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
            ]);
        }

        return redirect()->back();
    }

    public function destroy(Request $request, Team $team): RedirectResponse|JsonResponse
    {
        if ($team->isExploring()) {
            $error = 'No se puede borrar un equipo con exploraciones activas';

            if ($request->wantsJson()) {
                return response()->json(['error' => $error], 422);
            }

            return redirect()->back()->with('error', $error);
        }

        $this->teamRepository->eliminar($team->id);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }

    public function addMember(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'team_id' => 'required|exists:teams,id',
            'reclutado_id' => 'required|exists:reclutados,id',
            'slot' => 'required|integer|min:1|max:3',
            'behavior' => 'required|in:VANGUARDIA,COMBATIENTE,RECOLECTOR,SOPORTE',
        ]);

        $team = Team::find($data['team_id']);
        if ($team && $team->isExploring()) {
            $error = 'No se puede modificar un equipo con exploraciones activas';

            if ($request->wantsJson()) {
                return response()->json(['error' => $error], 422);
            }

            return redirect()->back()->with('error', $error);
        }

        // ensure slot not taken
        $exists = TeamMember::where('team_id', $data['team_id'])->where('slot', $data['slot'])->exists();
        if ($exists) {
            $error = 'Slot ya ocupado';

            if ($request->wantsJson()) {
                return response()->json(['error' => $error], 422);
            }

            return redirect()->back()->with('error', $error);
        }

        // ensure recultado not already in a team
        $already = TeamMember::where('pokemon_id', $data['reclutado_id'])->exists();
        if ($already) {
            $error = 'Pokémon ya está en un equipo';

            if ($request->wantsJson()) {
                return response()->json(['error' => $error], 422);
            }

            return redirect()->back()->with('error', $error);
        }

        $member = TeamMember::create([
            'team_id' => $data['team_id'],
            'pokemon_id' => $data['reclutado_id'],
            'slot' => $data['slot'],
            'behavior' => $data['behavior'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'member' => [
                    'id' => $member->id,
                    'team_id' => $member->team_id,
                    'pokemon_id' => $member->pokemon_id,
                    'slot' => $member->slot,
                ],
            ]);
        }

        return redirect()->back();
    }

    public function removeMember(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'member_id' => 'required|exists:team_members,id',
        ]);

        $member = TeamMember::find($data['member_id']);
        if ($member) {
            if ($member->team?->isExploring()) {
                $error = 'No se puede modificar un equipo con exploraciones activas';

                if ($request->wantsJson()) {
                    return response()->json(['error' => $error], 422);
                }

                return redirect()->back()->with('error', $error);
            }

            $member->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }
}
