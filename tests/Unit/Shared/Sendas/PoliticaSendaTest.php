<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Sendas;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Sendas\MapaSendaJugador;
use Src\Shared\Domain\Sendas\PoliticaSenda;
use Src\Shared\Tipos\TipoPokemon;

class PoliticaSendaTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: float, 2: float}>
     */
    public static function nivelesYFactores(): array
    {
        return [
            'N1' => [1, 1.0, 1.0],
            'N2' => [2, 1.0, 1.0],
            'N3' => [3, 1.0, 1.0],
            'N4' => [4, 1.1, 0.9],
            'N5' => [5, 1.2, 0.8],
        ];
    }

    #[Test]
    #[DataProvider('nivelesYFactores')]
    public function factores_de_dano_siguen_el_nivel(int $nivel, float $infligido, float $recibido): void
    {
        $mapa = MapaSendaJugador::desdeArray([TipoPokemon::FUEGO->value => $nivel]);

        $this->assertEqualsWithDelta($infligido, PoliticaSenda::factorDanoInfligido($mapa, TipoPokemon::FUEGO), 0.000001);
        $this->assertEqualsWithDelta($recibido, PoliticaSenda::factorDanoRecibido($mapa, TipoPokemon::FUEGO), 0.000001);
    }

    /**
     * @return array<string, array{0: int, 1: float}>
     */
    public static function nivelesYFactorPositivo(): array
    {
        return [
            'N1' => [1, 1.0],
            'N2' => [2, 1.0],
            'N3' => [3, 1.0],
            'N4' => [4, 1.1],
            'N5' => [5, 1.2],
        ];
    }

    #[Test]
    #[DataProvider('nivelesYFactorPositivo')]
    public function factores_de_captura_y_caramelo_siguen_el_nivel(int $nivel, float $esperado): void
    {
        $mapa = MapaSendaJugador::desdeArray([TipoPokemon::FUEGO->value => $nivel]);

        $this->assertEqualsWithDelta($esperado, PoliticaSenda::factorCaptura($mapa, [TipoPokemon::FUEGO]), 0.000001);
        $this->assertEqualsWithDelta($esperado, PoliticaSenda::factorCaramelo($mapa, TipoPokemon::FUEGO), 0.000001);
    }

    #[Test]
    public function factor_captura_de_un_dual_usa_el_mejor_tipo(): void
    {
        $mapa = MapaSendaJugador::desdeArray([
            TipoPokemon::FUEGO->value => 4,
            TipoPokemon::AGUA->value => 5,
        ]);

        $this->assertEqualsWithDelta(1.2, PoliticaSenda::factorCaptura($mapa, [TipoPokemon::FUEGO, TipoPokemon::AGUA]), 0.000001);
    }

    #[Test]
    public function mapa_vacio_no_altera_ningun_factor(): void
    {
        $mapa = MapaSendaJugador::vacio();

        $this->assertSame(1.0, PoliticaSenda::factorDanoInfligido($mapa, TipoPokemon::FUEGO));
        $this->assertSame(1.0, PoliticaSenda::factorDanoRecibido($mapa, TipoPokemon::FUEGO));
        $this->assertSame(1.0, PoliticaSenda::factorCaptura($mapa, []));
        $this->assertSame(1.0, PoliticaSenda::factorCaramelo($mapa, TipoPokemon::FUEGO));
    }
}
