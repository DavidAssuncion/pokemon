<?php

declare(strict_types=1);

namespace Tests\Feature\Gimnasios;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\Gimnasios\App\GeneradorPokemonGimnasio;
use Src\Gimnasios\Domain\Collections\IntCollection;
use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;
use Tests\TestCase;

class GeneradorPokemonGimnasioTest extends TestCase
{
    use RefreshDatabase;

    private GeneradorPokemonGimnasio $generador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generador = new GeneradorPokemonGimnasio(
            new MapeadorPokemonBatalla(new GeneradorMovimientosTipo()),
            new GeneradorMovimientosTipo(),
        );
    }

    #[Test]
    public function test_gimnasio_64_64_todos_evs_a_64(): void
    {
        $this->crearPokemon(1, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);

        $equipo = $this->generador->generar(new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([1]),
            retaguardia: new IntCollection([]),
        ), 50, 64, 64);

        $this->assertCount(1, $equipo);
        $evs = $equipo[0]->evs;
        $this->assertSame(64.0, $evs->hp);
        $this->assertSame(64.0, $evs->attack);
        $this->assertSame(64.0, $evs->defense);
        $this->assertSame(64.0, $evs->spAtk);
        $this->assertSame(64.0, $evs->spDef);
        $this->assertSame(64.0, $evs->speed);
    }

    #[Test]
    public function test_lider_128_64_dos_mejores_stats_a_128_resto_64(): void
    {
        // stats: atk=90, spAtk=80, speed=75, hp=50, def=40, spDef=30
        // Top 2: atk(90), spAtk(80) → 128; resto → 64
        $this->crearPokemon(2, ['hp' => 50, 'atk' => 90, 'def' => 40, 'spAtk' => 80, 'spDef' => 30, 'speed' => 75]);

        $equipo = $this->generador->generar(new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([2]),
            retaguardia: new IntCollection([]),
        ), 50, 128, 64);

        $this->assertCount(1, $equipo);
        $evs = $equipo[0]->evs;
        $this->assertSame(64.0, $evs->hp);
        $this->assertSame(128.0, $evs->attack);
        $this->assertSame(64.0, $evs->defense);
        $this->assertSame(128.0, $evs->spAtk);
        $this->assertSame(64.0, $evs->spDef);
        $this->assertSame(64.0, $evs->speed);
    }

    #[Test]
    public function test_ruta_0_0_todos_evs_a_0(): void
    {
        $this->crearPokemon(3, ['hp' => 100, 'atk' => 90, 'def' => 80, 'spAtk' => 70, 'spDef' => 60, 'speed' => 50]);

        $equipo = $this->generador->generar(new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([3]),
            retaguardia: new IntCollection([]),
        ), 50, 0, 0);

        $this->assertCount(1, $equipo);
        $evs = $equipo[0]->evs;
        $this->assertSame(0.0, $evs->hp);
        $this->assertSame(0.0, $evs->attack);
        $this->assertSame(0.0, $evs->defense);
        $this->assertSame(0.0, $evs->spAtk);
        $this->assertSame(0.0, $evs->spDef);
        $this->assertSame(0.0, $evs->speed);
    }

    #[Test]
    public function test_respeta_vanguardia_y_retaguardia_del_dto(): void
    {
        $this->crearPokemon(268, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);
        $this->crearPokemon(266, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);
        $this->crearPokemon(900, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);

        $equipo = $this->generador->generar(new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([268, 266]),
            retaguardia: new IntCollection([900]),
        ), 50);

        $this->assertCount(3, $equipo);
        $this->assertSame(Posicion::VANGUARDIA, $equipo[0]->posicion);
        $this->assertSame(Posicion::VANGUARDIA, $equipo[1]->posicion);
        $this->assertSame(Posicion::RETAGUARDIA, $equipo[2]->posicion);
    }

    #[Test]
    public function test_duplicados_generan_dos_combatientes(): void
    {
        $this->crearPokemon(338, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);
        $this->crearPokemon(464, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);

        $equipo = $this->generador->generar(new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([338]),
            retaguardia: new IntCollection([464, 464]),
        ), 50);

        $this->assertCount(3, $equipo);
        $this->assertSame(Posicion::VANGUARDIA, $equipo[0]->posicion);
        $this->assertSame(Posicion::RETAGUARDIA, $equipo[1]->posicion);
        $this->assertSame(Posicion::RETAGUARDIA, $equipo[2]->posicion);
        $this->assertSame(464, $equipo[1]->speciesId);
        $this->assertSame(464, $equipo[2]->speciesId);
    }

    #[Test]
    public function test_equipo_de_cuatro_combatientes_dark_etapa_3(): void
    {
        $this->crearPokemon(560, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);
        $this->crearPokemon(461, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);
        $this->crearPokemon(861, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);

        $equipo = $this->generador->generar(new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([560]),
            retaguardia: new IntCollection([461, 861, 461]),
        ), 50);

        $this->assertCount(4, $equipo);
        $this->assertSame(Posicion::VANGUARDIA, $equipo[0]->posicion);
        $this->assertSame(Posicion::RETAGUARDIA, $equipo[1]->posicion);
        $this->assertSame(Posicion::RETAGUARDIA, $equipo[2]->posicion);
        $this->assertSame(Posicion::RETAGUARDIA, $equipo[3]->posicion);
    }

    #[Test]
    public function test_especie_inexistente_se_omite_del_equipo(): void
    {
        $this->crearPokemon(488, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);
        $this->crearPokemon(150, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);

        // 10002 (deoxys-defense) no existe en BD → se omite, quedan 488 y 150
        $equipo = $this->generador->generar(new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([488, 10002]),
            retaguardia: new IntCollection([150]),
        ), 50);

        $this->assertCount(2, $equipo);
        $this->assertSame(488, $equipo[0]->speciesId);
        $this->assertSame(Posicion::VANGUARDIA, $equipo[0]->posicion);
        $this->assertSame(150, $equipo[1]->speciesId);
        $this->assertSame(Posicion::RETAGUARDIA, $equipo[1]->posicion);
    }

    /**
     * @param  array{hp: int, atk: int, def: int, spAtk: int, spDef: int, speed: int}  $stats
     */
    private function crearPokemon(int $speciesId, array $stats): void
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
            StatEnum::HP->value => $stats['hp'],
            StatEnum::ATTACK->value => $stats['atk'],
            StatEnum::DEFENSE->value => $stats['def'],
            StatEnum::SPECIAL_ATTACK->value => $stats['spAtk'],
            StatEnum::SPECIAL_DEFENSE->value => $stats['spDef'],
            StatEnum::SPEED->value => $stats['speed'],
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
