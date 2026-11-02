<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Sendas;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Sendas\MapaSendaJugador;
use Src\Shared\Domain\Sendas\NivelSenda;
use Src\Shared\Tipos\TipoPokemon;

class MapaSendaJugadorTest extends TestCase
{
    #[Test]
    public function vacio_devuelve_n1_sin_bonos_y_array_vacio(): void
    {
        $mapa = MapaSendaJugador::vacio();

        $this->assertSame(NivelSenda::N1, $mapa->nivel(TipoPokemon::FUEGO));
        $this->assertSame(0.0, $mapa->bonoDano(TipoPokemon::FUEGO));
        $this->assertSame(0.0, $mapa->bonoDefensa(TipoPokemon::FUEGO));
        $this->assertSame(0.0, $mapa->bonoCaramelo(TipoPokemon::FUEGO));
        $this->assertSame(0.0, $mapa->bonoCaptura([TipoPokemon::FUEGO, TipoPokemon::AGUA]));
        $this->assertSame([], $mapa->toArray());
    }

    #[Test]
    public function desde_array_conserva_niveles_4_y_5(): void
    {
        $mapa = MapaSendaJugador::desdeArray([
            TipoPokemon::FUEGO->value => 5,
            TipoPokemon::AGUA->value => 4,
        ]);

        $this->assertSame(NivelSenda::N5, $mapa->nivel(TipoPokemon::FUEGO));
        $this->assertSame(NivelSenda::N4, $mapa->nivel(TipoPokemon::AGUA));
        $this->assertSame(NivelSenda::N1, $mapa->nivel(TipoPokemon::PLANTA));
    }

    #[Test]
    public function desde_array_acota_los_niveles_al_rango_1_5(): void
    {
        $mapa = MapaSendaJugador::desdeArray([
            TipoPokemon::FUEGO->value => 99,
            TipoPokemon::AGUA->value => 0,
            TipoPokemon::PLANTA->value => -3,
        ]);

        $this->assertSame(NivelSenda::N5, $mapa->nivel(TipoPokemon::FUEGO));
        $this->assertSame(NivelSenda::N1, $mapa->nivel(TipoPokemon::AGUA));
        $this->assertSame(NivelSenda::N1, $mapa->nivel(TipoPokemon::PLANTA));
    }

    #[Test]
    public function desde_array_ignora_tipos_fuera_del_catalogo(): void
    {
        $mapa = MapaSendaJugador::desdeArray([
            0 => 5,
            19 => 5,
            TipoPokemon::FUEGO->value => 4,
        ]);

        $this->assertSame([TipoPokemon::FUEGO->value => 4], $mapa->toArray());
    }

    #[Test]
    public function bono_captura_usa_el_maximo_de_los_tipos(): void
    {
        $mapa = MapaSendaJugador::desdeArray([
            TipoPokemon::FUEGO->value => 4,
            TipoPokemon::AGUA->value => 5,
        ]);

        $this->assertSame(0.20, $mapa->bonoCaptura([TipoPokemon::FUEGO, TipoPokemon::AGUA]));
        $this->assertSame(0.10, $mapa->bonoCaptura([TipoPokemon::FUEGO]));
        $this->assertSame(0.0, $mapa->bonoCaptura([]));
    }

    #[Test]
    public function bonos_de_dano_defensa_y_caramelo_siguen_el_nivel(): void
    {
        $mapa = MapaSendaJugador::desdeArray([
            TipoPokemon::FUEGO->value => 5,
            TipoPokemon::AGUA->value => 4,
            TipoPokemon::PLANTA->value => 2,
        ]);

        $this->assertSame(0.20, $mapa->bonoDano(TipoPokemon::FUEGO));
        $this->assertSame(0.20, $mapa->bonoDefensa(TipoPokemon::FUEGO));
        $this->assertSame(0.20, $mapa->bonoCaramelo(TipoPokemon::FUEGO));

        $this->assertSame(0.10, $mapa->bonoDano(TipoPokemon::AGUA));
        $this->assertSame(0.10, $mapa->bonoDefensa(TipoPokemon::AGUA));
        $this->assertSame(0.10, $mapa->bonoCaramelo(TipoPokemon::AGUA));

        $this->assertSame(0.0, $mapa->bonoDano(TipoPokemon::PLANTA));
        $this->assertSame(0.0, $mapa->bonoDefensa(TipoPokemon::PLANTA));
        $this->assertSame(0.0, $mapa->bonoCaramelo(TipoPokemon::PLANTA));
    }

    #[Test]
    public function to_array_hace_round_trip(): void
    {
        $original = MapaSendaJugador::desdeArray([
            TipoPokemon::NORMAL->value => 1,
            TipoPokemon::FUEGO->value => 4,
            TipoPokemon::AGUA->value => 5,
        ]);

        $recargado = MapaSendaJugador::desdeArray($original->toArray());

        $this->assertSame($original->toArray(), $recargado->toArray());
        $this->assertSame(NivelSenda::N4, $recargado->nivel(TipoPokemon::FUEGO));
        $this->assertSame(NivelSenda::N5, $recargado->nivel(TipoPokemon::AGUA));
    }
    #[Test]
    public function desde_array_transporta_los_porcentajes_configurados(): void
    {
        $mapa = MapaSendaJugador::desdeArray(
            [TipoPokemon::FUEGO->value => 5, TipoPokemon::AGUA->value => 4],
            [4 => 0.10, 5 => 0.15],
        );

        $this->assertSame(0.15, $mapa->bonoDano(TipoPokemon::FUEGO));
        $this->assertSame(0.15, $mapa->bonoDefensa(TipoPokemon::FUEGO));
        $this->assertSame(0.15, $mapa->bonoCaramelo(TipoPokemon::FUEGO));
        $this->assertSame(0.10, $mapa->bonoDano(TipoPokemon::AGUA));
    }

    #[Test]
    public function desde_array_sin_porcentajes_usa_los_default(): void
    {
        $mapa = MapaSendaJugador::desdeArray([TipoPokemon::FUEGO->value => 5]);

        $this->assertSame(0.20, $mapa->bonoDano(TipoPokemon::FUEGO));
    }
}
