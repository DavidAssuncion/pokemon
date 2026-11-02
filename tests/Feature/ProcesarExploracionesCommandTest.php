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
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcesarExploracionesCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_consola_procesa_exploraciones_de_todos_los_usuarios(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();

        $exploracionA = $this->crearExploracionVencida($usuarioA);
        $exploracionB = $this->crearExploracionVencida($usuarioB);

        $this->artisan('exploraciones:procesar')
            ->expectsOutputToContain('procesadas: 2')
            ->assertSuccessful();

        $this->assertNotNull($exploracionA->refresh()->regreso);
        $this->assertNotNull($exploracionB->refresh()->regreso);
    }

    #[Test]
    public function la_consola_comparte_el_lock_por_exploracion_con_la_pagina(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();

        $bloqueada = $this->crearExploracionVencida($usuarioA);
        $libre = $this->crearExploracionVencida($usuarioB);

        $lock = Cache::lock('exploracion:procesar:'.$bloqueada->id, 60);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('exploraciones:procesar')
                ->expectsOutputToContain('omitidas: 1')
                ->assertSuccessful();

            $this->assertNull($bloqueada->refresh()->regreso);
            $this->assertNotNull($libre->refresh()->regreso);
        } finally {
            $lock->release();
        }

        // Al liberarse el lock, el siguiente tick procesa la pendiente.
        $this->artisan('exploraciones:procesar')
            ->expectsOutputToContain('procesadas: 1')
            ->assertSuccessful();

        $this->assertNotNull($bloqueada->refresh()->regreso);
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
