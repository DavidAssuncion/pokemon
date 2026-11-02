<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Src\Exploraciones\App\ProcesarExploracionCommand;
use Src\Exploraciones\App\ProcesarExploracionesPendientes;
use Src\Shared\Bus\Command;
use Src\Shared\Bus\CommandBus;
use Tests\TestCase;

/**
 * Doble de CommandBus: registra los comandos despachados y permite inyectar
 * comportamiento (lanzar, simular latencia) por comando.
 */
final class BusExploracionesFalso implements CommandBus
{
    /** @var list<Command> */
    public array $despachados = [];

    /** @param null|callable(Command):void $alDespachar */
    public function __construct(private readonly mixed $alDespachar = null)
    {
    }

    public function dispatch(Command $command): mixed
    {
        $this->despachados[] = $command;

        if (is_callable($this->alDespachar)) {
            ($this->alDespachar)($command);
        }

        return null;
    }
}

class ProcesarExploracionesPendientesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function solo_procesa_las_exploraciones_del_usuario_indicado(): void
    {
        $usuario = User::factory()->create();
        $otro = User::factory()->create();

        $propia = $this->crearExploracion($usuario);
        $ajena = $this->crearExploracion($otro);

        $bus = new BusExploracionesFalso();
        $resultado = (new ProcesarExploracionesPendientes($bus))->paraUsuario($usuario->id);

        $this->assertSame(1, $resultado->procesadas);
        $this->assertSame(0, $resultado->omitidas);
        $this->assertSame(0, $resultado->fallidas);
        $this->assertCount(1, $bus->despachados);
        $this->assertInstanceOf(ProcesarExploracionCommand::class, $bus->despachados[0]);
        $this->assertSame($propia->id, $bus->despachados[0]->exploracion->id);
        $this->assertNotSame($ajena->id, $bus->despachados[0]->exploracion->id);
    }

    #[Test]
    public function respeta_el_limite_de_exploraciones(): void
    {
        $usuario = User::factory()->create();
        $this->crearExploracion($usuario, ['inicio_exploracion' => now()->subHours(3)]);
        $this->crearExploracion($usuario, ['inicio_exploracion' => now()->subHours(2)]);
        $this->crearExploracion($usuario, ['inicio_exploracion' => now()->subHour()]);

        $bus = new BusExploracionesFalso();
        $resultado = (new ProcesarExploracionesPendientes($bus))->paraUsuario($usuario->id, limite: 2);

        $this->assertSame(2, $resultado->procesadas);
        $this->assertCount(2, $bus->despachados);
    }

    #[Test]
    public function omite_exploraciones_con_ultimo_procesado_reciente(): void
    {
        $usuario = User::factory()->create();
        $reciente = $this->crearExploracion($usuario, [
            'eventos' => ['ultimo_procesado' => now()->toIso8601String()],
        ]);
        $antigua = $this->crearExploracion($usuario, [
            'eventos' => ['ultimo_procesado' => now()->subMinutes(5)->toIso8601String()],
        ]);

        $bus = new BusExploracionesFalso();
        $resultado = (new ProcesarExploracionesPendientes($bus))->paraUsuario($usuario->id);

        $this->assertSame(1, $resultado->procesadas);
        $this->assertSame(1, $resultado->omitidas);
        $this->assertCount(1, $bus->despachados);
        $this->assertSame($antigua->id, $bus->despachados[0]->exploracion->id);
        $this->assertNotSame($reciente->id, $bus->despachados[0]->exploracion->id);
    }

    #[Test]
    public function un_ultimo_procesado_corrupto_no_bloquea_el_procesamiento(): void
    {
        $usuario = User::factory()->create();
        $exploracion = $this->crearExploracion($usuario, [
            'eventos' => ['ultimo_procesado' => 'no-es-una-fecha'],
        ]);

        $bus = new BusExploracionesFalso();
        $resultado = (new ProcesarExploracionesPendientes($bus))->paraUsuario($usuario->id);

        $this->assertSame(1, $resultado->procesadas);
        $this->assertSame($exploracion->id, $bus->despachados[0]->exploracion->id);
    }

    #[Test]
    public function aisla_los_fallos_por_exploracion_y_continua(): void
    {
        $usuario = User::factory()->create();
        $fallida = $this->crearExploracion($usuario, ['inicio_exploracion' => now()->subHours(2)]);
        $correcta = $this->crearExploracion($usuario, ['inicio_exploracion' => now()->subHour()]);

        $bus = new BusExploracionesFalso(function (Command $command) use ($fallida): void {
            if ($command instanceof ProcesarExploracionCommand && $command->exploracion->id === $fallida->id) {
                throw new RuntimeException('fallo simulado');
            }
        });

        $resultado = (new ProcesarExploracionesPendientes($bus))->paraUsuario($usuario->id);

        $this->assertSame(1, $resultado->procesadas);
        $this->assertSame(1, $resultado->fallidas);
        $this->assertCount(2, $bus->despachados);
        $this->assertSame($correcta->id, $bus->despachados[1]->exploracion->id);
    }

    #[Test]
    public function no_procesa_si_el_lock_esta_ocupado(): void
    {
        $usuario = User::factory()->create();
        $exploracion = $this->crearExploracion($usuario);

        $lock = Cache::lock('exploracion:procesar:'.$exploracion->id, 60);
        $this->assertTrue($lock->get());

        try {
            $bus = new BusExploracionesFalso();
            $resultado = (new ProcesarExploracionesPendientes($bus))->paraUsuario($usuario->id);

            $this->assertSame(0, $resultado->procesadas);
            $this->assertSame(1, $resultado->omitidas);
            $this->assertSame(0, $resultado->fallidas);
            $this->assertSame([], $bus->despachados);
        } finally {
            $lock->release();
        }
    }

    #[Test]
    public function una_con_forzar_regreso_salta_el_throttle(): void
    {
        $usuario = User::factory()->create();
        $exploracion = $this->crearExploracion($usuario, [
            'eventos' => ['ultimo_procesado' => now()->toIso8601String()],
        ]);

        $bus = new BusExploracionesFalso();
        $servicio = new ProcesarExploracionesPendientes($bus);

        $this->assertTrue($servicio->una($exploracion, forzarRegreso: true));
        $this->assertCount(1, $bus->despachados);
        $this->assertTrue($bus->despachados[0]->forzarRegreso);

        // Sin forzar y con throttle reciente: no se despacha.
        $this->assertFalse($servicio->una($exploracion));
        $this->assertCount(1, $bus->despachados);
    }

    #[Test]
    public function una_devuelve_false_si_el_despacho_falla(): void
    {
        $usuario = User::factory()->create();
        $exploracion = $this->crearExploracion($usuario);
        $bus = new BusExploracionesFalso(static function (): void {
            throw new RuntimeException('fallo simulado');
        });

        $this->assertFalse((new ProcesarExploracionesPendientes($bus))->una($exploracion, forzarRegreso: true));
        $this->assertCount(1, $bus->despachados);
    }

    #[Test]
    public function corta_por_presupuesto_y_marca_agotado_por_tiempo(): void
    {
        $usuario = User::factory()->create();
        $this->crearExploracion($usuario, ['inicio_exploracion' => now()->subHours(3)]);
        $this->crearExploracion($usuario, ['inicio_exploracion' => now()->subHours(2)]);
        $this->crearExploracion($usuario, ['inicio_exploracion' => now()->subHour()]);

        $bus = new BusExploracionesFalso(static function (): void {
            usleep(600_000);
        });

        $resultado = (new ProcesarExploracionesPendientes($bus))->paraUsuario(
            $usuario->id,
            limite: 10,
            presupuestoMs: 500,
        );

        $this->assertTrue($resultado->agotadoPorTiempo);
        $this->assertSame(1, $resultado->procesadas);
        $this->assertCount(1, $bus->despachados);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function crearExploracion(User $usuario, array $atributos = []): ExploracionActiva
    {
        $province = Province::firstOrCreate(['name' => 'Kanto-'.$usuario->id]);
        $habitat = Habitat::firstOrCreate(
            ['name' => 'Bosque-'.$usuario->id],
            ['province_id' => $province->id],
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

        return ExploracionActiva::create(array_merge([
            'user_id' => $usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 4,
            'hora_limite' => null,
            'indefinido' => false,
            'eventos' => null,
            'inicio_exploracion' => now()->subHour(),
            'llegada_destino' => null,
            'regreso' => null,
        ], $atributos));
    }
}
