<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\PlayerTypePath;
use App\Models\User;
use App\Support\CargarMapaSendaJugador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\Shared\Tipos\TipoPokemon;
use Tests\TestCase;

class CargarMapaSendaJugadorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function deriva_el_nivel_de_la_experiencia_e_ignora_la_columna_level(): void
    {
        $user = User::factory()->create();

        PlayerTypePath::query()->create([
            'user_id' => $user->id,
            'type_id' => TipoPokemon::FUEGO->value,
            'exp' => 9_050,
            'level' => 4,
        ]);

        $mapa = app(CargarMapaSendaJugador::class)->para((int) $user->id);

        $this->assertSame([TipoPokemon::FUEGO->value => 2], $mapa->toArray());
    }

    #[Test]
    public function deriva_el_nivel_cinco_desde_la_experiencia_real(): void
    {
        $user = User::factory()->create();

        PlayerTypePath::query()->create([
            'user_id' => $user->id,
            'type_id' => TipoPokemon::AGUA->value,
            'exp' => 72_000,
            'level' => 1,
        ]);

        $mapa = app(CargarMapaSendaJugador::class)->para((int) $user->id);

        $this->assertSame([TipoPokemon::AGUA->value => 5], $mapa->toArray());
    }
    #[Test]
    public function propaga_los_beneficios_configurados_al_mapa(): void
    {
        config(['sendas.beneficios' => [4 => 0.10, 5 => 0.15]]);

        $user = User::factory()->create();

        PlayerTypePath::query()->create([
            'user_id' => $user->id,
            'type_id' => TipoPokemon::AGUA->value,
            'exp' => 72_000,
            'level' => 5,
        ]);

        $mapa = app(CargarMapaSendaJugador::class)->para((int) $user->id);

        $this->assertSame(0.15, $mapa->bonoDano(TipoPokemon::AGUA));
        $this->assertSame(0.15, $mapa->bonoDefensa(TipoPokemon::AGUA));
        $this->assertSame(0.15, $mapa->bonoCaramelo(TipoPokemon::AGUA));
    }
}
