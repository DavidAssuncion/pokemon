<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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

        $team = Team::create(['name' => $data['name'], 'user_id' => Auth::id()]);

        if ($request->wantsJson()) {
            return response()->json([
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'members' => [],
                ],
            ]);
        }

        return redirect()->back();
    }

    public function update(Request $request, Team $team): RedirectResponse|JsonResponse
    {
        // El route-model-binding + global scope de Team ya devuelve 404 para equipos ajenos.
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
            // El reclutado debe ser del usuario autenticado (anti-IDOR).
            'reclutado_id' => ['required', Rule::exists('reclutados', 'id')->where('user_id', Auth::id())],
            'slot' => 'required|integer|min:1|max:3',
            'behavior' => 'required|in:VANGUARDIA,COMBATIENTE,RECOLECTOR,RASTREADOR',
        ]);

        // El global scope de Team hace que un equipo ajeno resuelva a null:
        // findOrFail devuelve 404 (misma semántica que update/destroy).
        $team = Team::findOrFail($data['team_id']);

        if ($team->isExploring()) {
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

        $member = TeamMember::findOrFail($data['member_id']);

        // El belongsTo Team hereda el global scope: para un miembro de un equipo ajeno
        // la relación resuelve null y abort(404) (anti-IDOR, mismo status que update/destroy).
        abort_unless($member->team?->user_id === Auth::id(), 404);

        if ($member->team?->isExploring()) {
            $error = 'No se puede modificar un equipo con exploraciones activas';

            if ($request->wantsJson()) {
                return response()->json(['error' => $error], 422);
            }

            return redirect()->back()->with('error', $error);
        }

        $member->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }
}
