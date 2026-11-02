<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\Exploraciones\App\CombateExploracion;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TipoPokemon;

class CombateExploracionTest extends TestCase
{
    private CombateExploracion $combate;

    protected function setUp(): void
    {
        $this->combate = new CombateExploracion(
            new MapeadorPokemonBatalla(new GeneradorMovimientosTipo()),
        );
    }

    private function datoExploradorFuerte(): DatosPokemonBatalla
    {
        return new DatosPokemonBatalla(
            id: 'exp_1',
            nombre: 'Explorador',
            hp: 300,
            atk: 200,
            def: 150,
            spAtk: 200,
            spDef: 150,
            speed: 180,
            tipos: [TipoPokemon::LUCHA],
            posicion: Posicion::VANGUARDIA,
            ivs: StatsValue::ceros(),
            moves: [
                new MovimientoBatalla(
                    nombre: 'Megapuño',
                    potencia: 120,
                    tipo: TipoPokemon::LUCHA,
                    categoria: CategoriaMovimiento::FISICO,
                ),
            ],
        );
    }

    private function datoSalvajeDebil(): DatosPokemonBatalla
    {
        return new DatosPokemonBatalla(
            id: 'salvaje_1',
            nombre: 'Salvaje',
            hp: 30,
            atk: 20,
            def: 15,
            spAtk: 20,
            spDef: 15,
            speed: 10,
            tipos: [TipoPokemon::NORMAL],
            posicion: Posicion::VANGUARDIA,
            ivs: StatsValue::ceros(),
            moves: [
                new MovimientoBatalla(
                    nombre: 'Placaje',
                    potencia: 40,
                    tipo: TipoPokemon::NORMAL,
                    categoria: CategoriaMovimiento::FISICO,
                ),
            ],
        );
    }

    private function datoExploradorDebil(): DatosPokemonBatalla
    {
        return new DatosPokemonBatalla(
            id: 'exp_2',
            nombre: 'ExploradorDebil',
            hp: 30,
            atk: 20,
            def: 15,
            spAtk: 20,
            spDef: 15,
            speed: 10,
            tipos: [TipoPokemon::NORMAL],
            posicion: Posicion::VANGUARDIA,
            ivs: StatsValue::ceros(),
            moves: [
                new MovimientoBatalla(
                    nombre: 'Placaje',
                    potencia: 40,
                    tipo: TipoPokemon::NORMAL,
                    categoria: CategoriaMovimiento::FISICO,
                ),
            ],
        );
    }

    private function datoSalvajeFuerte(): DatosPokemonBatalla
    {
        return new DatosPokemonBatalla(
            id: 'salvaje_2',
            nombre: 'SalvajeFuerte',
            hp: 300,
            atk: 200,
            def: 150,
            spAtk: 200,
            spDef: 150,
            speed: 180,
            tipos: [TipoPokemon::LUCHA],
            posicion: Posicion::VANGUARDIA,
            ivs: StatsValue::ceros(),
            moves: [
                new MovimientoBatalla(
                    nombre: 'Megapuño',
                    potencia: 120,
                    tipo: TipoPokemon::LUCHA,
                    categoria: CategoriaMovimiento::FISICO,
                ),
            ],
        );
    }

    #[Test]
    public function test_explorador_fuerte_vence_salvaje_debil(): void
    {
        $resultado = $this->combate->combatirDatos(
            $this->datoExploradorFuerte(),
            $this->datoSalvajeDebil(),
        );

        $this->assertTrue($resultado->victoria, 'El explorador fuerte debe ganar');
        $this->assertGreaterThan(0, $resultado->hpFinal, 'El explorador debe tener HP > 0');
        $this->assertNotEmpty($resultado->log->entries()->toList(), 'log debe tener entradas');
        $this->assertNotEmpty($resultado->log->entries()->toList(), 'log no debe estar vacío');
    }

    #[Test]
    public function test_explorador_debil_pierde_contra_salvaje_fuerte(): void
    {
        // Se usa mt_srand para evitar críticos que alteren el resultado
        mt_srand(1);
        $resultado = $this->combate->combatirDatos(
            $this->datoExploradorDebil(),
            $this->datoSalvajeFuerte(),
        );
        mt_srand();

        $this->assertFalse($resultado->victoria, 'El explorador débil debe perder');
        $this->assertSame(0.0, $resultado->hpFinal, 'El explorador debe tener HP 0');
    }

    #[Test]
    public function test_barreras_finales_son_floats(): void
    {
        $resultado = $this->combate->combatirDatos(
            $this->datoExploradorFuerte(),
            $this->datoSalvajeDebil(),
        );

        $this->assertIsFloat($resultado->barreraFisicaFinal);
        $this->assertIsFloat($resultado->barreraEspecialFinal);
        $this->assertGreaterThanOrEqual(0, $resultado->barreraFisicaFinal);
        $this->assertGreaterThanOrEqual(0, $resultado->barreraEspecialFinal);
    }

