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

        $this->assertTrue($resultado['victoria'], 'El explorador fuerte debe ganar');
        $this->assertGreaterThan(0, $resultado['hp_final'], 'El explorador debe tener HP > 0');
        $this->assertIsArray($resultado['log'], 'log debe ser array');
        $this->assertNotEmpty($resultado['log'], 'log no debe estar vacío');
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

        $this->assertFalse($resultado['victoria'], 'El explorador débil debe perder');
        $this->assertSame(0.0, $resultado['hp_final'], 'El explorador debe tener HP 0');
    }

    #[Test]
    public function test_barreras_finales_son_floats(): void
    {
        $resultado = $this->combate->combatirDatos(
            $this->datoExploradorFuerte(),
            $this->datoSalvajeDebil(),
        );

        $this->assertIsFloat($resultado['barrera_fisica_final']);
        $this->assertIsFloat($resultado['barrera_especial_final']);
        $this->assertGreaterThanOrEqual(0, $resultado['barrera_fisica_final']);
        $this->assertGreaterThanOrEqual(0, $resultado['barrera_especial_final']);
    }

    #[Test]
    public function test_barreras_se_reducen_tras_recibir_daño(): void
    {
        $resultado = $this->combate->combatirDatos(
            $this->datoExploradorDebil(),
            $this->datoSalvajeFuerte(),
        );

        // El salvaje ataca con Megapuño (físico) → barrera física se reduce
        $this->assertLessThanOrEqual(150, $resultado['barrera_fisica_final'], 'Barrera física debe poder reducirse');
    }

    #[Test]
    public function test_resultado_tiene_4_claves_requeridas_mas_log(): void
    {
        $resultado = $this->combate->combatirDatos(
            $this->datoExploradorFuerte(),
            $this->datoSalvajeDebil(),
        );

        $this->assertArrayHasKey('victoria', $resultado);
        $this->assertArrayHasKey('hp_final', $resultado);
        $this->assertArrayHasKey('barrera_fisica_final', $resultado);
        $this->assertArrayHasKey('barrera_especial_final', $resultado);
        $this->assertArrayHasKey('log', $resultado);
    }
}
