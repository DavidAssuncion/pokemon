<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Pokemon\Domain\Stats\DatosStats;
use Src\Shared\Domain\PerfilIvsEvs;
use Src\Shared\Domain\PoliticaIvsEvs;

class PoliticaIvsEvsTest extends TestCase
{
    private static function base(): DatosStats
    {
        return new DatosStats(hp: 45, atk: 60, def: 40, spAtk: 50, spDef: 45, speed: 70);
    }

    #[Test]
    public function test_las_constantes_coinciden_con_la_escala_del_proyecto(): void
    {
        $this->assertSame(15, PoliticaIvsEvs::IV_MAXIMO);
        $this->assertSame(255, PoliticaIvsEvs::EV_MAXIMO);
        $this->assertSame(8, PoliticaIvsEvs::IV_MITAD);
        $this->assertSame(128, PoliticaIvsEvs::EV_MITAD);
    }

    /**
     * @return array<string, array{0: int, 1: PerfilIvsEvs}>
     */
    public static function nivelesRuta(): array
    {
        return [
            'nivel 1' => [1, PerfilIvsEvs::Nulo],
            'nivel 2' => [2, PerfilIvsEvs::TopDos],
            'nivel 3' => [3, PerfilIvsEvs::Completo],
            'clamp inferior' => [-4, PerfilIvsEvs::Nulo],
            'clamp cero' => [0, PerfilIvsEvs::Nulo],
            'clamp superior' => [9, PerfilIvsEvs::Completo],
        ];
    }

    #[Test]
    #[DataProvider('nivelesRuta')]
    public function test_perfil_de_ruta(int $nivel, PerfilIvsEvs $esperado): void
    {
        $this->assertSame($esperado, PoliticaIvsEvs::perfilRuta($nivel));
    }

    /**
     * @return array<string, array{0: int, 1: PerfilIvsEvs}>
     */
    public static function nivelesEntrenador(): array
    {
        return [
            'nivel 1' => [1, PerfilIvsEvs::TopUno],
            'nivel 2' => [2, PerfilIvsEvs::TopDos],
            'nivel 3' => [3, PerfilIvsEvs::Completo],
            'clamp inferior' => [0, PerfilIvsEvs::TopUno],
            'clamp superior' => [5, PerfilIvsEvs::Completo],
        ];
    }

    #[Test]
    #[DataProvider('nivelesEntrenador')]
    public function test_perfil_de_entrenador(int $nivel, PerfilIvsEvs $esperado): void
    {
        $this->assertSame($esperado, PoliticaIvsEvs::perfilEntrenador($nivel));
    }

    /**
     * @return array<string, array{0: int, 1: PerfilIvsEvs}>
     */
    public static function indicesGimnasio(): array
    {
        return [
            'indice 1' => [1, PerfilIvsEvs::TopUno],
            'indice 6' => [6, PerfilIvsEvs::TopUno],
            'indice 7' => [7, PerfilIvsEvs::TopDos],
            'indice 12' => [12, PerfilIvsEvs::TopDos],
            'indice 13' => [13, PerfilIvsEvs::Completo],
            'indice 18' => [18, PerfilIvsEvs::Completo],
            'clamp inferior' => [0, PerfilIvsEvs::TopUno],
            'clamp superior' => [25, PerfilIvsEvs::Completo],
        ];
    }

    #[Test]
    #[DataProvider('indicesGimnasio')]
    public function test_perfil_de_gimnasio(int $indice, PerfilIvsEvs $esperado): void
    {
        $this->assertSame($esperado, PoliticaIvsEvs::perfilGimnasio($indice));
    }

    /**
     * @return array<string, array{0: int, 1: PerfilIvsEvs}>
     */
    public static function indicesLider(): array
    {
        return [
            'lider 1 sube a top_dos' => [1, PerfilIvsEvs::TopDos],
            'lider 6 sube a top_dos' => [6, PerfilIvsEvs::TopDos],
            'lider 7 sube a completo' => [7, PerfilIvsEvs::Completo],
            'lider 12 sube a completo' => [12, PerfilIvsEvs::Completo],
            'lider 13 tope completo' => [13, PerfilIvsEvs::Completo],
            'lider 18 tope completo' => [18, PerfilIvsEvs::Completo],
            'lider con clamp inferior' => [0, PerfilIvsEvs::TopDos],
            'lider con clamp superior' => [25, PerfilIvsEvs::Completo],
        ];
    }

    #[Test]
    #[DataProvider('indicesLider')]
    public function test_lider_sube_un_tier_con_tope_completo(int $indice, PerfilIvsEvs $esperado): void
    {
        $this->assertSame($esperado, PoliticaIvsEvs::perfilGimnasio($indice, esLider: true));
    }

    #[Test]
    public function test_perfil_nulo_no_asigna_ivs_ni_evs(): void
    {
        $ceros = ['hp' => 0, 'atk' => 0, 'def' => 0, 'spAtk' => 0, 'spDef' => 0, 'speed' => 0];

        $this->assertSame($ceros, PoliticaIvsEvs::ivs(PerfilIvsEvs::Nulo, self::base())->toArray());
        $this->assertSame($ceros, PoliticaIvsEvs::evs(PerfilIvsEvs::Nulo, self::base())->toArray());
    }

    #[Test]
    public function test_perfil_top_uno_maximiza_solo_speed(): void
    {
        $ivs = PoliticaIvsEvs::ivs(PerfilIvsEvs::TopUno, self::base())->toArray();
        $evs = PoliticaIvsEvs::evs(PerfilIvsEvs::TopUno, self::base())->toArray();

        $this->assertSame(['hp' => 0, 'atk' => 0, 'def' => 0, 'spAtk' => 0, 'spDef' => 0, 'speed' => 15], $ivs);
        $this->assertSame(['hp' => 0, 'atk' => 0, 'def' => 0, 'spAtk' => 0, 'spDef' => 0, 'speed' => 255], $evs);
    }

    #[Test]
    public function test_perfil_top_dos_maximiza_speed_y_atk_y_el_resto_va_a_la_mitad(): void
    {
        $ivs = PoliticaIvsEvs::ivs(PerfilIvsEvs::TopDos, self::base())->toArray();
        $evs = PoliticaIvsEvs::evs(PerfilIvsEvs::TopDos, self::base())->toArray();

        $this->assertSame(['hp' => 8, 'atk' => 15, 'def' => 8, 'spAtk' => 8, 'spDef' => 8, 'speed' => 15], $ivs);
        $this->assertSame(['hp' => 128, 'atk' => 255, 'def' => 128, 'spAtk' => 128, 'spDef' => 128, 'speed' => 255], $evs);
    }

    #[Test]
    public function test_perfil_completo_maximiza_los_seis_stats(): void
    {
        $maximos = ['hp' => 15, 'atk' => 15, 'def' => 15, 'spAtk' => 15, 'spDef' => 15, 'speed' => 15];

        $this->assertSame($maximos, PoliticaIvsEvs::ivs(PerfilIvsEvs::Completo, self::base())->toArray());
        $this->assertSame(
            ['hp' => 255, 'atk' => 255, 'def' => 255, 'spAtk' => 255, 'spDef' => 255, 'speed' => 255],
            PoliticaIvsEvs::evs(PerfilIvsEvs::Completo, self::base())->toArray(),
        );
    }
}
