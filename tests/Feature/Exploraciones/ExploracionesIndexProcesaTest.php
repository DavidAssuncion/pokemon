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
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Src\Shared\Bus\Command;
use Src\Shared\Bus\CommandBus;
use Tests\TestCase;

class ExploracionesIndexProcesaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->actingAs($this->usuario);
    }

    #[Test]
    public function una_expedicion_vencida_pasa_a_terminadas_en_la_misma_carga(): void
    {
        $exploracion = $this->crearExploracionVencida($this->usuario);

        $response = $this->get('/exploraciones');

        $response->assertOk();
        $this->assertSame([], $response->viewData('activas'));
        $this->assertCount(1, $response->viewData('terminadas'));
        $this->assertFalse($response->viewData('avisoProcesamiento'));

        $exploracion->refresh();
        $this->assertNotNull($exploracion->regreso);
        $this->assertNotNull($exploracion->eventos->get('resultado'));
    }

    #[Test]
    public function no_toca_las_exploraciones_de_otro_usuario(): void
    {
        $otro = User::factory()->create();
        $ajena = $this->crearExploracionVencida($otro);

        $this->get('/exploraciones')->assertOk();

        $this->assertNull($ajena->refresh()->regreso);
    }

    #[Test]
    public function un_fallo_del_handler_no_rompe_la_pagina_y_marca_aviso(): void
    {
        $this->app->instance(CommandBus::class, new class () implements CommandBus {
            public function dispatch(Command $command): mixed
            {
                throw new RuntimeException('fallo simulado del handler');
            }
        });

        $exploracion = $this->crearExploracionVencida($this->usuario);

        $response = $this->get('/exploraciones');

        $response->assertOk();
        $this->assertTrue($response->viewData('avisoProcesamiento'));
        $this->assertNull($exploracion->refresh()->regreso);
    }

    #[Test]
    public function sin_exploraciones_activas_no_despacha_nada(): void
    {
        $bus = new class () implements CommandBus {
            public int $despachos = 0;

            public function dispatch(Command $command): mixed
            {
                $this->despachos++;

                return null;
            }
        };
        $this->app->instance(CommandBus::class, $bus);

        $this->get('/exploraciones')->assertOk();

        $this->assertSame(0, $bus->despachos);
    }

    private function crearExploracionVencida(User $usuario): ExploracionActiva
    {
        $province = Province::firstOrCreate(['name' => 'Kanto-'.$usuario->id]);
        $habitat = Habitat::firstOrCreate(
            ['name' => 'Bosque-'.$usuario->id],
            ['province_id' => $province->id, 'peligro' => 1],
        );
        $pokemon = Pokemon::firstOrCreate(
            ['name' => 'pokemon-'.$usuario->id],
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
            'user_id' => $usuario->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Recluta-'.$usuario->id,
            'exp' => ['total' => 0],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        return ExploracionActiva::create([
            'user_id' => $usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 1,
            'hora_limite' => null,
            'indefinido' => false,
            'eventos' => ['bitacora' => []],
            'inicio_exploracion' => now()->subHours(2),
            'llegada_destino' => null,
            'regreso' => null,
        ]);
    }
}
