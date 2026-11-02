<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\ReglasEv;

class ReglasEvTest extends TestCase
{
    #[Test]
    public function test_un_caramelo_desde_250_ev_llega_al_tope_y_pierde_5(): void
    {
        $this->assertSame(1, ReglasEv::caramelosHastaTope(250.0, 1));
        $this->assertSame(255.0, ReglasEv::aplicar(250.0, 1));
    }

    #[Test]
    public function test_en_el_tope_no_se_consumen_caramelos(): void
    {
        $this->assertSame(0, ReglasEv::caramelosHastaTope(255.0, 5));
        $this->assertSame(255.0, ReglasEv::aplicar(255.0, 5));
    }

    #[Test]
    public function test_dos_caramelos_desde_240_ev_llegan_al_tope_y_pierden_5(): void
    {
        $this->assertSame(2, ReglasEv::caramelosHastaTope(240.0, 2));
        $this->assertSame(255.0, ReglasEv::aplicar(240.0, 2));
    }

    #[Test]
    public function test_legado_por_encima_del_tope_se_conserva_intacto(): void
    {
        $this->assertSame(0, ReglasEv::caramelosHastaTope(300.0, 5));
        $this->assertSame(300.0, ReglasEv::aplicar(300.0, 5));
    }

    #[Test]
    public function test_desde_cero_se_necesitan_26_caramelos_para_llegar_al_tope(): void
    {
        $this->assertSame(26, ReglasEv::caramelosHastaTope(0.0, 100));
        $this->assertSame(255.0, ReglasEv::aplicar(0.0, 26));
    }
}
