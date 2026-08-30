<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\Effects\EfectoOrbeVida;
use Src\Battle\Domain\Effects\EfectoPerforacionArmadura;
use Src\Battle\Domain\Effects\EfectoRegeneracionDefensa;
use Src\Battle\Domain\Effects\EfectoRestos;
use Src\Battle\Domain\Effects\FabricaEfectos;
use Src\Battle\Domain\Effects\InterfazEfecto;

/**
 * FabricaEfectos (instancia inyectable): registro y creación de efectos/items.
 */
class FabricaEfectosTest extends TestCase
{
    private FabricaEfectos $fabrica;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fabrica = new FabricaEfectos();
        $this->fabrica->registrarEfecto('armor_pierce', EfectoPerforacionArmadura::class, 0.10);
        $this->fabrica->registrarEfecto('regen_def', EfectoRegeneracionDefensa::class, 10.0);
        $this->fabrica->registrarItem('leftovers', EfectoRestos::class);
        $this->fabrica->registrarItem('life_orb', EfectoOrbeVida::class);
    }

    public function test_crear_efecto_devuelve_instancia_con_clave(): void
    {
        $efecto = $this->fabrica->crearEfecto('armor_pierce');

        $this->assertInstanceOf(InterfazEfecto::class, $efecto);
        $this->assertInstanceOf(EfectoPerforacionArmadura::class, $efecto);
        $this->assertSame('armor_pierce', $efecto->obtenerClave());
        $this->assertSame(0.10, $efecto->obtenerPorcentajeDanioDirecto());
    }

    public function test_crear_efecto_con_args_variadicos(): void
    {
        $efecto = $this->fabrica->crearEfecto('regen_def');

        $this->assertInstanceOf(EfectoRegeneracionDefensa::class, $efecto);
        $this->assertSame('regen_def', $efecto->obtenerClave());
    }

    public function test_crear_efecto_desconocido_devuelve_null(): void
    {
        $this->assertNull($this->fabrica->crearEfecto('no_existe'));
    }

    public function test_crear_item_devuelve_instancia(): void
    {
        $item = $this->fabrica->crearItem('leftovers');

        $this->assertInstanceOf(InterfazEfecto::class, $item);
        $this->assertInstanceOf(EfectoRestos::class, $item);
        $this->assertSame('leftovers', $item->obtenerClave());
    }

    public function test_crear_item_desconocido_devuelve_null(): void
    {
        $this->assertNull($this->fabrica->crearItem('no_existe'));
    }

    public function test_claves_efectos_lista_registrados(): void
    {
        $claves = $this->fabrica->clavesEfectos();

        $this->assertContains('armor_pierce', $claves);
        $this->assertContains('regen_def', $claves);
        $this->assertNotContains('leftovers', $claves);
    }

    public function test_claves_items_lista_registrados(): void
    {
        $claves = $this->fabrica->clavesItems();

        $this->assertContains('leftovers', $claves);
        $this->assertContains('life_orb', $claves);
        $this->assertNotContains('armor_pierce', $claves);
    }
}
