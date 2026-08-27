<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\FaseEvolutiva;

class FaseEvolutivaTest extends TestCase
{
    /** Cadena evolutiva de Charmander: 4 → 5 → 6. */
    private function cadenaCharmander(): array
    {
        return [
            ['species_id' => 4],
            ['species_id' => 5],
            ['species_id' => 6],
        ];
    }

    public function test_forma_base_es_fase_uno(): void
    {
        $this->assertSame(1, FaseEvolutiva::de(4, $this->cadenaCharmander()));
    }

    public function test_segunda_evolucion_es_fase_dos(): void
    {
        $this->assertSame(2, FaseEvolutiva::de(5, $this->cadenaCharmander()));
    }

    public function test_tercera_evolucion_es_fase_tres(): void
    {
        $this->assertSame(3, FaseEvolutiva::de(6, $this->cadenaCharmander()));
    }

    public function test_cadena_vacia_devuelve_cero(): void
    {
        $this->assertSame(0, FaseEvolutiva::de(4, []));
    }

    public function test_de_segura_nunca_devuelve_menos_de_uno(): void
    {
        $this->assertSame(1, FaseEvolutiva::deSegura(4, []));
        $this->assertSame(1, FaseEvolutiva::deSegura(1, $this->cadenaCharmander()));
    }

    public function test_no_depende_del_orden_de_la_cadena(): void
    {
        $desordenada = [
            ['species_id' => 6],
            ['species_id' => 4],
            ['species_id' => 5],
        ];

        $this->assertSame(2, FaseEvolutiva::de(5, $desordenada));
        $this->assertSame(3, FaseEvolutiva::de(6, $desordenada));
    }
}
