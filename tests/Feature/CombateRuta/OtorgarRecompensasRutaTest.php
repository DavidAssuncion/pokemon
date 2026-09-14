<?php

declare(strict_types=1);

namespace Tests\Feature\CombateRuta;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Jobs\ActualizarPokedexJob;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Src\CombateRuta\App\OtorgarRecompensasRuta;
use Src\CombateRuta\App\RegistrarResultadoRuta;
use Src\CombateRuta\Domain\DataTransferObjects\ResultadoRuta;
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Tests\TestCase;

class OtorgarRecompensasRutaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['experiencia' => 10 * 20 ** 3]);
        $this->team = $this->crearEquipoJugador($this->user);
    }

    #[Test]
    public function victoria_otorga_recompensas_con_capturas_y_avistados(): void
    {
        Bus::fake();

        $persistir = $this->createMock(PersistirRecompensas::class);
        $persistir->expects($this->once())->method('persistir');

        $otorgar = new OtorgarRecompensasRuta(new CalculadorRecompensas(), $persistir);
        $pokemon = $this->crearPokemonRival(50);

        // ProbabilidadCaptura: chance = min(50,25)/255 ≈ 0.098; 0.05 ≤ 0.098 → captura.
        $resultado = $otorgar->otorgar(
            userId: (int) $this->user->id,
            teamId: (int) $this->team->id,
            speciesIdsRival: [(int) $pokemon->id],
            aleatorio: fn (): float => 0.05,
        );

        $this->assertInstanceOf(ResultadoRuta::class, $resultado);
        $this->assertTrue($resultado->victoria);
        $this->assertGreaterThan(0, $resultado->expTotal);
        $this->assertCount(1, $resultado->capturas);
        $this->assertSame((int) $pokemon->id, $resultado->capturas[0]->pokemonId);

        Bus::assertDispatched(ActualizarPokedexJob::class, 1);
    }

    #[Test]
    public function con_aleatorio_que_no_captura_la_victoria_no_incluye_capturas(): void
    {
        $persistir = $this->createMock(PersistirRecompensas::class);
        $persistir->expects($this->once())->method('persistir');

        $otorgar = new OtorgarRecompensasRuta(new CalculadorRecompensas(), $persistir);
        $pokemon = $this->crearPokemonRival(53);

        // 0.5 > 0.098 → no captura.
        $resultado = $otorgar->otorgar(
            userId: (int) $this->user->id,
            teamId: (int) $this->team->id,
            speciesIdsRival: [(int) $pokemon->id],
            aleatorio: fn (): float => 0.5,
        );

        $this->assertInstanceOf(ResultadoRuta::class, $resultado);
        $this->assertCount(0, $resultado->capturas);
    }

    #[Test]
    public function derrota_no_otorga_nada(): void
    {
        $persistir = $this->createMock(PersistirRecompensas::class);
        $persistir->expects($this->never())->method('persistir');

        $registrar = new RegistrarResultadoRuta(
            new OtorgarRecompensasRuta(new CalculadorRecompensas(), $persistir),
        );
        // El rival existe en el catálogo: si la derrota llegara al otorgador,
        // otorgaría recompensas (y el test fallaría).
        $pokemon = $this->crearPokemonRival(50);

        $resultado = $registrar->registrar(
            userId: (int) $this->user->id,
            teamId: (int) $this->team->id,
            speciesIdsRival: [(int) $pokemon->id],
            won: false,
        );

        $this->assertNull($resultado);
        $this->assertDatabaseCount('player_inventory', 0);
        $this->assertDatabaseCount('reclutables', 0);
    }

    #[Test]
    public function victoria_delega_en_el_otorgador_y_devuelve_el_modal(): void
    {
        $persistir = $this->createMock(PersistirRecompensas::class);
        $persistir->expects($this->once())->method('persistir');

        $registrar = new RegistrarResultadoRuta(
            new OtorgarRecompensasRuta(new CalculadorRecompensas(), $persistir),
        );
        $this->crearPokemonRival(54);

        $resultado = $registrar->registrar(
            userId: (int) $this->user->id,
            teamId: (int) $this->team->id,
            speciesIdsRival: [54],
            won: true,
            aleatorio: fn (): float => 0.05,
        );

        $this->assertInstanceOf(ResultadoRuta::class, $resultado);
        $this->assertTrue($resultado->victoria);
        $this->assertGreaterThan(0, $resultado->expTotal);
    }

    #[Test]
    public function ids_invalidos_y_duplicados_se_sanean_antes_de_otorgar(): void
    {
        $persistir = $this->createMock(PersistirRecompensas::class);
        $persistir->expects($this->once())->method('persistir');

        $otorgar = new OtorgarRecompensasRuta(new CalculadorRecompensas(), $persistir);
        $pokemon = $this->crearPokemonRival(55);

        // Duplicado + id inválido (0): solo el id válido cuenta una vez.
        $resultado = $otorgar->otorgar(
            userId: (int) $this->user->id,
            teamId: (int) $this->team->id,
            speciesIdsRival: [(int) $pokemon->id, (int) $pokemon->id, 0],
            aleatorio: fn (): float => 0.05,
        );

        $this->assertInstanceOf(ResultadoRuta::class, $resultado);
        $this->assertCount(1, $resultado->capturas);
    }

    private function crearPokemonRival(int $speciesId): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $speciesId,
            'name' => 'rival-'.$speciesId,
            'species_id' => $speciesId,
            'capture_rate' => 50,
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
        $team = Team::create(['name' => 'Equipo Ruta', 'user_id' => $user->id]);

        foreach ([1, 2] as $slot) {
            $pokemon = Pokemon::create([
                'id' => 2000 + $slot,
                'name' => 'jugador-ruta-'.$slot,
                'species_id' => 2000 + $slot,
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
                'nombre' => 'jugador-ruta-'.$slot,
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
