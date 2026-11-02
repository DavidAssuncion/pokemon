<?php

declare(strict_types=1);

namespace Tests\Feature\Battle;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TipoPokemon;
use Tests\Support\Battle\CatalogoEscenarios;
use Tests\Support\Battle\ConstructorBatallaHarness;
use Tests\Support\Battle\EjecutorBatallaHarness;
use Tests\Support\Battle\EscenarioBatalla;
use Tests\Support\Battle\ViolacionInvariante;
use Tests\TestCase;

/**
 * Arnés determinista IA vs IA para CI.
 *
 * Solo comprueba determinismo, terminación e invariantes seguras. Las
 * baterías de efectos concretos viven en otros tests.
 */
final class HarnessIaVsIaTest extends TestCase
{
    protected function tearDown(): void
    {
        mt_srand();
        parent::tearDown();
    }

    public function test_misma_semilla_produce_la_misma_batalla(): void
    {
        $escenario = $this->escenario('restos_orbe_vida')->conSemilla(101);
        $ejecutor = new EjecutorBatallaHarness();

        $primera = $ejecutor->ejecutar($escenario);
        $segunda = $ejecutor->ejecutar($escenario);

        $this->assertNotSame('', trim($primera->textoLog()));
        $this->assertSame($primera->textoLog(), $segunda->textoLog());
        $this->assertSame($primera->hpFinales, $segunda->hpFinales);
        $this->assertSame($primera->estancada, $segunda->estancada);
        $this->assertSame($primera->rondas, $segunda->rondas);
        $this->assertSame(
            $this->describirViolaciones($primera->violacionesSeguras),
            $this->describirViolaciones($segunda->violacionesSeguras),
        );
    }

    public function test_batalla_sin_dano_termina_con_tope_de_rondas(): void
    {
        $escenario = CatalogoEscenarios::sinDano()->conMaxRondas(20)->conSemilla(5);

        $inicio = microtime(true);
        $resultado = (new EjecutorBatallaHarness())->ejecutar($escenario);
        $transcurrido = microtime(true) - $inicio;

        $this->assertTrue($resultado->estancada, 'La batalla sin daño debe truncarse por el tope');
        $this->assertSame(20, $resultado->rondas);
        $this->assertStringContainsString('¡Comienza la batalla!', $resultado->textoLog());
        $this->assertStringNotContainsString('gana la batalla', $resultado->textoLog());
        $this->assertSame([], $this->describirViolaciones($resultado->violacionesSeguras));
        $this->assertLessThan(2.0, $transcurrido, "El tope tardó {$transcurrido} s");
    }

    public function test_matriz_humo_de_efectos_respeta_invariantes(): void
    {
        $ejecutor = new EjecutorBatallaHarness();
        $batallas = 0;

        $inicio = microtime(true);

        foreach (CatalogoEscenarios::humo() as $escenario) {
            foreach ([1, 7] as $semilla) {
                $resultado = $ejecutor->ejecutar($escenario->conSemilla($semilla));
                $batallas++;

                $contexto = "{$escenario->nombre} (semilla {$semilla})";
                $this->assertNotSame('', trim($resultado->textoLog()), "{$contexto} no generó log");
                $this->assertLessThanOrEqual(
                    $resultado->maxRondas,
                    $resultado->rondas,
                    "{$contexto} superó el tope de rondas",
                );
                $this->assertSame(
                    [],
                    $this->describirViolaciones($resultado->violacionesSeguras),
                    "{$contexto}: ".implode(' | ', $this->describirViolaciones($resultado->violacionesSeguras)),
                );
            }
        }

        $transcurrido = microtime(true) - $inicio;

        $this->assertSame(20, $batallas);
        $this->assertLessThan(10.0, $transcurrido, "La matriz de humo tardó {$transcurrido} s");
    }

    public function test_max_rondas_invalido_lanza_excepcion(): void
    {
        $batalla = (new ConstructorBatallaHarness())->construir(CatalogoEscenarios::sinDano());

        $this->expectException(\InvalidArgumentException::class);

        $batalla->ejecutarBatalla(0);
    }

    public function test_ivs_explicitas_son_obligatorias_en_el_constructor(): void
    {
        $mensaje = '';
        try {
            ConstructorBatallaHarness::dato([], null);
        } catch (\InvalidArgumentException $excepcion) {
            $mensaje = $excepcion->getMessage();
        }

        $this->assertStringContainsString('IVs explícitas', $mensaje);

        $sinMarca = $this->datosSinMarca();
        $escenario = new EscenarioBatalla(
            nombre: 'sin_ivs_explicitas',
            equipo1: new DatosPokemonBatallaCollection([$sinMarca]),
            equipo2: new DatosPokemonBatallaCollection([$sinMarca]),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IVs explícitas');

        (new ConstructorBatallaHarness())->construir($escenario);
    }

    public function test_el_constructor_acepta_datos_con_ivs_explicitas(): void
    {
        $dato = ConstructorBatallaHarness::dato([
            'id' => 'a1',
            'nombre' => 'Con IVs',
            'hp' => 60,
            'atk' => 60,
            'def' => 60,
            'spAtk' => 60,
            'spDef' => 60,
            'speed' => 60,
            'tipos' => [TipoPokemon::NORMAL],
            'posicion' => Posicion::VANGUARDIA,
            'moves' => [new MovimientoBatalla('Placaje', 40, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
        ], StatsValue::ceros());

        $escenario = new EscenarioBatalla(
            nombre: 'con_ivs_explicitas',
            equipo1: new DatosPokemonBatallaCollection([$dato]),
            equipo2: new DatosPokemonBatallaCollection([$dato]),
        );

        $batalla = (new ConstructorBatallaHarness())->construir($escenario);

        $this->assertInstanceOf(AgregadoBatalla::class, $batalla);
        $this->assertCount(1, $batalla->team1->combatants());
        $this->assertCount(1, $batalla->team2->combatants());
    }

    private function escenario(string $nombre): EscenarioBatalla
    {
        foreach (CatalogoEscenarios::humo() as $escenario) {
            if ($escenario->nombre === $nombre) {
                return $escenario;
            }
        }

        $this->fail("No existe el escenario {$nombre}");
    }

    private function datosSinMarca(): DatosPokemonBatalla
    {
        return new DatosPokemonBatalla(
            id: 'plano',
            nombre: 'Plano',
            hp: 60,
            atk: 60,
            def: 60,
            spAtk: 60,
            spDef: 60,
            speed: 60,
            tipos: [TipoPokemon::NORMAL],
            posicion: Posicion::VANGUARDIA,
            moves: [new MovimientoBatalla('Placaje', 40, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            ivs: StatsValue::ceros(),
        );
    }

    /**
     * @param  list<ViolacionInvariante>  $violaciones
     * @return list<string>
     */
    private function describirViolaciones(array $violaciones): array
    {
        return array_map(
            static fn (ViolacionInvariante $violacion): string => $violacion->describir(),
            $violaciones,
        );
    }
}
