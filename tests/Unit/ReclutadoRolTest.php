<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Reclutado;
use PHPUnit\Framework\Attributes\Test;
use Src\Exploraciones\Domain\RolExploracion;
use Tests\TestCase;

class ReclutadoRolTest extends TestCase
{
    /** rol() devuelve el rol parseado del behavior. */
    #[Test]
    public function rol_devuelve_el_valor_correcto_del_behavior(): void
    {
        $reclutado = new Reclutado(['behavior' => 'VANGUARDIA']);

        $this->assertSame(RolExploracion::VANGUARDIA, $reclutado->rol());
    }

    /** rol() hace fallback a COMBATIENTE ante un behavior inválido o vacío. */
    #[Test]
    public function rol_devuelve_combatiente_ante_behavior_invalido_o_nulo(): void
    {
        $reclutado = new Reclutado(['behavior' => null]);
        $this->assertSame(RolExploracion::COMBATIENTE, $reclutado->rol());

        $reclutado2 = new Reclutado(['behavior' => 'INVALIDO']);
        $this->assertSame(RolExploracion::COMBATIENTE, $reclutado2->rol());
    }
}
