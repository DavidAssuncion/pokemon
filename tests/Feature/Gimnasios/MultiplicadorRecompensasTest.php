<?php

declare(strict_types=1);

namespace Tests\Feature\Gimnasios;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\CombateEntrenadores\App\OtorgarRecompensasEntrenador;
use Src\CombateEntrenadores\Domain\DataTransferObjects\DatosModalVictoria;
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Shared\Domain\MultiplicadoresDificultad;
use Src\Shared\Domain\PoliticaIvsEvs;
use Tests\TestCase;

class MultiplicadorRecompensasTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Pokemon $pokemon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['experiencia' => 10 * 20 ** 3]);
        $this->team = $this->crearEquipoJugador($this->user);
        $this->pokemon = $this->crearPokemonCompleto(25);
    }

    #[Test]
    public function test_gimnasio_otorga_cinco_veces_mas_que_entrenador(): void
    {
        // Entrenador: usa el multiplicador por defecto (2.0)
        $entrenador = $this->otorgar(multiplicador: null);
        // Gimnasio: pasa multiplicador 10.0
        $gimnasio = $this->otorgar(multiplicador: 10.0);

        // exp_total con ×10 debe ser 5× el de ×2 (10/2 = 5)
        $this->assertNotNull($entrenador);
        $this->assertNotNull($gimnasio);
        $this->assertSame(5 * $entrenador->expTotal, $gimnasio->expTotal);
    }

    #[Test]
    public function gimnasio_top_uno_no_cambia_las_recompensas(): void
    {
        $pokemon = $this->crearPokemonConDificultad(26);

        $base = $this->otorgarConDificultad($pokemon, null);
        $topUno = $this->otorgarConDificultad(
            $pokemon,
            MultiplicadoresDificultad::para(PoliticaIvsEvs::perfilGimnasio(3)),
        );

        $this->assertNotNull($base);
        $this->assertNotNull($topUno);
        $this->assertSame($base->toArray(), $topUno->toArray());
    }

    #[Test]
    public function gimnasio_top_dos_aplica_multiplicadores_de_perfil(): void
    {
        $pokemon = $this->crearPokemonConDificultad(26);

        $topDos = $this->otorgarConDificultad(
            $pokemon,
            MultiplicadoresDificultad::para(PoliticaIvsEvs::perfilGimnasio(9)),
        );

        $this->assertNotNull($topDos);
        $caramelos = $topDos->caramelos->toArray();

        $this->assertSame(33, $caramelos[0]['cantidad']);
        $this->assertSame(18, $caramelos[1]['cantidad']);
        $this->assertSame(9, $caramelos[2]['cantidad']);
    }

    #[Test]
    public function gimnasio_completo_aplica_multiplicadores_de_perfil(): void
    {
        $pokemon = $this->crearPokemonConDificultad(26);

        $completo = $this->otorgarConDificultad(
            $pokemon,
            MultiplicadoresDificultad::para(PoliticaIvsEvs::perfilGimnasio(15)),
        );

        $this->assertNotNull($completo);
        $caramelos = $completo->caramelos->toArray();

        $this->assertSame(33, $caramelos[0]['cantidad']);
        $this->assertSame(28, $caramelos[1]['cantidad']);
        $this->assertSame(12, $caramelos[2]['cantidad']);
    }

    #[Test]
    public function el_lider_sube_un_tier_en_los_multiplicadores(): void
    {
        $pokemon = $this->crearPokemonConDificultad(26);

        $noLider = $this->otorgarConDificultad(
            $pokemon,
            MultiplicadoresDificultad::para(PoliticaIvsEvs::perfilGimnasio(6)),
        );
        $lider = $this->otorgarConDificultad(
            $pokemon,
            MultiplicadoresDificultad::para(PoliticaIvsEvs::perfilGimnasio(6, true)),
        );
        $liderTope = $this->otorgarConDificultad(
            $pokemon,
            MultiplicadoresDificultad::para(PoliticaIvsEvs::perfilGimnasio(18, true)),
        );

        $this->assertNotNull($noLider);
        $this->assertNotNull($lider);
        $this->assertNotNull($liderTope);

        $this->assertSame(10, $noLider->caramelos->toArray()[1]['cantidad']);
        $this->assertSame(18, $lider->caramelos->toArray()[1]['cantidad']);
        $this->assertSame(28, $liderTope->caramelos->toArray()[1]['cantidad']);
    }

    #[Test]
    public function entrenador_nivel_dos_aplica_multiplicadores_top_dos(): void
    {
        $pokemon = $this->crearPokemonConDificultad(26);

        $entrenador = $this->otorgarConDificultad(
            $pokemon,
            MultiplicadoresDificultad::para(PoliticaIvsEvs::perfilEntrenador(2)),
            multiplicador: 2.0,
        );

        $this->assertNotNull($entrenador);
        $caramelos = $entrenador->caramelos->toArray();

        $this->assertSame(6, $caramelos[0]['cantidad']);
        $this->assertSame(3, $caramelos[1]['cantidad']);
        $this->assertSame(1, $caramelos[2]['cantidad']);
    }

    private function otorgarConDificultad(
        Pokemon $pokemon,
        ?MultiplicadoresDificultad $dificultad,
        float $multiplicador = OtorgarRecompensasEntrenador::MULTIPLICADOR_GIMNASIO,
    ): ?DatosModalVictoria {
        $persistir = $this->createMock(PersistirRecompensas::class);
        $persistir->expects($this->once())->method('persistir');

        $otorgar = new OtorgarRecompensasEntrenador(new CalculadorRecompensas(), $persistir);

        // Contrato de producción: `array_unique` cuenta una vez cada especie rival.
        return $otorgar->otorgar(
            userId: (int) $this->user->id,
            teamId: (int) $this->team->id,
            speciesIdsRival: [(int) $pokemon->id],
            nivelEntrenador: 10,
            multiplicador: $multiplicador,
            dificultad: $dificultad,
        );
    }

    private function otorgar(?float $multiplicador): ?DatosModalVictoria
    {
        $calculador = new CalculadorRecompensas();
        $persistir = $this->createMock(PersistirRecompensas::class);

        $otorgar = new OtorgarRecompensasEntrenador($calculador, $persistir);

        $args = [
            'userId' => (int) $this->user->id,
            'teamId' => (int) $this->team->id,
            'speciesIdsRival' => [(int) $this->pokemon->id],
            'nivelEntrenador' => 10,
        ];

        if ($multiplicador !== null) {
            $args['multiplicador'] = $multiplicador;
        }

        return $otorgar->otorgar(...$args);
    }

    private function crearPokemonConDificultad(int $speciesId): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $speciesId,
            'name' => 'pokemon-dificultad-'.$speciesId,
            'species_id' => $speciesId,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 700,
        ]);

        PokemonStat::create([
            'pokemon_id' => $pokemon->id,
            'stat' => StatEnum::HP->value,
            'base_stat' => 50,
            'effort' => 1,
        ]);

        PokemonType::create([
            'pokemon_id' => $pokemon->id,
            'type' => TipoEnum::NORMAL,
            'slot' => 1,
        ]);

        return $pokemon;
    }

    private function crearPokemonCompleto(int $speciesId): Pokemon
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

        PokemonStat::create([
            'pokemon_id' => $pokemon->id,
            'stat' => StatEnum::HP->value,
            'base_stat' => 50,
            'effort' => 0,
        ]);
        PokemonStat::create([
            'pokemon_id' => $pokemon->id,
            'stat' => StatEnum::ATTACK->value,
            'base_stat' => 60,
            'effort' => 0,
        ]);

        PokemonType::create([
            'pokemon_id' => $pokemon->id,
            'type' => TipoEnum::NORMAL,
            'slot' => 1,
        ]);

        return $pokemon;
    }

    private function crearEquipoJugador(User $user): Team
    {
        $team = Team::create(['name' => 'Equipo Test', 'user_id' => $user->id]);

        foreach ([1, 2, 3] as $slot) {
            $pokemon = Pokemon::create([
                'id' => 1000 + $slot,
                'name' => 'jugador-'.$slot,
                'species_id' => 1000 + $slot,
                'capture_rate' => 45,
                'base_experience' => 64,
                'height' => 7,
                'weight' => 69,
            ]);

            foreach (StatEnum::cases() as $stat) {
                PokemonStat::create([
                    'pokemon_id' => $pokemon->id,
                    'stat' => $stat->value,
                    'base_stat' => 100,
                    'effort' => 0,
                ]);
            }

            PokemonType::create([
                'pokemon_id' => $pokemon->id,
                'type' => TipoEnum::NORMAL,
                'slot' => 1,
            ]);

            $reclutado = Reclutado::create([
                'user_id' => $user->id,
                'nombre' => 'jugador-'.$slot,
                'pokemon_id' => $pokemon->id,
                'exp' => ['exp' => 100],
                'obj_equipados' => [],
                'movimientos' => [],
            ]);

            TeamMember::create([
                'team_id' => $team->id,
                'pokemon_id' => $reclutado->id,
                'slot' => $slot,
                'behavior' => 'VANGUARDIA',
            ]);
        }

        return $team;
    }
}