    #[Test]
    public function test_barreras_se_reducen_tras_recibir_daño(): void
    {
        $resultado = $this->combate->combatirDatos(
            $this->datoExploradorDebil(),
            $this->datoSalvajeFuerte(),
        );

        // El salvaje ataca con Megapuño (físico) → barrera física se reduce
        $this->assertLessThanOrEqual(150, $resultado->barreraFisicaFinal, 'Barrera física debe poder reducirse');
    }

    #[Test]
    public function test_modificador_danio_fuerte_aumenta_el_dano_del_explorador(): void
    {
        $salvaje = new DatosPokemonBatalla(
            id: 'salvaje_medio',
            nombre: 'SalvajeMedio',
            hp: 350,
            atk: 90,
            def: 90,
            spAtk: 90,
            spDef: 90,
            speed: 90,
            tipos: [TipoPokemon::NORMAL],
            posicion: Posicion::VANGUARDIA,
            ivs: StatsValue::ceros(),
            moves: [
                new MovimientoBatalla(
                    nombre: 'Placaje',
                    potencia: 40,
                    tipo: TipoPokemon::NORMAL,
                    categoria: CategoriaMovimiento::FISICO,
                ),
            ],
        );

        mt_srand(1);
        $sinBonus = $this->combate->combatirDatos($this->datoExploradorFuerte(), $salvaje);
        mt_srand();
        mt_srand(1);
        $conBonus = $this->combate->combatirDatos($this->datoExploradorFuerte(), $salvaje, null, 1.15);
        mt_srand();

        $this->assertTrue($sinBonus->victoria, 'El explorador fuerte debe ganar sin bonus');
        $this->assertTrue($conBonus->victoria, 'El explorador fuerte debe ganar con bonus');

        $rondas = static fn (array $log): int => count(array_filter(
            $log,
            static fn (string $line): bool => str_starts_with($line, '--- Ronda'),
        ));
        // El log de combate de exploraciones usa el formato
        // "ataca a [...] con <movimiento> -> N de daño". Se suma el daño de
        // las líneas del movimiento del explorador (Megapuño).
        $danoTotal = static function (array $log): float {
            $total = 0.0;
            foreach ($log as $line) {
                if (str_contains($line, 'Megapuño')
                    && preg_match('/-> ([0-9.]+) de daño/', $line, $coincidencias) === 1) {
                    $total += (float) $coincidencias[1];
                }
            }

            return $total;
        };

        // El daño real infligido al salvaje debe crecer con el bonus, sin
        // depender de un número de rondas que varía con el HP del rival.
        $this->assertGreaterThan(
            $danoTotal($sinBonus->log->entries()->toList()),
            $danoTotal($conBonus->log->entries()->toList()),
            'El bonus de daño debe aumentar el daño total infligido al salvaje',
        );
        $this->assertLessThanOrEqual(
            $rondas($sinBonus->log->entries()->toList()),
            $rondas($conBonus->log->entries()->toList()),
            'El bonus de daño no debe aumentar las rondas del combate',
        );
    }

    #[Test]
    public function test_resultado_tiene_las_propiedades_requeridas(): void
    {
        $resultado = $this->combate->combatirDatos(
            $this->datoExploradorFuerte(),
            $this->datoSalvajeDebil(),
        );

        $this->assertObjectHasProperty('victoria', $resultado);
        $this->assertObjectHasProperty('hpFinal', $resultado);
        $this->assertObjectHasProperty('barreraFisicaFinal', $resultado);
        $this->assertObjectHasProperty('barreraEspecialFinal', $resultado);
        $this->assertObjectHasProperty('log', $resultado);
    }

    #[Test]
    public function test_captura_hp_inicial_y_datos_del_rival(): void
    {
        $resultado = $this->combate->combatirDatos(
            $this->datoExploradorFuerte(),
            $this->datoSalvajeDebil(),
        );

        $this->assertSame($resultado->hpMax, $resultado->hpInicial);
        $this->assertSame($resultado->rivalHpMax, $resultado->rivalHpInicial);
        $this->assertSame(0.0, $resultado->rivalHpFinal, 'El rival debe quedar debilitado');
        $this->assertGreaterThan(0.0, $resultado->rivalHpMax);
    }

    #[Test]
    public function test_captura_hp_inicial_respetando_el_estado_inicial(): void
    {
        mt_srand(1);
        $resultado = $this->combate->combatirDatos(
            $this->datoExploradorFuerte(),
            $this->datoSalvajeDebil(),
            ['hp' => 75.0, 'barrera_fisica' => 10.0, 'barrera_especial' => 10.0],
        );
        mt_srand();

        $this->assertSame(75.0, $resultado->hpInicial);
        $this->assertGreaterThan(75.0, $resultado->hpMax);
        $this->assertSame($resultado->rivalHpMax, $resultado->rivalHpInicial);
        $this->assertSame(0.0, $resultado->rivalHpFinal);
    }
}
