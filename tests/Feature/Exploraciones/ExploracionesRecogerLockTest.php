<?php

declare(strict_types=1);

namespace Tests\Feature\Exploraciones;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExploracionesRecogerLockTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create(['experiencia' => 0]);
        $this->actingAs($this->usuario);
    }

    #[Test]
    public function con_el_lock_ocupado_responde_409_y_no_procesa(): void
    {
        $exploracion = $this->crearExploracionIndefinida();

        $lock = Cache::lock('exploracion:procesar:'.$exploracion->id, 60);
        $this->assertTrue($lock->get());

        try {
            $this->postJson("/exploraciones/{$exploracion->id}/recoger")
                ->assertStatus(409)
                ->assertJson([
                    'message' => 'La exploración se está procesando; recarga en unos segundos.',
                ]);

            $this->assertNull($exploracion->refresh()->regreso);
            $this->assertSame(0, $this->usuario->refresh()->experiencia);
        } finally {
            $lock->release();
        }
    }

    #[Test]
    public function con_el_lock_ocupado_el_redirect_muestra_error_y_no_procesa(): void
    {
        $exploracion = $this->crearExploracionIndefinida();

        $lock = Cache::lock('exploracion:procesar:'.$exploracion->id, 60);
        $this->assertTrue($lock->get());

        try {
            $this->post("/exploraciones/{$exploracion->id}/recoger")
                ->assertRedirect()
                ->assertSessionHas('error', 'La exploración se está procesando; recarga en unos segundos.');

            $this->assertNull($exploracion->refresh()->regreso);
        } finally {
            $lock->release();
        }
    }

    #[Test]
    public function con_el_lock_libre_finaliza_una_sola_vez(): void
    {
        $exploracion = $this->crearExploracionIndefinida();

        $this->postJson("/exploraciones/{$exploracion->id}/recoger")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $exploracion->refresh();
        $this->assertNotNull($exploracion->regreso);
        $this->assertSame(0, $this->usuario->refresh()->experiencia);
    }

    private function crearExploracionIndefinida(): ExploracionActiva
    {
        $province = Province::firstOrCreate(['name' => 'Kanto']);
        $habitat = Habitat::firstOrCreate(
            ['name' => 'Bosque'],
            ['province_id' => $province->id, 'peligro' => 1],
        );
        $pokemon = Pokemon::firstOrCreate(
            ['name' => 'pokemon-recoger'],
            [
                'species_id' => 1,
                'capture_rate' => 45,
                'base_experience' => 64,
                'height' => 7,
                'weight' => 69,
                'hatch' => null,
                'evolution_chain_id' => null,
            ],
        );
        $reclutado = Reclutado::create([
            'user_id' => $this->usuario->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Recluta',
            'exp' => ['total' => 0],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        return ExploracionActiva::create([
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => null,
            'hora_limite' => null,
            'indefinido' => true,
            'eventos' => ['bitacora' => []],
            'inicio_exploracion' => now()->subHours(3),
            'llegada_destino' => null,
            'regreso' => null,
        ]);
    }
}
