<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TeamMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Src\Equipos\Domain\TeamRepositoryInterface;

class TeamController extends Controller
{
    public function __construct(
        private readonly TeamRepositoryInterface $teamRepository,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Use repository for persistence
        $this->teamRepository->guardar(new \Src\Equipos\Domain\TeamAggregate(
            id: 0,
            name: $data['name'],
        ));

        return redirect()->back();
    }

    public function update(Request $request, \App\Models\Team $team): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $team->update(['name' => $data['name']]);

        return redirect()->back();
    }

    public function destroy(\App\Models\Team $team): RedirectResponse
    {
        $this->teamRepository->eliminar($team->id);

        return redirect()->back();
    }

    public function addMember(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'team_id' => 'required|exists:teams,id',
            'reclutado_id' => 'required|exists:reclutados,id',
            'slot' => 'required|integer|min:1|max:3',
            'behavior' => 'required|in:VANGUARDIA,COMBATIENTE,RECOLECTOR,SOPORTE',
        ]);

        // ensure slot not taken
        $exists = TeamMember::where('team_id', $data['team_id'])->where('slot', $data['slot'])->exists();
        if ($exists) {
            return redirect()->back()->with('error', 'Slot ya ocupado');
        }

        // ensure recultado not already in a team
        $already = TeamMember::where('pokemon_id', $data['reclutado_id'])->exists();
        if ($already) {
            return redirect()->back()->with('error', 'Pokémon ya está en un equipo');
        }

        TeamMember::create([
            'team_id' => $data['team_id'],
            'pokemon_id' => $data['reclutado_id'],
            'slot' => $data['slot'],
            'behavior' => $data['behavior'],
        ]);

        return redirect()->back();
    }

    public function removeMember(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => 'required|exists:team_members,id',
        ]);

        $member = TeamMember::find($data['member_id']);
        if ($member) {
            $member->delete();
        }

        return redirect()->back();
    }
}
