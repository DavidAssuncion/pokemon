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
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\Domain\CalculadorRecompensas;
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
        $this->assertSame(5 * $entrenador['exp_total'], $gimnasio['exp_total']);
    }

    /**
     * @return array{
     *     exp_total: int,
     *     exp_miembro: int,
     *     caramelos: list<array{nombre: string, imagen: string, cantidad: int}>
     * }
     */
    private function otorgar(?float $multiplicador): array
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

        /** @var array{
         *     exp_total: int,
         *     exp_miembro: int,
         *     caramelos: list<array{nombre: string, imagen: string, cantidad: int}>
         * } $recompensas
         */
        $recompensas = $otorgar->otorgar(...$args);

        return $recompensas;
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
