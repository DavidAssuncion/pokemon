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
    public function genera_cinco_especies_unicas_cuando_el_pool_lo_permita(): void
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

        $this->assertCount(5, $equipo);
        $idsGenerados = array_map(
            fn (DatosPokemonBatalla $d): int => $d->speciesId,
            $equipo
        );
        $this->assertSame(
            array_values(array_unique($idsGenerados)),
            $idsGenerados,
        );
        $this->assertCount(5, $idsGenerados);
    }

    #[Test]
    public function genera_cinco_pokemon_con_repeticion_cuando_el_pool_tiene_menos_de_cinco(): void
    {
        $habitat = $this->crearHabitat('Cueva');
        $ids = [201, 202, 203];

        foreach ($ids as $id) {
            $this->crearPokemon($id, $habitat);
        }

        $generador = new GeneradorEquipoEntrenador(
            new MapeadorPokemonBatalla(new GeneradorMovimientosTipo()),
            new ClasificadorOfensivaDefensiva(),
        );

        // Pesos iguales (45/10 = 4.5 cada uno): el aleatorio decide el bucket.
        // 0.01 → 201, 0.49 → 202, 0.99 → 203 (acumulado 33 %, 67 %, 100 %).
        $aleatorio = (function (): float {
            static $pasos = null;
            $pasos ??= [0.01, 0.49, 0.99, 0.01, 0.49];

            return array_shift($pasos);
        });

        $equipo = $generador->generar((int) $habitat->id, 1, 0, '2026-09-12', aleatorio: $aleatorio);

        $idsGenerados = array_map(
            fn (DatosPokemonBatalla $d): int => $d->speciesId,
            $equipo
        );

        $this->assertCount(5, $equipo);
        $this->assertSame([201, 202, 203, 201, 202], $idsGenerados);
    }

    #[Test]
    public function con_pool_menor_a_cinco_repite_ponderado_por_capture_rate(): void
    {
        $habitat = $this->crearHabitat('Marisma');
        // Pesos capture_rate/hatch: 200/10=20, 100/10=10, 10/10=1 (total 31).
        $this->crearPokemon(501, $habitat, captureRate: 200);
        $this->crearPokemon(502, $habitat, captureRate: 100);
        $this->crearPokemon(503, $habitat, captureRate: 10);

        $generador = new GeneradorEquipoEntrenador(
            new MapeadorPokemonBatalla(new GeneradorMovimientosTipo()),
            new ClasificadorOfensivaDefensiva(),
        );

        // Buckets acumulados: 501 ≤ 20/31 ≈ 0.645; 502 ≤ 30/31 ≈ 0.968; 503 > 0.968.
        $aleatorio = (function (): float {
            static $pasos = null;
            $pasos ??= [0.05, 0.05, 0.7, 0.7, 0.99];

            return array_shift($pasos);
        });

        $equipo = $generador->generar((int) $habitat->id, 1, 0, '2026-09-12', aleatorio: $aleatorio);

        $idsGenerados = array_map(
            fn (DatosPokemonBatalla $d): int => $d->speciesId,
            $equipo
        );

        // El de mayor capture_rate aparece el doble de veces que el segundo,
        // y el de menor peso solo una vez (con reemplazo, dependiente del peso).
        $this->assertSame([501, 501, 502, 502, 503], $idsGenerados);
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

        $ids1 = array_map(fn (DatosPokemonBatalla $d): string => $d->id, $equipo1);
        $ids2 = array_map(fn (DatosPokemonBatalla $d): string => $d->id, $equipo2);

        $this->assertSame($ids1, $ids2);
    }

    #[Test]
    public function los_ids_mantienen_el_formato_entrenador_con_indice_0_a_4(): void
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
        for ($i = 0; $i < 5; $i++) {
            $idsEsperados[] = "entrenador_{$habitat->id}_1_2_{$i}";
        }

        $this->assertSame(
            $idsEsperados,
            array_map(fn (DatosPokemonBatalla $d): string => $d->id, $equipo),
        );
    }

    private function crearPokemon(int $id, Habitat $habitat, int $captureRate = 45, int $hatch = 10): Pokemon
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
