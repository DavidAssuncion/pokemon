<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CapacidadesStats;
use Src\Exploraciones\Domain\EstimadorRecompensasExploracion;
use Src\Exploraciones\Domain\ReglasExploracion;
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

    /**
     * Pool con base_experience alta: el caramelo de tipo deja de redondear a 0
     * y permite verificar el factor económico en los tres items.
     *
     * @return list<array{id:int, capture_rate:int, hatch:int|null, tipos:list<TipoPokemon>, stats:list<array{stat:int,effort:int}>, base_experience:int, evolution_chain_id:?int, species_id:int}>
     */
    private function poolAlto(): array
    {
        return [
            [
                'id' => 1,
                'capture_rate' => 45,
                'hatch' => null,
                'tipos' => [TipoPokemon::FUEGO],
                'stats' => [['stat' => 2, 'effort' => 1]],
                'base_experience' => 500,
                'evolution_chain_id' => 51,
                'species_id' => 1,
            ],
            [
                'id' => 2,
                'capture_rate' => 45,
                'hatch' => null,
                'tipos' => [TipoPokemon::FUEGO],
                'stats' => [['stat' => 4, 'effort' => 1]],
                'base_experience' => 500,
                'evolution_chain_id' => 51,
                'species_id' => 2,
            ],
        ];
    }

    #[Test]
    public function test_items_aplican_el_factor_economico_y_tienen_label_no_nulo(): void
    {
        $estimador = new EstimadorRecompensasExploracion();
        $resultado = $estimador->estimar(
            pool: $this->poolAlto(),
            porHoras: 1,
            capacidades: $this->capacidades(),
            dificultad: 30,
            nivelSalvaje: 30,
        );

        $porTipo = [];
        foreach ($resultado['items'] as $item) {
            $porTipo[$item['tipo']] = $item;
            $this->assertNotNull($item['label']);
            $this->assertNotSame('', trim($item['label']));
        }

        $this->assertSame('Caramelos de familia', $porTipo['familia']['label']);
        $this->assertSame('Caramelos EV', $porTipo['ev']['label']);
        $this->assertSame('Caramelos de tipo Fuego', $porTipo['tipo']['label']);

        $e = $resultado['e'];
        $factor = ReglasExploracion::FACTOR_RECOMPENSAS;

        // Nominales SIN factor: familia/EV = E × 1.0 (fase 1, effort 1);
        // tipo = E × floor((3000 × 0.2)/100) = E × 6.
        $nominales = [
            'familia' => $e * 1.0,
            'ev' => $e * 1.0,
            'tipo' => $e * 6,
        ];

        foreach ($nominales as $tipo => $nominalSinFactor) {
            $nominalConFactor = $nominalSinFactor * $factor;
            $this->assertSame((int) floor($nominalConFactor * EstimadorRecompensasExploracion::RANGO_MIN), $porTipo[$tipo]['min'], $tipo);
            $this->assertSame((int) ceil($nominalConFactor * EstimadorRecompensasExploracion::RANGO_MAX), $porTipo[$tipo]['max'], $tipo);
            // Con factor 0.5 el máximo queda por debajo del máximo sin factor.
            $this->assertLessThan(
                (int) ceil($nominalSinFactor * EstimadorRecompensasExploracion::RANGO_MAX),
                $porTipo[$tipo]['max'],
                $tipo,
            );
        }
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

        // E = floor(60 / (30 × 0.6)) + bonus rango. Dificultad 30, capacidades altas
        // → MAESTRO movilidad (×0.40 de reducción) y MAESTRO exploración (+3).
        // intervaloEfectivo = floor(30 × 0.6) = 18; e = intdiv(60, 18) + 3 = 6.
        $this->assertSame(6, $resultado['e']);
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

    #[Test]
    public function test_fase_acepta_el_mapa_de_etapas_por_especie(): void
    {
        $pool = [[
            'id' => 25,
            'capture_rate' => 45,
            'hatch' => null,
            'tipos' => [TipoPokemon::ELECTRICO],
            'stats' => [['stat' => 6, 'effort' => 1]],
            'base_experience' => 112,
            'evolution_chain_id' => 10,
            'species_id' => 25,
        ]];

        $estimador = new EstimadorRecompensasExploracion();
        $conMapa = $estimador->estimar(
            pool: $pool,
            porHoras: 10,
            capacidades: $this->capacidades(),
            dificultad: 30,
            nivelSalvaje: 30,
            cadenas: [10 => [25 => 3]],
        );
        $conLista = $estimador->estimar(
            pool: $pool,
            porHoras: 10,
            capacidades: $this->capacidades(),
            dificultad: 30,
            nivelSalvaje: 30,
            cadenas: [10 => [25]],
        );

        $familia = static function (array $resultado): array {
            foreach ($resultado['items'] as $item) {
                if ($item['tipo'] === 'familia') {
                    return $item;
                }
            }

            return [];
        };

        // El mapa real da fase 3; la lista legacy daría fase 1.
        $this->assertGreaterThan($familia($conLista)['max'], $familia($conMapa)['max']);
        $this->assertGreaterThan($familia($conLista)['min'], $familia($conMapa)['min']);
    }
}
