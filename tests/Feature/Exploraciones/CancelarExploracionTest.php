<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CancelarExploracionTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create(['experiencia' => 10 * 10 ** 3]);
        $this->actingAs($this->usuario);
    }

    private function crearHabitat(int $id = 1, string $nombre = 'Habitat'): Habitat
    {
        $province = Province::firstOrCreate(['id' => 100 + $id], ['name' => 'Provincia-'.$id]);

        return Habitat::firstOrCreate(['id' => $id], [
            'name' => $nombre,
            'province_id' => $province->id,
        ]);
    }

    private function crearReclutado(): Reclutado
    {
        $pokemon = Pokemon::firstOrCreate(['id' => 1], [
            'name' => 'pokemon-1',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 51,
        ]);

        return Reclutado::firstOrCreate(
            ['pokemon_id' => $pokemon->id, 'user_id' => $this->usuario->id],
            [
                'nombre' => 'Reclutado',
                'exp' => ['total' => 10],
                'es_shiny' => false,
                'obj_equipados' => [],
                'movimientos' => [],
            ],
        );
    }

    private function crearExploracion(?Reclutado $reclutado = null, bool $cancelada = false): ExploracionActiva
    {
        $habitat = $this->crearHabitat();
        $eventos = $cancelada
            ? collect(['cancelada' => ['motivo' => 'manual', 'timestamp' => now()->toIso8601String()]])
            : collect();

        return ExploracionActiva::create([
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado?->id ?? $this->crearReclutado()->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 2,
            'indefinido' => false,
            'eventos' => $eventos,
            'regreso' => $cancelada ? now() : null,
        ]);
    }

    #[Test]
    public function test_cancelar_marca_regreso_y_evento_cancelada_con_reasignacion_de_regreso(): void
    {
        $exploracion = $this->crearExploracion();

        $response = $this->postJson("/exploraciones/{$exploracion->id}/cancelar");

        $response->assertOk();

        $refrescada = $exploracion->fresh();
        $this->assertNotNull($refrescada->regreso);
        $this->assertIsArray($refrescada->eventos->get('cancelada'));
        $this->assertSame('manual', $refrescada->eventos->get('cancelada')['motivo']);
        $this->assertArrayNotHasKey('resultado', $refrescada->eventos->toArray());
    }

    #[Test]
    public function test_cancelar_solo_permite_el_dueño(): void
    {
        $otro = User::factory()->create();
        $exploracion = $this->crearExploracion();

        $this->actingAs($otro)
            ->postJson("/exploraciones/{$exploracion->id}/cancelar")
            ->assertNotFound();
    }

    #[Test]
    public function test_cancelar_rechaza_si_ya_termino_por_otra_via(): void
    {
        $exploracion = $this->crearExploracion();
        $exploracion->update(['regreso' => now()]);

        $this->postJson("/exploraciones/{$exploracion->id}/cancelar")->assertStatus(422);
    }

    #[Test]
    public function test_cancelar_es_idempotente(): void
    {
        $exploracion = $this->crearExploracion(cancelada: true);

        $this->postJson("/exploraciones/{$exploracion->id}/cancelar")->assertOk();
        $this->postJson("/exploraciones/{$exploracion->id}/cancelar")->assertOk();
    }

    #[Test]
    public function test_index_excluye_canceladas_de_activas_y_resultados(): void
    {
        $activa = $this->crearExploracionConHabitat('Habitat-Activa');
        $cancelada = $this->crearExploracionConHabitat('Habitat-Cancelada', cancelada: true);
        $terminada = ExploracionActiva::create([
            'user_id' => $this->usuario->id,
            'reclutado_id' => $this->crearReclutado()->id,
            'habitat_id' => $this->crearHabitat(3, 'Habitat-Terminada')->id,
            'nivel' => 1,
            'duracion_horas' => 2,
            'indefinido' => false,
            'eventos' => collect(['resultado' => ['exp' => 5]]),
            'regreso' => now(),
        ]);

        $response = $this->get('/exploraciones');

        $response->assertStatus(200);
        $response->assertSee('Habitat-Activa');
        $response->assertSee('Habitat-Terminada');
        $response->assertDontSee('Habitat-Cancelada');
    }

    private function crearExploracionConHabitat(string $habitatNombre, bool $cancelada = false): ExploracionActiva
    {
        $reclutado = $this->crearReclutado();
        $habitat = $this->crearHabitat(9, $habitatNombre);

        $eventos = $cancelada
            ? collect(['cancelada' => ['motivo' => 'manual', 'timestamp' => now()->toIso8601String()]])
            : collect();

        return ExploracionActiva::create([
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 2,
            'indefinido' => false,
            'eventos' => $eventos,
            'regreso' => $cancelada ? now() : null,
        ]);
    }
}
