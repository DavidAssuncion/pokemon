<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $team = Team::create(['name' => $data['name']]);

        return redirect()->back();
    }

    public function destroy(Team $team)
    {
        $team->delete();
        return redirect()->back();
    }

    public function addMember(Request $request)
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

    public function removeMember(Request $request)
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
