<?php

declare(strict_types=1);

namespace Tests\Unit\CombateRuta;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\CombateRuta\App\GeneradorEquipoRuta;
use Src\Shared\Domain\ClasificadorOfensivaDefensiva;
use Tests\TestCase;

/**
 * Integración de PoliticaIvsEvs en la generación de rivales de ruta.
 *
 * Todos los pokémon de prueba tienen los 6 stats base a 80, así que el
 * desempate (hp, atk, def, spAtk, spDef, speed) hace determinista qué stats
 * entran en el top-N.
 */
class GeneradorEquipoRutaPerfilIvsEvsTest extends TestCase
{
    use RefreshDatabase;

    private const IV_CERO = ['hp' => 0, 'atk' => 0, 'def' => 0, 'spAtk' => 0, 'spDef' => 0, 'speed' => 0];

    private function crearGenerador(): GeneradorEquipoRuta
    {
        return new GeneradorEquipoRuta(
            new MapeadorPokemonBatalla(new GeneradorMovimientosTipo()),
            new ClasificadorOfensivaDefensiva(),
        );
    }

    #[Test]
    public function nivel_1_aplica_perfil_nulo_con_ivs_y_evs_a_cero(): void
    {
        $habitat = $this->crearHabitatConPokemon('Ruta P1', 1);

        $equipo = $this->crearGenerador()->generar(
            (int) $habitat->id,
            1,
            nivelRival: 12,
            aleatorio: $this->aleatorioFijo(0.05),
        );

        $this->assertCount(3, $equipo);
        foreach ($equipo->all() as $datos) {
            $this->assertSame(self::IV_CERO, $datos->ivs->toArray());
            $this->assertSame(self::IV_CERO, $datos->evs->toArray());
        }
    }

    #[Test]
    public function nivel_2_aplica_top_dos_y_mitad_al_resto(): void
    {
        $habitat = $this->crearHabitatConPokemon('Ruta P2', 2);

        $equipo = $this->crearGenerador()->generar(
            (int) $habitat->id,
            2,
            nivelRival: 12,
            aleatorio: $this->aleatorioFijo(0.05),
        );

        $this->assertCount(3, $equipo);
        foreach ($equipo->all() as $datos) {
            $this->assertSame(
                ['hp' => 15, 'atk' => 15, 'def' => 8, 'spAtk' => 8, 'spDef' => 8, 'speed' => 8],
                $datos->ivs->toArray(),
            );
            $this->assertSame(
                ['hp' => 255, 'atk' => 255, 'def' => 128, 'spAtk' => 128, 'spDef' => 128, 'speed' => 128],
                $datos->evs->toArray(),
            );
        }
    }

    #[Test]
    public function nivel_3_aplica_perfil_completo_en_los_seis_stats(): void
    {
        $habitat = $this->crearHabitatConPokemon('Ruta P3', 3);

        $equipo = $this->crearGenerador()->generar(
            (int) $habitat->id,
            3,
            nivelRival: 12,
            aleatorio: $this->aleatorioFijo(0.05),
        );

        $this->assertCount(3, $equipo);
        foreach ($equipo->all() as $datos) {
            $this->assertSame(
                ['hp' => 15, 'atk' => 15, 'def' => 15, 'spAtk' => 15, 'spDef' => 15, 'speed' => 15],
                $datos->ivs->toArray(),
            );
            $this->assertSame(
                ['hp' => 255, 'atk' => 255, 'def' => 255, 'spAtk' => 255, 'spDef' => 255, 'speed' => 255],
                $datos->evs->toArray(),
            );
        }
    }

    #[Test]
    public function el_shiny_en_batalla_usa_el_perfil_del_tier_y_no_ivs_maximos(): void
    {
        $habitat = $this->crearHabitatConPokemon('Ruta Shiny', 1);

        $equipo = $this->crearGenerador()->generar(
            (int) $habitat->id,
            1,
            nivelRival: 12,
            aleatorio: $this->aleatorioFijo(0.05),
            aleatorioShiny: static fn (int $max): int => 1,
        );

        $this->assertCount(3, $equipo);
        foreach ($equipo->all() as $datos) {
            $this->assertTrue($datos->shiny);
            $this->assertSame(self::IV_CERO, $datos->ivs->toArray());
            $this->assertSame(self::IV_CERO, $datos->evs->toArray());
        }
    }

    private function aleatorioFijo(float $valor): callable
    {
        return static fn (): float => $valor;
    }

    private function crearHabitatConPokemon(string $nombre, int $nivel): Habitat
    {
        $province = Province::create(['name' => 'Kanto']);
        $habitat = Habitat::create(['province_id' => $province->id, 'name' => $nombre, 'peligro' => 1]);

        foreach ([9101, 9102, 9103] as $id) {
            $pokemon = Pokemon::create([
                'id' => $id,
                'name' => 'pokemon-'.$id,
                'species_id' => $id,
                'capture_rate' => 45,
                'base_experience' => 64,
                'height' => 7,
                'weight' => 69,
                'hatch' => 10,
            ]);

            foreach (StatEnum::cases() as $stat) {
                $pokemon->stats()->create([
                    'stat' => $stat->value,
                    'base_stat' => 80,
                    'effort' => 0,
                ]);
            }

            $pokemon->types()->create([
                'type' => TipoEnum::NORMAL,
                'slot' => 1,
            ]);

            $habitat->pokemon()->attach($pokemon->id, ['level' => $nivel]);
        }

        return $habitat;
    }
}
