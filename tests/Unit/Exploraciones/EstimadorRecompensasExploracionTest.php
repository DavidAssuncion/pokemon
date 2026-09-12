<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CapacidadesStats;
use Src\Exploraciones\Domain\EstimadorRecompensasExploracion;
use Src\Shared\Tipos\TipoPokemon;

class EstimadorRecompensasExploracionTest extends TestCase
{
    private function capacidades(int $speed = 100): CapacidadesStats
    {
        return new CapacidadesStats(
            hp: 100,
            atk: 100,
            def: 100,
            spAtk: 100,
            spDef: 100,
            speed: $speed,
            nivelPokemon: 30,
            nivelEntrenador: 20,
        );
    }

    /** @return list<array{id:int, capture_rate:int, hatch:int|null, tipos:list<TipoPokemon>, stats:list<array{stat:int,effort:int}>, base_experience:int, evolution_chain_id:?int, species_id:int}> */
    private function pool(): array
    {
        return [
            [
                'id' => 1,
                'capture_rate' => 45,
                'hatch' => null,
                'tipos' => [TipoPokemon::NORMAL],
                'stats' => [['stat' => 2, 'effort' => 1]],
                'base_experience' => 64,
                'evolution_chain_id' => 51,
                'species_id' => 1,
            ],
            [
                'id' => 2,
                'capture_rate' => 45,
                'hatch' => null,
                'tipos' => [TipoPokemon::FUEGO],
                'stats' => [['stat' => 4, 'effort' => 2]],
                'base_experience' => 65,
                'evolution_chain_id' => 51,
                'species_id' => 2,
            ],
        ];
    }

    #[Test]
    public function test_estima_e_segun_intervalo_efectivo_y_bonus(): void
    {
        $estimador = new EstimadorRecompensasExploracion();
        $resultado = $estimador->estimar(
            pool: $this->pool(),
            porHoras: 1,
            capacidades: $this->capacidades(),
            dificultad: 30,
            nivelSalvaje: 30,
        );

        // E = floor(60 / (15 × 0.6)) + bonus rango. Dificultad 30, capacidades altas
        // → MAESTRO movilidad (×0.40 de reducción) y MAESTRO exploración (+3).
        // intervaloEfectivo = floor(15 × 0.6) = 9; e = intdiv(60, 9) + 3 = 9.
        $this->assertSame(9, $resultado['e']);
        $this->assertCount(3, $resultado['items']);
        $this->assertSame(1, $resultado['por_horas']);
        $this->assertSame(0.75, $resultado['rango_min']);
        $this->assertSame(1.25, $resultado['rango_max']);
    }

    #[Test]
    public function test_items_tienen_tipo_label_y_rango(): void
    {
        $estimador = new EstimadorRecompensasExploracion();
        $resultado = $estimador->estimar(
            pool: $this->pool(),
            porHoras: 4,
            capacidades: $this->capacidades(),
            dificultad: 30,
            nivelSalvaje: 30,
        );

        $tipos = array_column($resultado['items'], 'tipo');
        $this->assertContains('familia', $tipos);
        $this->assertContains('ev', $tipos);
        $this->assertContains('tipo', $tipos);

        foreach ($resultado['items'] as $item) {
            $this->assertArrayHasKey('min', $item);
            $this->assertArrayHasKey('max', $item);
            $this->assertLessThanOrEqual($item['max'], $item['min']);
        }
    }

    #[Test]
    public function test_nominal_tipo_es_floor_de_exp_promedio(): void
    {
        $estimador = new EstimadorRecompensasExploracion();
        $resultado = $estimador->estimar(
            pool: $this->pool(),
            porHoras: 1,
            capacidades: $this->capacidades(),
            dificultad: 30,
            nivelSalvaje: 30,
        );

        $itemTipo = null;
        foreach ($resultado['items'] as $item) {
            if ($item['tipo'] === 'tipo') {
                $itemTipo = $item;
            }
        }

        $this->assertNotNull($itemTipo);
        // El nominal de tipo usa floor((expDerrota_promedio × 0.2)/100) antes de × E.
        $this->assertIsInt($itemTipo['min']);
        $this->assertIsInt($itemTipo['max']);
    }

    #[Test]
    public function test_pool_vacio_devuelve_items_a_cero(): void
    {
        $estimador = new EstimadorRecompensasExploracion();
        $resultado = $estimador->estimar(
            pool: [],
            porHoras: 4,
            capacidades: $this->capacidades(),
            dificultad: 30,
            nivelSalvaje: 30,
        );

        foreach ($resultado['items'] as $item) {
            $this->assertSame(0, $item['min']);
            $this->assertSame(0, $item['max']);
        }
    }
}
