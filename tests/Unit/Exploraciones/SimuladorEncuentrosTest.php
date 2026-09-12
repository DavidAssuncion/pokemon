<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\SimuladorEncuentros;
use Src\Shared\Tipos\TipoPokemon;

class SimuladorEncuentrosTest extends TestCase
{
    /** @var list<array{id: int, capture_rate: int, hatch: int|null, tipos: list<TipoPokemon>, stats: list<array{stat: int, effort: int}>}> */
    private array $pool;

    protected function setUp(): void
    {
        $this->pool = [
            ['id' => 1, 'capture_rate' => 255, 'hatch' => 1, 'tipos' => [TipoPokemon::NORMAL], 'stats' => []],
        ];
    }

    public function test_por_defecto_se_generan_emboscadas_en_el_bucket_de_encuentro(): void
    {
        $eventos = SimuladorEncuentros::generarEventos(
            $this->pool,
            1,
            now()->subMinute(),
            now(),
            fn (): float => 0.05,
        );

        $this->assertSame('emboscada', $eventos[0]['tipo']);
        $this->assertSame([1, 1], $eventos[0]['pokemon_ids']);
    }

    public function test_por_defecto_el_subtipo_excepcional_sigue_disponible(): void
    {
        $eventos = SimuladorEncuentros::generarEventos(
            $this->pool,
            1,
            now()->subMinute(),
            now(),
            fn (): float => 0.085,
        );

        $this->assertSame('encuentro', $eventos[0]['tipo']);
        $this->assertSame('excepcional', $eventos[0]['subtype']);
    }

    public function test_sin_emboscadas_el_bucket_de_encuentro_se_convierte_en_grupo(): void
    {
        $eventos = SimuladorEncuentros::generarEventos(
            $this->pool,
            1,
            now()->subMinute(),
            now(),
            fn (): float => 0.05,
            false,
        );

        $this->assertSame('encuentro', $eventos[0]['tipo']);
        $this->assertSame('grupo', $eventos[0]['subtype']);
    }

    public function test_sin_excepcionales_el_bucket_de_encuentro_se_convierte_en_grupo(): void
    {
        $secuencia = [0.5, 0.05, 0.085, 0.5];
        $aleatorio = function () use (&$secuencia): float {
            return array_shift($secuencia) ?? 0.5;
        };

        $eventos = SimuladorEncuentros::generarEventos(
            $this->pool,
            1,
            now()->subMinute(),
            now(),
            $aleatorio,
            true,
            false,
        );

        $this->assertSame('encuentro', $eventos[0]['tipo']);
        $this->assertSame('grupo', $eventos[0]['subtype']);
    }

    public function test_sin_emboscadas_el_bucket_especial_nunca_genera_emboscada(): void
    {
        // tiradaTipo = 0.7 → 70 % → bucket especial (emboscada de nivel superior)
        $secuencia = [0.5, 0.7, 0.7, 0.7];
        $aleatorio = function () use (&$secuencia): float {
            return array_shift($secuencia) ?? 0.5;
        };

        $eventos = SimuladorEncuentros::generarEventos(
            $this->pool,
            1,
            now()->subMinute(),
            now(),
            $aleatorio,
            false,
        );

        $this->assertNotSame('emboscada', $eventos[0]['tipo']);
        $this->assertContains($eventos[0]['tipo'], ['hallazgo', 'neutral']);
    }

    public function test_sin_emboscadas_ningun_evento_de_muchos_es_emboscada(): void
    {
        $inicio = now()->subHour();
        $fin = now();

        $eventos = SimuladorEncuentros::generarEventos($this->pool, 1000, $inicio, $fin, null, false);

        $this->assertNotEmpty($eventos);
        foreach ($eventos as $evento) {
            $this->assertNotSame('emboscada', $evento['tipo']);
        }
    }
}
