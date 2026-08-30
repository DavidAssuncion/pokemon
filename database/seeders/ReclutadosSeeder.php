<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ReclutadosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (DB::table('teams')->exists()) {
            return;
        }

        // El usuario demo se crea/obtiene aquí porque este seeder corre antes de
        // que DatabaseSeeder cree su propio usuario (reclutados/teams lo necesitan).
        $userId = $this->demoUserId();

        // Starter pokemon names (try common variants)
        $starterNames = [
            'Marill',
            'Slugma',
            'Paras',
            'Eevee',
            'Zigzagoon',
            'Starly',
        ];

        DB::transaction(function () use ($starterNames, $userId) {
            $reclutadosMap = [];

            foreach ($starterNames as $name) {
                $pokemon = DB::table('pokemon')
                    ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                    ->orWhere('name', 'LIKE', "%{$name}%")
                    ->first();

                if (! $pokemon) {
                    // Try variants (galar etc.)
                    $pokemon = DB::table('pokemon')
                        ->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($name).'%'])
                        ->first();
                }

                if (! $pokemon) {
                    Log::warning("Starter pokemon not found: {$name}");

                    continue;
                }

                $reclutadoId = DB::table('reclutados')->insertGetId([
                    'user_id' => $userId,
                    'nombre' => $pokemon->name,
                    'pokemon_id' => $pokemon->id,
                    'exp' => json_encode(new \stdClass()),
                    'es_shiny' => false,
                    'obj_equipados' => json_encode(new \stdClass()),
                    'movimientos' => json_encode([]),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                $reclutadosMap[$pokemon->name] = $reclutadoId;
            }

            // Create two default teams
            // Team 1: Marill, Slugma, Paras
            $team1Id = DB::table('teams')->insertGetId([
                'user_id' => $userId,
                'name' => 'Equipo A',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $this->insertTeamMemberIfExists($reclutadosMap, 'Marill', $team1Id, 1, 'VANGUARDIA');
            $this->insertTeamMemberIfExists($reclutadosMap, 'Slugma', $team1Id, 2, 'COMBATIENTE');
            $this->insertTeamMemberIfExists($reclutadosMap, 'Paras', $team1Id, 3, 'RECOLECTOR');

            // Team 2: Eevee, Zigzagoon (Galar), Starly
            $team2Id = DB::table('teams')->insertGetId([
                'user_id' => $userId,
                'name' => 'Equipo B',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $this->insertTeamMemberIfExists($reclutadosMap, 'Eevee', $team2Id, 1, 'COMBATIENTE');
            // Zigzagoon may be stored as Zigzagoon-Galar or similar; try Zigzagoon key
            $this->insertTeamMemberIfExists($reclutadosMap, 'Zigzagoon', $team2Id, 2, 'RECOLECTOR');
            $this->insertTeamMemberIfExists($reclutadosMap, 'Starly', $team2Id, 3, 'RASTREADOR');
        });
    }

    /**
     * Id del usuario demo (creado si no existe).
     */
    private function demoUserId(): int
    {
        $user = DB::table('users')->where('name', 'demo')->first();

        if ($user !== null) {
            return (int) $user->id;
        }

        return (int) DB::table('users')->insertGetId([
            'name' => 'demo',
            'email' => 'demo@example.com',
            'password' => Hash::make('password'),
            'experiencia' => 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function insertTeamMemberIfExists(array $map, string $name, int $teamId, int $slot, string $behavior): void
    {
        if (! isset($map[$name])) {
            // Try lowercase keys
            foreach ($map as $k => $id) {
                if (strtolower($k) === strtolower($name) || stripos($k, $name) !== false) {
                    $map[$name] = $id;
                    break;
                }
            }
        }

        if (! isset($map[$name])) {
            // nothing to insert
            return;
        }

        DB::table('team_members')->insert([
            'team_id' => $teamId,
            'pokemon_id' => $map[$name],
            'slot' => $slot,
            'behavior' => $behavior,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
