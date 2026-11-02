<?php

declare(strict_types=1);

namespace Tests\Feature\CombateEntrenadores;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\CombateEntrenadores\App\GeneradorEquipoEntrenador;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\Shared\Domain\ClasificadorOfensivaDefensiva;
use Tests\TestCase;

class GeneradorEquipoEntrenadorTest extends TestCase
{
    use RefreshDatabase;

    private function crearHabitat(string $nombre): Habitat
    {
        $province = Province::create(['name' => 'Kanto']);

        return Habitat::create(['province_id' => $province->id, 'name' => $nombre, 'peligro' => 1]);
    }

    #[Test]
    public function genera_tres_especies_unicas_cuando_el_pool_lo_permita(): void
    {
        $habitat = $this->crearHabitat('Bosque');
        $ids = [101, 102, 103, 104, 105, 106];

        foreach ($ids as $id) {
            $this->crearPokemon($id, $habitat);
        }

        $generador = new GeneradorEquipoEntrenador(
            new MapeadorPokemonBatalla(new GeneradorMovimientosTipo()),
            new ClasificadorOfensivaDefensiva(),
        );

        $equipo = $generador->generar((int) $habitat->id, 1, 0, '2026-09-12');

        $this->assertCount(3, $equipo);
        $idsGenerados = array_map(
            fn (DatosPokemonBatalla $d): int => $d->speciesId,
            $equipo->all()
        );
        $this->assertSame(
            array_values(array_unique($idsGenerados)),
            $idsGenerados,
        );
        $this->assertCount(3, $idsGenerados);
    }

    #[Test]
    public function genera_tres_pokemon_con_repeticion_cuando_el_pool_tiene_menos_de_tres(): void
    {
        $habitat = $this->crearHabitat('Cueva');
        $ids = [201, 202];

        foreach ($ids as $id) {
            $this->crearPokemon($id, $habitat);
        }

        $generador = new GeneradorEquipoEntrenador(
            new MapeadorPokemonBatalla(new GeneradorMovimientosTipo()),
            new ClasificadorOfensivaDefensiva(),
        );

        // Pesos iguales (45/10 = 4.5 cada uno): el aleatorio decide el bucket.
        // 0.01 → 201, 0.99 → 202 (acumulado 50 %, 100 %).
        $aleatorio = (function (): float {
            static $pasos = null;
            $pasos ??= [0.01, 0.99, 0.01];

            return array_shift($pasos);
        });

        $equipo = $generador->generar((int) $habitat->id, 1, 0, '2026-09-12', aleatorio: $aleatorio);

        $idsGenerados = array_map(
            fn (DatosPokemonBatalla $d): int => $d->speciesId,
            $equipo->all()
        );

        $this->assertCount(3, $equipo);
        $this->assertSame([201, 202, 201], $idsGenerados);
    }

    #[Test]
    public function con_pool_menor_a_tres_repite_ponderado_por_capture_rate(): void
    {
        $habitat = $this->crearHabitat('Marisma');
        // Pesos capture_rate/hatch: 200/10=20, 100/10=10 (total 30).
        $this->crearPokemon(501, $habitat, captureRate: 200);
        $this->crearPokemon(502, $habitat, captureRate: 100);

        $generador = new GeneradorEquipoEntrenador(
            new MapeadorPokemonBatalla(new GeneradorMovimientosTipo()),
            new ClasificadorOfensivaDefensiva(),
        );

        // Buckets acumulados: 501 ≤ 20/30 ≈ 0.667; 502 > 0.667.
        $aleatorio = (function (): float {
            static $pasos = null;
            $pasos ??= [0.05, 0.05, 0.7];

            return array_shift($pasos);
        });

        $equipo = $generador->generar((int) $habitat->id, 1, 0, '2026-09-12', aleatorio: $aleatorio);

        $idsGenerados = array_map(
            fn (DatosPokemonBatalla $d): int => $d->speciesId,
            $equipo->all()
        );

        // El de mayor capture_rate aparece el doble de veces que el segundo
        // (con reemplazo, dependiente del peso).
        $this->assertSame([501, 501, 502], $idsGenerados);
    }

