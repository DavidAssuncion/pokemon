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
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\Gimnasios\App\GeneradorPokemonGimnasio;
use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;
use Src\Shared\Collections\IntCollection;
use Src\Shared\Domain\PerfilIvsEvs;
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
        );
    }

    #[Test]
    public function test_perfil_por_defecto_es_top_uno(): void
    {
        $this->crearPokemon(1, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);

        $equipo = $this->generador->generar(new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([1]),
            retaguardia: new IntCollection([]),
        ), 50);

        // Escalado 3v3: un DTO de 1 solo combatiente se repite cíclicamente hasta 3.
        // TopUno: el mejor stat base (atk, desempate por orden fijo) recibe el máximo.
        $this->assertCount(3, $equipo);
        $evs = $equipo->en(0)->evs;
        $this->assertSame(0.0, $evs->hp);
        $this->assertSame(255.0, $evs->attack);
        $this->assertSame(0.0, $evs->defense);
        $this->assertSame(0.0, $evs->spAtk);
        $this->assertSame(0.0, $evs->spDef);
        $this->assertSame(0.0, $evs->speed);
    }

    #[Test]
    public function test_perfil_top_dos_reparte_maximo_y_mitad(): void
    {
        // stats: atk=90, spAtk=80, speed=75, hp=50, def=40, spDef=30
        // Top 2: atk(90), spAtk(80) → máximos; resto → mitad.
        $this->crearPokemon(2, ['hp' => 50, 'atk' => 90, 'def' => 40, 'spAtk' => 80, 'spDef' => 30, 'speed' => 75]);

        $equipo = $this->generador->generar(new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([2]),
            retaguardia: new IntCollection([]),
        ), 50, PerfilIvsEvs::TopDos);

        $this->assertCount(3, $equipo);
        $evs = $equipo->en(0)->evs;
        $this->assertSame(128.0, $evs->hp);
        $this->assertSame(255.0, $evs->attack);
        $this->assertSame(128.0, $evs->defense);
        $this->assertSame(255.0, $evs->spAtk);
        $this->assertSame(128.0, $evs->spDef);
        $this->assertSame(128.0, $evs->speed);
    }

    #[Test]
    public function test_perfil_completo_maximiza_todos_los_evs(): void
    {
        $this->crearPokemon(3, ['hp' => 100, 'atk' => 90, 'def' => 80, 'spAtk' => 70, 'spDef' => 60, 'speed' => 50]);

        $equipo = $this->generador->generar(new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([3]),
            retaguardia: new IntCollection([]),
        ), 50, PerfilIvsEvs::Completo);

        $this->assertCount(3, $equipo);
        $evs = $equipo->en(0)->evs;
        $this->assertSame(255.0, $evs->hp);
        $this->assertSame(255.0, $evs->attack);
        $this->assertSame(255.0, $evs->defense);
        $this->assertSame(255.0, $evs->spAtk);
        $this->assertSame(255.0, $evs->spDef);
        $this->assertSame(255.0, $evs->speed);
    }

    #[Test]
    public function test_respeta_vanguardia_y_retaguardia_del_dto(): void
    {
        $this->crearPokemon(268, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);
        $this->crearPokemon(266, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);
        $this->crearPokemon(900, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);

        // Roster de 3: se devuelve tal cual (vanguardia primero, luego retaguardia).
        $equipo = $this->generador->generar(new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([268, 266]),
            retaguardia: new IntCollection([900]),
        ), 50);

        $this->assertCount(3, $equipo);
        $this->assertSame(Posicion::VANGUARDIA, $equipo->en(0)->posicion);
        $this->assertSame(Posicion::VANGUARDIA, $equipo->en(1)->posicion);
        $this->assertSame(Posicion::RETAGUARDIA, $equipo->en(2)->posicion);
        $this->assertSame([268, 266, 900], array_map(
            fn (DatosPokemonBatalla $p): int => $p->speciesId,
            $equipo->all(),
        ));
    }

    #[Test]
    public function test_duplicados_generan_dos_combatientes(): void
    {
        $this->crearPokemon(338, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);
        $this->crearPokemon(464, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);

        // Duplicados: [V,R1,R2] → 3 combatientes (V + dos R de la misma especie).
        $equipo = $this->generador->generar(new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([338]),
            retaguardia: new IntCollection([464, 464]),
        ), 50);

        $this->assertCount(3, $equipo);
        $this->assertSame(Posicion::VANGUARDIA, $equipo->en(0)->posicion);
        $this->assertSame(Posicion::RETAGUARDIA, $equipo->en(1)->posicion);
        $this->assertSame(Posicion::RETAGUARDIA, $equipo->en(2)->posicion);
        $this->assertSame([338, 464, 464], array_map(
            fn (DatosPokemonBatalla $p): int => $p->speciesId,
            $equipo->all(),
        ));
    }

    #[Test]
    public function test_roster_mayor_a_tres_se_recorta_a_tres(): void
    {
        $this->crearPokemon(560, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);
        $this->crearPokemon(461, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);
        $this->crearPokemon(861, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);

        // dark etapa 3: [V(560), R(461), R(861), R(461)] → recorta a los 3 primeros.
        $equipo = $this->generador->generar(new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([560]),
            retaguardia: new IntCollection([461, 861, 461]),
        ), 50);

        $this->assertCount(3, $equipo);
        $this->assertSame(Posicion::VANGUARDIA, $equipo->en(0)->posicion);
        $this->assertSame(Posicion::RETAGUARDIA, $equipo->en(1)->posicion);
        $this->assertSame(Posicion::RETAGUARDIA, $equipo->en(2)->posicion);
        $this->assertSame([560, 461, 861], array_map(
            fn (DatosPokemonBatalla $p): int => $p->speciesId,
            $equipo->all(),
        ));
    }

    #[Test]
    public function test_roster_vacio_devuelve_equipo_vacio(): void
    {
        $equipo = $this->generador->generar(new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([]),
            retaguardia: new IntCollection([]),
        ), 50);

        $this->assertTrue($equipo->isEmpty());
    }

    #[Test]
    public function test_especie_inexistente_se_omite_del_equipo(): void
    {
        $this->crearPokemon(488, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);
        $this->crearPokemon(150, ['hp' => 50, 'atk' => 60, 'def' => 50, 'spAtk' => 60, 'spDef' => 50, 'speed' => 55]);

        // 10002 (deoxys-defense) no existe en BD → se omite; el roster [488(V),150(R)] escala a 3.
        $equipo = $this->generador->generar(new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([488, 10002]),
            retaguardia: new IntCollection([150]),
        ), 50);

        $this->assertCount(3, $equipo);
        $this->assertSame([488, 150, 488], array_map(
            fn (DatosPokemonBatalla $p): int => $p->speciesId,
            $equipo->all(),
        ));
        $this->assertSame(Posicion::VANGUARDIA, $equipo->en(0)->posicion);
        $this->assertSame(Posicion::RETAGUARDIA, $equipo->en(1)->posicion);
        $this->assertSame(Posicion::VANGUARDIA, $equipo->en(2)->posicion);
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
