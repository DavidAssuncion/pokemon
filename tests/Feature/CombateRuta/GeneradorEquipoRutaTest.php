<?php

declare(strict_types=1);

namespace Tests\Feature\CombateRuta;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\CombateRuta\App\GeneradorEquipoRuta;
use Src\Shared\Domain\ClasificadorOfensivaDefensiva;
use Tests\TestCase;

class GeneradorEquipoRutaTest extends TestCase
{
    use RefreshDatabase;

    private function crearGenerador(): GeneradorEquipoRuta
    {
        return new GeneradorEquipoRuta(
            new MapeadorPokemonBatalla(new GeneradorMovimientosTipo()),
            new ClasificadorOfensivaDefensiva(),
        );
    }

    #[Test]
    public function genera_cinco_salvajes_con_seleccion_ponderada_con_reemplazo(): void
    {
        $habitat = $this->crearHabitat('Ruta 1');
        // Pesos capture_rate/hatch: 200/10=20, 100/10=10, 10/10=1 (total 31).
        $this->crearPokemon(601, $habitat, captureRate: 200);
        $this->crearPokemon(602, $habitat, captureRate: 100);
        $this->crearPokemon(603, $habitat, captureRate: 10);

        // Buckets acumulados: 601 ≤ 20/31 ≈ 0.645; 602 ≤ 30/31 ≈ 0.968; 603 > 0.968.
        $aleatorio = (function (): float {
            static $pasos = null;
            $pasos ??= [0.05, 0.05, 0.7, 0.7, 0.99];

            return array_shift($pasos);
        });

        $equipo = $this->crearGenerador()->generar((int) $habitat->id, 1, nivelRival: 12, aleatorio: $aleatorio);

        $this->assertCount(5, $equipo);
        $this->assertSame(
            [601, 601, 602, 602, 603],
            array_map(fn (DatosPokemonBatalla $d): int => $d->speciesId, $equipo),
        );
    }

    #[Test]
    public function aplica_la_formacion_determinista_del_clasificador(): void
    {
        $habitat = $this->crearHabitat('Ruta 2');
        foreach ([701, 702, 703, 704, 705] as $id) {
            $this->crearPokemon($id, $habitat);
        }

        $aleatorio = (function (): float {
            static $pasos = null;
            $pasos ??= [0.1, 0.1, 0.1, 0.1, 0.9];

            return array_shift($pasos);
        });

        $equipo = $this->crearGenerador()->generar((int) $habitat->id, 1, nivelRival: 12, aleatorio: $aleatorio);

        $this->assertCount(5, $equipo);
        $formacion = array_map(fn (DatosPokemonBatalla $d): string => $d->posicion->value, $equipo);

        $this->assertContains(Posicion::VANGUARDIA->value, $formacion);
        $this->assertContains(Posicion::RETAGUARDIA->value, $formacion);
    }

    #[Test]
    public function ids_siguen_el_formato_ruta_con_indice_0_a_4(): void
    {
        $habitat = $this->crearHabitat('Ruta 3');
        foreach ([801, 802, 803] as $id) {
            $this->crearPokemon($id, $habitat);
        }

        // Peso igual 45/10: pasos que recorren los tres buckets con reemplazo.
        $aleatorio = (function (): float {
            static $pasos = null;
            $pasos ??= [0.1, 0.4, 0.9, 0.1, 0.9];

            return array_shift($pasos);
        });

        $equipo = $this->crearGenerador()->generar((int) $habitat->id, 1, nivelRival: 12, aleatorio: $aleatorio);

        $this->assertSame(
            ['ruta_'.$habitat->id.'_1_0', 'ruta_'.$habitat->id.'_1_1', 'ruta_'.$habitat->id.'_1_2', 'ruta_'.$habitat->id.'_1_3', 'ruta_'.$habitat->id.'_1_4'],
            array_map(fn (DatosPokemonBatalla $d): string => $d->id, $equipo),
        );
    }

    #[Test]
    public function devuelve_lista_vacia_cuando_el_pool_no_tiene_peso(): void
    {
        $habitat = $this->crearHabitat('Ruta 4');
        $this->crearPokemon(901, $habitat, captureRate: 0);

        $equipo = $this->crearGenerador()->generar((int) $habitat->id, 1, nivelRival: 12);

        $this->assertSame([], $equipo);
    }

    #[Test]
    public function devuelve_lista_vacia_cuando_el_pool_del_nivel_esta_vacio(): void
    {
        $habitat = $this->crearHabitat('Ruta 4B');
        // El pool solo existe en nivel 2; se pide nivel 1 → sin rivales.
        $this->crearPokemon(902, $habitat);
        $habitat->pokemon()->updateExistingPivot(902, ['level' => 2]);

        $equipo = $this->crearGenerador()->generar((int) $habitat->id, 1, nivelRival: 12);

        $this->assertSame([], $equipo);
    }

    #[Test]
    public function pondera_con_divisor_1_cuando_el_hatch_es_nulo_o_cero(): void
    {
        $habitat = $this->crearHabitat('Ruta 5');
        // hatch nulo → peso = 100/1 = 100; hatch 0 → peso = 100/1 = 100.
        $this->crearPokemon(911, $habitat, captureRate: 100, hatch: null);
        $this->crearPokemon(912, $habitat, captureRate: 100, hatch: 0);

        // 5 tiradas al 0.01: siempre el primer bucket (911).
        $aleatorio = (function (): float {
            static $pasos = null;
            $pasos ??= [0.01, 0.01, 0.01, 0.01, 0.01];

            return array_shift($pasos);
        });

        $equipo = $this->crearGenerador()->generar((int) $habitat->id, 1, nivelRival: 12, aleatorio: $aleatorio);

        $this->assertSame(
            [911, 911, 911, 911, 911],
            array_map(fn (DatosPokemonBatalla $d): int => $d->speciesId, $equipo),
        );
    }

    private function crearHabitat(string $nombre): Habitat
    {
        $province = Province::create(['name' => 'Kanto']);

        return Habitat::create(['province_id' => $province->id, 'name' => $nombre, 'peligro' => 1]);
    }

    private function crearPokemon(int $id, Habitat $habitat, int $captureRate = 45, ?int $hatch = 10): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $id,
            'name' => 'pokemon-'.$id,
            'species_id' => $id,
            'capture_rate' => $captureRate,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'hatch' => $hatch,
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

        $habitat->pokemon()->attach($pokemon->id, ['level' => 1]);

        return $pokemon;
    }
}
