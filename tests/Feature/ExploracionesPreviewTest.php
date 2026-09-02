<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Province;
use App\Models\Reclutado;
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

        $this->usuario = User::factory()->create(['experiencia' => 10 * 10 ** 3]); // nivel 10
        $this->actingAs($this->usuario);
    }

    /**
     * @return array{reclutado: Reclutado, habitat: Habitat}
     */
    private function crearContexto(array $stats = []): array
    {
        $province = Province::create(['name' => 'Kanto']);
        $habitat = Habitat::create(['name' => 'Bosque', 'province_id' => $province->id, 'peligro' => 2]);

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
        $statsPorDefecto = ['hp' => 39, 'atk' => 52, 'def' => 43, 'spAtk' => 60, 'spDef' => 50, 'speed' => 65];
        $this->crearStats($pokemon, array_merge($statsPorDefecto, $stats));
        PokemonType::create(['pokemon_id' => $pokemon->id, 'type' => 10, 'slot' => 1]); // Fuego

        $reclutado = Reclutado::create([
            'user_id' => $this->usuario->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Char',
            'exp' => ['total' => 10 * 5 ** 3], // nivel 5
        ]);

        return ['reclutado' => $reclutado, 'habitat' => $habitat];
    }

    private function crearStats(Pokemon $pokemon, array $stats): void
    {
        $mapa = ['hp' => 1, 'atk' => 2, 'def' => 3, 'spAtk' => 4, 'spDef' => 5, 'speed' => 6];
        foreach ($mapa as $clave => $statId) {
            PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => $statId, 'base_stat' => $stats[$clave], 'effort' => 0]);
        }
    }

    public function test_preview_devuelve_contrato_individual(): void
    {
        $ctx = $this->crearContexto();

        $response = $this->getJson('/exploraciones/preview?'.http_build_query([
            'reclutado_id' => $ctx['reclutado']->id,
            'habitat_id' => $ctx['habitat']->id,
            'level' => 1,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'capacidades' => [
                    'combate',
                    'deteccion',
                    'recoleccion',
                    'supervivencia',
                    'exploracion',
                    'movilidad',
                ],
                'nivel_jugador',
                'nivel_pokemon',
                'min_lvl',
                'peligro',
                'riesgo',
            ]);

        $json = $response->json();
        $this->assertSame(10, $json['nivel_jugador']);
        $this->assertSame(5, $json['nivel_pokemon']);
        $this->assertSame(2, $json['peligro']);
        $this->assertNull($json['min_lvl']);
        $this->assertContains($json['riesgo'], ['Bajo', 'Medio', 'Alto']);
        // Charmander nivel 5 (stats 39/52/43/60/50/65) + jugador 10:
        // combate = 0.25*(52+60+43+50) + 15 = 51.25 + 15 = 66.25 → dificultad 30+10=40 → Bajo
        $this->assertSame('Bajo', $json['riesgo']);
        $this->assertSame(66.25, $json['capacidades']['combate']);
    }

    public function test_preview_rechaza_reclutado_ajeno_anti_idor(): void
    {
        $ctx = $this->crearContexto();
        $otroUsuario = User::factory()->create();
        $reclutadoAjeno = Reclutado::create([
            'user_id' => $otroUsuario->id,
            'pokemon_id' => $ctx['reclutado']->pokemon_id,
            'nombre' => 'Ajeno',
            'exp' => ['total' => 0],
        ]);

        $this->getJson('/exploraciones/preview?'.http_build_query([
            'reclutado_id' => $reclutadoAjeno->id,
            'habitat_id' => $ctx['habitat']->id,
            'level' => 1,
        ]))->assertUnprocessable();
    }

    public function test_preview_con_min_lvl_nulo_devuelve_null(): void
    {
        $ctx = $this->crearContexto();

        $response = $this->getJson('/exploraciones/preview?'.http_build_query([
            'reclutado_id' => $ctx['reclutado']->id,
            'habitat_id' => $ctx['habitat']->id,
            'level' => 1,
        ]));

        $response->assertOk()->assertJson(['min_lvl' => null]);
    }

    public function test_preview_valida_parametros(): void
    {
        $ctx = $this->crearContexto();

        $this->getJson('/exploraciones/preview?reclutado_id=&habitat_id=&level=9')
            ->assertUnprocessable();

        $this->getJson('/exploraciones/preview')
            ->assertUnprocessable();
    }
}
