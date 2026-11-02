<?php

declare(strict_types=1);

namespace Tests\Unit\Gimnasios;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\Gimnasios\App\GeneradorPokemonGimnasio;
use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;
use Src\Shared\Collections\IntCollection;
use Src\Shared\Domain\PerfilIvsEvs;
use Src\Shared\Domain\PoliticaIvsEvs;
use Tests\TestCase;

/**
 * Integración de PoliticaIvsEvs en la generación de rivales de gimnasio.
 *
 * El pokémon de prueba tiene los 6 stats base a 80, así que el desempate
 * (hp, atk, def, spAtk, spDef, speed) hace determinista qué stats entran en
 * el top-N de cada perfil.
 */
class GeneradorPokemonGimnasioPerfilTest extends TestCase
{
    use RefreshDatabase;

    private const STATS_IGUALES = [
        'hp' => 80,
        'atk' => 80,
        'def' => 80,
        'spAtk' => 80,
        'spDef' => 80,
        'speed' => 80,
    ];

    private GeneradorPokemonGimnasio $generador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generador = new GeneradorPokemonGimnasio(
            new MapeadorPokemonBatalla(new GeneradorMovimientosTipo()),
        );
    }

    #[Test]
    public function test_sin_perfil_explicito_usa_top_uno(): void
    {
        $this->crearPokemon(1);

        $equipo = $this->generar(1);

        $this->assertSame(
            ['hp' => 15, 'atk' => 0, 'def' => 0, 'spAtk' => 0, 'spDef' => 0, 'speed' => 0],
            $equipo->en(0)->ivs->toArray(),
        );
        $this->assertSame(
            ['hp' => 255, 'atk' => 0, 'def' => 0, 'spAtk' => 0, 'spDef' => 0, 'speed' => 0],
            $equipo->en(0)->evs->toArray(),
        );
    }

    #[Test]
    public function test_perfil_top_uno_maximiza_solo_el_mejor_stat(): void
    {
        $this->crearPokemon(2);

        $equipo = $this->generar(2, PerfilIvsEvs::TopUno);

        $this->assertSame(
            ['hp' => 15, 'atk' => 0, 'def' => 0, 'spAtk' => 0, 'spDef' => 0, 'speed' => 0],
            $equipo->en(0)->ivs->toArray(),
        );
        $this->assertSame(
            ['hp' => 255, 'atk' => 0, 'def' => 0, 'spAtk' => 0, 'spDef' => 0, 'speed' => 0],
            $equipo->en(0)->evs->toArray(),
        );
    }

    #[Test]
    public function test_perfil_top_dos_maximiza_los_dos_mejores_stats_y_mitad_al_resto(): void
    {
        $this->crearPokemon(3);

        $equipo = $this->generar(3, PerfilIvsEvs::TopDos);

        $this->assertSame(
            ['hp' => 15, 'atk' => 15, 'def' => 8, 'spAtk' => 8, 'spDef' => 8, 'speed' => 8],
            $equipo->en(0)->ivs->toArray(),
        );
        $this->assertSame(
            ['hp' => 255, 'atk' => 255, 'def' => 128, 'spAtk' => 128, 'spDef' => 128, 'speed' => 128],
            $equipo->en(0)->evs->toArray(),
        );
    }

    #[Test]
    public function test_perfil_completo_maximiza_los_seis_stats(): void
    {
        $this->crearPokemon(4);

        $equipo = $this->generar(4, PerfilIvsEvs::Completo);

        $this->assertSame(
            ['hp' => 15, 'atk' => 15, 'def' => 15, 'spAtk' => 15, 'spDef' => 15, 'speed' => 15],
            $equipo->en(0)->ivs->toArray(),
        );
        $this->assertSame(
            ['hp' => 255, 'atk' => 255, 'def' => 255, 'spAtk' => 255, 'spDef' => 255, 'speed' => 255],
            $equipo->en(0)->evs->toArray(),
        );
    }

    #[Test]
    public function test_lider_sube_un_tier_en_los_gimnasios_bajos(): void
    {
        $this->crearPokemon(5);

        $perfilEntrenador = PoliticaIvsEvs::perfilGimnasio(1, false);
        $perfilLider = PoliticaIvsEvs::perfilGimnasio(1, true);

        $this->assertSame(PerfilIvsEvs::TopUno, $perfilEntrenador);
        $this->assertSame(PerfilIvsEvs::TopDos, $perfilLider);

        $equipo = $this->generar(5, $perfilLider);

        $this->assertSame(
            ['hp' => 15, 'atk' => 15, 'def' => 8, 'spAtk' => 8, 'spDef' => 8, 'speed' => 8],
            $equipo->en(0)->ivs->toArray(),
        );
        $this->assertSame(
            ['hp' => 255, 'atk' => 255, 'def' => 128, 'spAtk' => 128, 'spDef' => 128, 'speed' => 128],
            $equipo->en(0)->evs->toArray(),
        );
    }

    #[Test]
    public function test_lider_en_gimnasio_alto_no_supera_completo(): void
    {
        $this->assertSame(PerfilIvsEvs::Completo, PoliticaIvsEvs::perfilGimnasio(18, false));
        $this->assertSame(PerfilIvsEvs::Completo, PoliticaIvsEvs::perfilGimnasio(18, true));
    }

    /**
     * @param  PerfilIvsEvs|null  $perfil
     */
    private function generar(int $speciesId, ?PerfilIvsEvs $perfil = null): \Src\Battle\Domain\Collections\DatosPokemonBatallaCollection
    {
        return $this->generador->generar(
            new EquipoEtapaGimnasio(
                vanguardia: new IntCollection([$speciesId]),
                retaguardia: new IntCollection([]),
            ),
            50,
            perfil: $perfil,
        );
    }

    private function crearPokemon(int $speciesId): void
    {
        $pokemon = Pokemon::create([
            'id' => $speciesId,
            'name' => 'pokemon-'.$speciesId,
            'species_id' => $speciesId,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $statMap = [
            StatEnum::HP->value => self::STATS_IGUALES['hp'],
            StatEnum::ATTACK->value => self::STATS_IGUALES['atk'],
            StatEnum::DEFENSE->value => self::STATS_IGUALES['def'],
            StatEnum::SPECIAL_ATTACK->value => self::STATS_IGUALES['spAtk'],
            StatEnum::SPECIAL_DEFENSE->value => self::STATS_IGUALES['spDef'],
            StatEnum::SPEED->value => self::STATS_IGUALES['speed'],
        ];

        foreach ($statMap as $statId => $valor) {
            PokemonStat::create([
                'pokemon_id' => $pokemon->id,
                'stat' => $statId,
                'base_stat' => $valor,
                'effort' => 0,
            ]);
        }

        PokemonType::create([
            'pokemon_id' => $pokemon->id,
            'type' => TipoEnum::NORMAL,
            'slot' => 1,
        ]);
    }
}
