<?php

declare(strict_types=1);

namespace Tests\Feature\Exploraciones;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\PlayerTypePath;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\Exploraciones\App\CombateExploracion;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Domain\Sendas\MapaSendaJugador;
use Tests\TestCase;

class CombateExploracionSendaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function el_explorador_recibe_la_senda_del_dueno_y_el_salvaje_no(): void
    {
        $user = User::factory()->create();

        PlayerTypePath::query()->create([
            'user_id' => $user->id,
            'type_id' => TipoEnum::NORMAL->value,
            'exp' => 72_000,
            'level' => 5,
        ]);

        $pokemonExplorador = $this->crearPokemon(9101, 'explorador');
        $pokemonSalvaje = $this->crearPokemon(9102, 'salvaje');

        $reclutado = Reclutado::create([
            'user_id' => $user->id,
            'nombre' => 'Explorador',
            'pokemon_id' => $pokemonExplorador->id,
            'exp' => ['total' => 10 * 20 ** 3],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        $espia = new MapeadorSendaEspia(new GeneradorMovimientosTipo());
        $combate = new CombateExploracion($espia);

        $combate->combatir($reclutado, $pokemonSalvaje, 5);

        $this->assertNotNull($espia->datosExplorador);
        $this->assertNotNull($espia->datosSalvaje);

        $equipoExplorador = EquipoBatalla::fromData(
            new DatosPokemonBatallaCollection([$espia->datosExplorador]),
            'Explorador',
        );
        $equipoSalvaje = EquipoBatalla::fromData(
            new DatosPokemonBatallaCollection([$espia->datosSalvaje]),
            'Salvaje',
        );

        $this->assertSame(
            [TipoEnum::NORMAL->value => 5],
            $equipoExplorador->combatientesCollection()->primero()?->senda()->toArray(),
        );
        $this->assertSame(
            [],
            $equipoSalvaje->combatientesCollection()->primero()?->senda()->toArray(),
        );
    }

    private function crearPokemon(int $id, string $nombre): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $id,
            'name' => $nombre,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $stats = [
            StatEnum::HP->value => 100,
            StatEnum::ATTACK->value => 100,
            StatEnum::DEFENSE->value => 60,
            StatEnum::SPECIAL_ATTACK->value => 60,
            StatEnum::SPECIAL_DEFENSE->value => 60,
            StatEnum::SPEED->value => 60,
        ];

        foreach ($stats as $stat => $valor) {
            PokemonStat::create([
                'pokemon_id' => $pokemon->id,
                'stat' => $stat,
                'base_stat' => $valor,
                'effort' => 0,
            ]);
        }

        PokemonType::create([
            'pokemon_id' => $pokemon->id,
            'type' => TipoEnum::NORMAL,
            'slot' => 1,
        ]);

        return $pokemon;
    }
}

/**
 * Espía el mapa de Senda que CombateExploracion pasa a cada combatiente.
 */
final class MapeadorSendaEspia extends MapeadorPokemonBatalla
{
    public ?DatosPokemonBatalla $datosExplorador = null;

    public ?DatosPokemonBatalla $datosSalvaje = null;

    /**
     * @param  list<string>|null  $movimientosSeleccionados
     */
    public function desdePokemon(
        Pokemon $pokemon,
        string $id,
        string $nombre,
        Posicion $posicion,
        bool $shiny = false,
        ?string $item = null,
        ?int $nivel = null,
        ?StatsValue $evs = null,
        ?StatsValue $ivs = null,
        PosturaCombate $postura = PosturaCombate::ATACAR,
        ?array $movimientosSeleccionados = null,
        ?int $habilidadActivaId = null,
        ?int $nivelDesbloqueo = null,
        ?MapaSendaJugador $senda = null,
    ): DatosPokemonBatalla {
        $datos = parent::desdePokemon(
            $pokemon,
            $id,
            $nombre,
            $posicion,
            $shiny,
            $item,
            $nivel,
            $evs,
            $ivs,
            $postura,
            $movimientosSeleccionados,
            $habilidadActivaId,
            $nivelDesbloqueo,
            $senda,
        );

        if (str_starts_with($id, 'salvaje_')) {
            $this->datosSalvaje = $datos;
        } else {
            $this->datosExplorador = $datos;
        }

        return $datos;
    }
}
