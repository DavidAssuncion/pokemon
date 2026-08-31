<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExploracionesPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->actingAs($this->usuario);
    }

    /**
     * @return array{team: Team, habitat: Habitat}
     */
    private function crearContexto(): array
    {
        $province = Province::create(['name' => 'Kanto']);
        $habitat = Habitat::create(['name' => 'Bosque', 'province_id' => $province->id, 'peligro' => 2]);
        $team = Team::create(['name' => 'Equipo', 'user_id' => $this->usuario->id]);

        $pokemon = Pokemon::create([
            'name' => 'charmander',
            'species_id' => 4,
            'capture_rate' => 45,
            'base_experience' => 62,
            'height' => 6,
            'weight' => 85,
            'hatch' => 10,
            'evolution_chain_id' => 51,
        ]);
        PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => 1, 'base_stat' => 39, 'effort' => 0]);
        PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => 2, 'base_stat' => 52, 'effort' => 0]);
        PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => 3, 'base_stat' => 43, 'effort' => 0]);
        PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => 4, 'base_stat' => 60, 'effort' => 0]);
        PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => 5, 'base_stat' => 50, 'effort' => 0]);
        PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => 6, 'base_stat' => 65, 'effort' => 0]);
        PokemonType::create(['pokemon_id' => $pokemon->id, 'type' => 10, 'slot' => 1]); // Fuego

        // Pool del hábitat a nivel 1: planta (donde Fuego es súper-eficaz).
        $planta = Pokemon::create([
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'hatch' => 10,
            'evolution_chain_id' => 51,
        ]);
        PokemonType::create(['pokemon_id' => $planta->id, 'type' => 12, 'slot' => 1]); // Planta
        $habitat->pokemon()->attach($planta->id, ['level' => 1]);

        $reclutado = Reclutado::create([
            'user_id' => $this->usuario->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Char',
            'exp' => ['total' => 0],
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        return ['team' => $team, 'habitat' => $habitat];
    }

    public function test_preview_devuelve_el_contrato_completo(): void
    {
        $ctx = $this->crearContexto();

        $response = $this->getJson('/exploraciones/preview?'.http_build_query([
            'team_id' => $ctx['team']->id,
            'habitat_id' => $ctx['habitat']->id,
            'level' => 1,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'peligro_estrellas',
                'afinidad',
                'advertencias',
                'roles',
                'matchups' => [
                    [
                        'miembro_tipos',
                        'pool_tipo',
                        'defensa',
                        'ataque',
                        'clasificacion',
                    ],
                ],
                'riesgo',
                'recompensa_esperada',
            ])
            ->assertJson([
                'peligro_estrellas' => 2,
                'roles' => ['VANGUARDIA'],
            ]);

        $riesgo = $response->json('riesgo');
        $this->assertContains($riesgo, ['Bajo', 'Medio', 'Alto', 'Extremo']);
        // Fuego vs Planta: súper-eficaz y capacidad suficiente → bien preparado.
        $this->assertContains('Equipo bien preparado para esta zona', $response->json('advertencias'));
        // Semáforo de tipos: Fuego (miembro) contra Planta (pool) → positivo.
        $this->assertCount(1, $response->json('matchups'));
        $this->assertSame('Fuego', $response->json('matchups.0.miembro_tipos.0'));
        $this->assertSame('Planta', $response->json('matchups.0.pool_tipo'));
        $this->assertSame('positivo', $response->json('matchups.0.clasificacion'));
    }

    public function test_preview_rechaza_equipo_ajeno_anti_idor(): void
    {
        $ctx = $this->crearContexto();
        $otroUsuario = User::factory()->create();
        $equipoAjeno = Team::create(['name' => 'Ajeno', 'user_id' => $otroUsuario->id]);

        $this->getJson('/exploraciones/preview?'.http_build_query([
            'team_id' => $equipoAjeno->id,
            'habitat_id' => $ctx['habitat']->id,
            'level' => 1,
        ]))->assertUnprocessable();
    }

    public function test_preview_advierte_de_tipo_debil(): void
    {
        $ctx = $this->crearContexto();

        // Miembro de tipo Veneno contra pool Planta: Veneno→Planta = 2.0 (súper-eficaz
        // en ataque) pero defensa = Planta→Veneno = 1.0 → desempate ofensivo con
        // ataque > 1.0 → POSITIVO. Cambiamos a un miembro que SÍ genera matchup crítico:
        // Agua contra pool Planta: defensa = Planta→Agua = 2.0 → NEGATIVO.
        $pokemon = Pokemon::create([
            'name' => 'squirtle',
            'species_id' => 7,
            'capture_rate' => 45,
            'base_experience' => 63,
            'height' => 5,
            'weight' => 90,
            'hatch' => 10,
            'evolution_chain_id' => 51,
        ]);
        PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => 1, 'base_stat' => 44, 'effort' => 0]);
        PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => 2, 'base_stat' => 48, 'effort' => 0]);
        PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => 3, 'base_stat' => 65, 'effort' => 0]);
        PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => 4, 'base_stat' => 50, 'effort' => 0]);
        PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => 5, 'base_stat' => 64, 'effort' => 0]);
        PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => 6, 'base_stat' => 43, 'effort' => 0]);
        PokemonType::create(['pokemon_id' => $pokemon->id, 'type' => 11, 'slot' => 1]); // Agua
        $reclutado = Reclutado::create([
            'user_id' => $this->usuario->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Squi',
            'exp' => ['total' => 0],
        ]);
        TeamMember::create([
            'team_id' => $ctx['team']->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 2,
            'behavior' => 'COMBATIENTE',
        ]);

        $response = $this->getJson('/exploraciones/preview?'.http_build_query([
            'team_id' => $ctx['team']->id,
            'habitat_id' => $ctx['habitat']->id,
            'level' => 1,
        ]));

        $response->assertOk()->assertJson(['riesgo' => 'Extremo']);
        // El semáforo vive en matchups: el matchup Agua (miembro) vs Planta (pool)
        // es negativo; los textos de tipo ya NO van en advertencias.
        $matchups = collect($response->json('matchups'))
            ->firstWhere('miembro_tipos', ['Agua']);
        $this->assertSame('negativo', $matchups['clasificacion']);
        foreach ($response->json('advertencias') as $advertencia) {
            $this->assertStringNotContainsString('Pokémon de tipo', $advertencia);
        }
    }

    public function test_preview_valida_parametros(): void
    {
        $ctx = $this->crearContexto();

        $this->getJson('/exploraciones/preview?team_id=&habitat_id=&level=9')
            ->assertUnprocessable();

        $this->getJson('/exploraciones/preview')
            ->assertUnprocessable();
    }
}