    #[Test]
    public function usa_el_mismo_equipo_para_el_mismo_dia_determinista(): void
    {
        $habitat = $this->crearHabitat('Pradera');
        $ids = [301, 302, 303, 304, 305, 306, 307];

        foreach ($ids as $id) {
            $this->crearPokemon($id, $habitat);
        }

        $generador = new GeneradorEquipoEntrenador(
            new MapeadorPokemonBatalla(new GeneradorMovimientosTipo()),
            new ClasificadorOfensivaDefensiva(),
        );

        $fecha = '2026-09-12';

        $equipo1 = $generador->generar((int) $habitat->id, 2, 1, $fecha);
        $equipo2 = $generador->generar((int) $habitat->id, 2, 1, $fecha);

        $ids1 = array_map(fn (DatosPokemonBatalla $d): string => $d->id, $equipo1->all());
        $ids2 = array_map(fn (DatosPokemonBatalla $d): string => $d->id, $equipo2->all());

        $this->assertSame($ids1, $ids2);
    }

    #[Test]
    public function los_ids_mantienen_el_formato_entrenador_con_indice_0_a_2(): void
    {
        $habitat = $this->crearHabitat('Río');
        $ids = [401, 402, 403, 404, 405];

        foreach ($ids as $id) {
            $this->crearPokemon($id, $habitat);
        }

        $generador = new GeneradorEquipoEntrenador(
            new MapeadorPokemonBatalla(new GeneradorMovimientosTipo()),
            new ClasificadorOfensivaDefensiva(),
        );

        $equipo = $generador->generar((int) $habitat->id, 1, 2, '2026-09-12');

        $idsEsperados = [];
        for ($i = 0; $i < 3; $i++) {
            $idsEsperados[] = "entrenador_{$habitat->id}_1_2_{$i}";
        }

        $this->assertSame(
            $idsEsperados,
            array_map(fn (DatosPokemonBatalla $d): string => $d->id, $equipo->all()),
        );
    }


    #[Test]
    public function nivel_1_aplica_top_uno_con_el_mejor_stat_al_maximo_y_el_resto_a_cero(): void
    {
        $habitat = $this->crearHabitatConPokemon('Entrenador P1', 1);

        $equipo = $this->crearGenerador()->generar((int) $habitat->id, 1, 0, '2026-09-12');

        $this->assertCount(3, $equipo);
        foreach ($equipo->all() as $datos) {
            $this->assertSame(
                ['hp' => 15, 'atk' => 0, 'def' => 0, 'spAtk' => 0, 'spDef' => 0, 'speed' => 0],
                $datos->ivs->toArray(),
            );
            $this->assertSame(
                ['hp' => 255, 'atk' => 0, 'def' => 0, 'spAtk' => 0, 'spDef' => 0, 'speed' => 0],
                $datos->evs->toArray(),
            );
        }
    }

    #[Test]
    public function nivel_2_aplica_top_dos_y_mitad_al_resto(): void
    {
        $habitat = $this->crearHabitatConPokemon('Entrenador P2', 2);

        $equipo = $this->crearGenerador()->generar((int) $habitat->id, 2, 0, '2026-09-12');

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
        $habitat = $this->crearHabitatConPokemon('Entrenador P3', 3);

        $equipo = $this->crearGenerador()->generar((int) $habitat->id, 3, 0, '2026-09-12');

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

    private function crearGenerador(): GeneradorEquipoEntrenador
    {
        return new GeneradorEquipoEntrenador(
            new MapeadorPokemonBatalla(new GeneradorMovimientosTipo()),
            new ClasificadorOfensivaDefensiva(),
        );
    }

    private function crearHabitatConPokemon(string $nombre, int $nivel): Habitat
    {
        $habitat = $this->crearHabitat($nombre);

        foreach ([9101, 9102, 9103] as $id) {
            $this->crearPokemon($id, $habitat, level: $nivel);
        }

        return $habitat;
    }

    private function crearPokemon(int $id, Habitat $habitat, int $captureRate = 45, int $hatch = 10, int $level = 1): Pokemon
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

        $habitat->pokemon()->attach($pokemon->id, ['level' => $level]);

        return $pokemon;
    }
}
