<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\CalculadorDañoClima;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Daño por clima al final de la ronda:
 * Granizo daña 6.25% a no-HIELO; tormenta arena daña 6.25% a no-ROCA/TIERRA/ACERO.
 */
class CalculadorDañoClimaTest extends TestCase
{
    use ConstruyeCombatientes;

    private CalculadorDañoClima $calculador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculador = new CalculadorDañoClima();
    }

    public function test_granizo_dana_a_no_hielo(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 100], // battleStats()->hp = 310
            tipos: [TipoPokemon::PLANTA],
            id: 'c1',
            nombre: 'Planta',
        );

        $daño = $this->calculador->calcular($combatiente, TipoClima::GRANIZO);

        $this->assertSame(19.375, $daño); // max(1, 310 * 0.0625)
    }

    public function test_granizo_no_dana_a_hielo(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 100],
            tipos: [TipoPokemon::HIELO],
            id: 'c1',
            nombre: 'Hielo',
        );

        $daño = $this->calculador->calcular($combatiente, TipoClima::GRANIZO);

        $this->assertSame(0.0, $daño);
    }

    public function test_tormenta_arena_dana_a_no_roca_tierra_acero(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 100], // battleStats()->hp = 310
            tipos: [TipoPokemon::SINIESTRO],
            id: 'c1',
            nombre: 'Siniestro',
        );

        $daño = $this->calculador->calcular($combatiente, TipoClima::TORMENTA_ARENA);

        $this->assertSame(19.375, $daño); // max(1, 310 * 0.0625)
    }

    public function test_tormenta_arena_no_dana_a_roca(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 100],
            tipos: [TipoPokemon::ROCA],
            id: 'c1',
            nombre: 'Roca',
        );

        $daño = $this->calculador->calcular($combatiente, TipoClima::TORMENTA_ARENA);

        $this->assertSame(0.0, $daño);
    }

    public function test_combatiente_muerto_devuelve_0(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 100],
            tipos: [TipoPokemon::PLANTA],
            id: 'c1',
            nombre: 'Planta',
        );
        $combatiente->setHpActual(0);

        $daño = $this->calculador->calcular($combatiente, TipoClima::GRANIZO);

        $this->assertSame(0.0, $daño);
    }

    public function test_clima_none_devuelve_0(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 100],
            tipos: [TipoPokemon::PLANTA],
            id: 'c1',
            nombre: 'Planta',
        );

        $daño = $this->calculador->calcular($combatiente, TipoClima::NONE);

        $this->assertSame(0.0, $daño);
    }

    public function test_tormenta_arena_no_dana_a_tierra(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 100],
            tipos: [TipoPokemon::TIERRA],
            id: 'c1',
            nombre: 'Tierra',
        );

        $daño = $this->calculador->calcular($combatiente, TipoClima::TORMENTA_ARENA);

        $this->assertSame(0.0, $daño);
    }

    public function test_tormenta_arena_no_dana_a_acero(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 100],
            tipos: [TipoPokemon::ACERO],
            id: 'c1',
            nombre: 'Acero',
        );

        $daño = $this->calculador->calcular($combatiente, TipoClima::TORMENTA_ARENA);

        $this->assertSame(0.0, $daño);
    }

    public function test_tormenta_arena_no_dana_a_dual_tipo_roca_planta(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 100],
            tipos: [TipoPokemon::ROCA, TipoPokemon::PLANTA],
            id: 'c1',
            nombre: 'RocaPlanta',
        );

        $daño = $this->calculador->calcular($combatiente, TipoClima::TORMENTA_ARENA);

        $this->assertSame(0.0, $daño);
    }

    public function test_tormenta_arena_no_dana_a_dual_tipo_planta_roca(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 100],
            tipos: [TipoPokemon::PLANTA, TipoPokemon::ROCA],
            id: 'c1',
            nombre: 'PlantaRoca',
        );

        $daño = $this->calculador->calcular($combatiente, TipoClima::TORMENTA_ARENA);

        $this->assertSame(0.0, $daño);
    }

    public function test_granizo_no_dana_a_dual_tipo_planta_hielo(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 100],
            tipos: [TipoPokemon::PLANTA, TipoPokemon::HIELO],
            id: 'c1',
            nombre: 'PlantaHielo',
        );

        $daño = $this->calculador->calcular($combatiente, TipoClima::GRANIZO);

        $this->assertSame(0.0, $daño);
    }
}
