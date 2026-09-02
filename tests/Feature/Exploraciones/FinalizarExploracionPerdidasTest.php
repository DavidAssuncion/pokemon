<?php

declare(strict_types=1);

namespace Tests\Feature\Exploraciones;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Src\Exploraciones\App\FinalizarExploracionCommand;
use Src\Exploraciones\App\FinalizarExploracionHandler;
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Presentation\TransformadorResultadoExploracion;
use Src\Shared\Bus\UnitOfWork;
use Tests\TestCase;

class FinalizarExploracionPerdidasTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create(['experiencia' => 10 * 10 ** 3]); // nivel 10
    }

    private function crearContexto(array $opciones = []): array
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => $province->id, 'peligro' => 1]);

        $salvaje = Pokemon::create([
            'id' => 1,
            'name' => 'rattata',
            'species_id' => 1,
            'capture_rate' => 255,
            'base_experience' => 51,
            'height' => 3,
            'weight' => 35,
            'evolution_chain_id' => 51,
        ]);
        $this->crearStats($salvaje, ['hp' => 30, 'atk' => 30, 'def' => 25, 'spAtk' => 25, 'spDef' => 25, 'speed' => 40]);
        $this->crearTipo($salvaje, TipoEnum::NORMAL);
        DB::table('pokemon_habitat')->insert(['pokemon_id' => $salvaje->id, 'habitat_id' => $habitat->id, 'level' => 1]);

        $pokemon = Pokemon::create([
            'id' => 2,
            'name' => 'mewtwo',
            'species_id' => 2,
            'capture_rate' => 3,
            'base_experience' => 340,
            'height' => 20,
            'weight' => 1220,
            'evolution_chain_id' => 52,
        ]);
        $this->crearStats($pokemon, $opciones['explorador_stats'] ?? ['hp' => 200, 'atk' => 180, 'def' => 150, 'spAtk' => 180, 'spDef' => 150, 'speed' => 200]);
        $this->crearTipo($pokemon, TipoEnum::PSYCHIC);

        $reclutado = Reclutado::create([
            'user_id' => $this->usuario->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Mewtwo',
            'exp' => ['total' => 10 * 40 ** 3],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        $exploracion = ExploracionActiva::create([
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 24,
            'indefinido' => false,
            'inicio_exploracion' => now()->subHours(2),
        ]);

        return ['exploracion' => $exploracion, 'reclutado' => $reclutado];
    }

    private function crearStats(Pokemon $pokemon, array $stats): void
    {
        $mapa = [
            'hp' => StatEnum::HP,
            'atk' => StatEnum::ATTACK,
            'def' => StatEnum::DEFENSE,
            'spAtk' => StatEnum::SPECIAL_ATTACK,
            'spDef' => StatEnum::SPECIAL_DEFENSE,
            'speed' => StatEnum::SPEED,
        ];
        foreach ($mapa as $clave => $stat) {
            PokemonStat::create([
                'pokemon_id' => $pokemon->id,
                'stat' => $stat,
                'base_stat' => $stats[$clave],
                'effort' => $clave === 'hp' ? 2 : 1,
            ]);
        }
    }

    private function crearTipo(Pokemon $pokemon, TipoEnum $tipo): void
    {
        PokemonType::create(['pokemon_id' => $pokemon->id, 'type' => $tipo, 'slot' => 1]);
    }

    private function handler(): FinalizarExploracionHandler
    {
        return new FinalizarExploracionHandler(
            app(UnitOfWork::class),
            app(CalculadorRecompensas::class),
            app(PersistirRecompensas::class),
            app(TransformadorResultadoExploracion::class),
            fn (): float => 1.0, // aleatorio alto → todas las capturas fallan
        );
    }

    /**
     * Crea una exploración con bitácora de victoria (2 rattatas vencidos) o
     * derrota (1 rattata vencido + derrota) y eventos de hallazgo para que
     * haya caramelos familia/EV/tipo.
     */
    private function exploracionConBitacora(bool $conDerrota): ExploracionActiva
    {
        $ctx = $this->crearContexto();

        $bitacora = [
            [
                'tipo' => 'encuentro',
                'subtype' => 'normal',
                'pokemon_id' => 1,
                'timestamp' => '2026-09-01T10:00:00Z',
                'resolucion' => 'victoria',
                'victoria' => true,
            ],
            [
                'tipo' => 'hallazgo',
                'subtype' => 'caramelo_familia',
                'pokemon_id' => 1,
                'cantidad' => 5,
                'timestamp' => '2026-09-01T10:05:00Z',
            ],
            [
                'tipo' => 'hallazgo',
                'subtype' => 'caramelo_ev',
                'stat' => 6,
                'cantidad' => 4,
                'timestamp' => '2026-09-01T10:10:00Z',
            ],
            [
                'tipo' => 'hallazgo',
                'subtype' => 'caramelo_tipo',
                'tipo_id' => 1,
                'cantidad' => 6,
                'timestamp' => '2026-09-01T10:15:00Z',
            ],
        ];

        if ($conDerrota) {
            $bitacora[] = [
                'tipo' => 'encuentro',
                'subtype' => 'normal',
                'pokemon_id' => 1,
                'timestamp' => '2026-09-01T10:20:00Z',
                'resolucion' => 'derrota',
                'victoria' => false,
            ];
        }

        $eventos = [
            'bitacora' => $bitacora,
            'ultimo_procesado' => now()->toIso8601String(),
        ];

        if ($conDerrota) {
            $eventos['derrota'] = ['reason' => 'explorador_debilitado', 'timestamp' => now()->toIso8601String()];
        }

        $ctx['exploracion']->eventos = $eventos;
        $ctx['exploracion']->save();

        return $ctx['exploracion'];
    }

    #[Test]
    public function test_sin_derrota_no_hay_objetos_perdidos(): void
    {
        $exploracion = $this->exploracionConBitacora(false);

        $this->handler()->handle(new FinalizarExploracionCommand($exploracion));

        $exploracion->refresh();
        $this->assertNotNull($exploracion->regreso);
        $this->assertNull($exploracion->eventos->get('objetos_perdidos'), 'Sin derrota no debe haber pérdidas');
    }

    #[Test]
    public function test_con_derrota_se_pierde_ceil_mitad_de_cada_recompensa(): void
    {
        $exploracion = $this->exploracionConBitacora(true);

        $this->handler()->handle(new FinalizarExploracionCommand($exploracion));

        $exploracion->refresh();
        $resultado = $exploracion->eventos->get('resultado');
        $perdidas = $exploracion->eventos->get('objetos_perdidos');

        $this->assertNotNull($perdidas, 'Debe haber objetos_perdidos');
        $this->assertNotEmpty($perdidas);

        // Categoría exito_parcial (1 victoria de 2 combates, ratio 0.5) → multiplicador 0.7.
        // Familia: hallazgo floor(5*0.7)=3 + derrota floor(1*0.7)=0 → 3 → pierde ceil(3/2)=2 → 1
        // EV: hallazgo floor(4*0.7)=2 + derrota floor(1*0.7)=0 → 2 → pierde ceil(2/2)=1 → 1
        // Tipo: hallazgo floor(6*0.7)=4 + derrota 0 → 4 → pierde ceil(4/2)=2 → 2
        $familia = collect($resultado['caramelos_familia'])->first();
        $this->assertSame(1, $familia['cantidad'], 'Familia 3 - 2 = 1');
        $ev = collect($resultado['caramelos_ev'])->first();
        $this->assertSame(1, $ev['cantidad'], 'EV 2 - 1 = 1');
        $tipo = collect($resultado['caramelos_tipo'])->first();
        $this->assertSame(2, $tipo['cantidad'], 'Tipo 4 - 2 = 2');

        // Las pérdidas están registradas con su cantidad
        $familiaPerdida = collect($perdidas)->first(fn (array $p) => $p['tipo'] === 'familia');
        $this->assertSame(2, $familiaPerdida['cantidad_perdida']);
        $evPerdida = collect($perdidas)->first(fn (array $p) => $p['tipo'] === 'ev');
        $this->assertSame(1, $evPerdida['cantidad_perdida']);
        $tipoPerdida = collect($perdidas)->first(fn (array $p) => $p['tipo'] === 'tipo');
        $this->assertSame(2, $tipoPerdida['cantidad_perdida']);
    }

    #[Test]
    public function test_con_derrota_la_vista_puede_leer_perdidas_desde_resultado(): void
    {
        $exploracion = $this->exploracionConBitacora(true);

        $this->handler()->handle(new FinalizarExploracionCommand($exploracion));

        $exploracion->refresh();
        $eventos = $exploracion->eventos;

        // Contrato aditivo: el resultado incluye la información de pérdidas
        $this->assertNotNull($eventos->get('objetos_perdidos'), 'objetos_perdidos accesible desde eventos');
    }
}
