<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;
use Src\Exploraciones\Domain\Recompensas\RecompensaCaptura;
use Src\Exploraciones\Domain\Recompensas\RecompensaEv;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\RecompensaTipo;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;

/**
 * DTOs de Recompensas: inmutabilidad (readonly) y validación de tipos
 * del agregado ResultadoRecompensas.
 */
class PokemonDerrotadoTest extends TestCase
{
    public function test_pokemon_derrotado_expone_sus_campos_para_el_calculador(): void
    {
        $derrotado = new PokemonDerrotado(
            id: 25,
            baseExperience: 112,
            evolutionChainId: 10,
            speciesId: 172,
            captureRate: 190,
            tipos: ['Eléctrico'],
            stats: collect([['stat' => 1, 'effort' => 2]]),
            fase: 2,
        );

        $this->assertSame(25, $derrotado->id);
        $this->assertSame(112, $derrotado->baseExperience);
        $this->assertSame(10, $derrotado->evolutionChainId);
        $this->assertSame(172, $derrotado->speciesId);
        $this->assertSame(190, $derrotado->captureRate);
        $this->assertSame(['Eléctrico'], $derrotado->tipos);
        $this->assertSame([['stat' => 1, 'effort' => 2]], $derrotado->stats->all());
        $this->assertSame(2, $derrotado->fase);
    }

    public function test_resultado_rechaza_items_de_tipo_incorrecto_en_capturas(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Violación de tipo intencionada: se prueba la validación runtime del agregado.
        // @phpstan-ignore-next-line argument.type
        new ResultadoRecompensas(
            capturas: collect([new RecompensaEv(stat: 1, cantidad: 2)]),
            caramelosFamilia: [],
            caramelosEv: [],
            caramelosTipo: [],
            expTotal: 10,
        );
    }

    public function test_resultado_rechaza_items_de_tipo_incorrecto_en_caramelos_familia(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Violación de tipo intencionada: se prueba la validación runtime del agregado.
        // @phpstan-ignore-next-line argument.type
        new ResultadoRecompensas(
            capturas: [],
            caramelosFamilia: collect([new RecompensaTipo(tipo: 'Fuego', cantidad: 1)]),
            caramelosEv: [],
            caramelosTipo: [],
            expTotal: 10,
        );
    }

    public function test_resultado_acepta_arrays_y_normaliza_a_colecciones_valued(): void
    {
        $resultado = new ResultadoRecompensas(
            capturas: [new RecompensaCaptura(pokemonId: 25, cantidad: 1)],
            caramelosFamilia: [new RecompensaFamilia(evolutionChainId: 10, cantidad: 2)],
            caramelosEv: [new RecompensaEv(stat: 1, cantidad: 3)],
            caramelosTipo: [new RecompensaTipo(tipo: 'Eléctrico', cantidad: 4)],
            expTotal: 5,
        );

        $this->assertInstanceOf(RecompensaCaptura::class, $resultado->capturas->first());
        $this->assertInstanceOf(RecompensaFamilia::class, $resultado->caramelosFamilia->first());
        $this->assertInstanceOf(RecompensaEv::class, $resultado->caramelosEv->first());
        $this->assertInstanceOf(RecompensaTipo::class, $resultado->caramelosTipo->first());
        $this->assertSame(5, $resultado->expTotal);
    }

    public function test_recompensa_tipo_slug_delega_en_slug_tipo(): void
    {
        $this->assertSame('electrico', (new RecompensaTipo(tipo: 'Eléctrico', cantidad: 1))->slug());
        $this->assertSame('dragon', (new RecompensaTipo(tipo: 'Dragón', cantidad: 1))->slug());
    }
}
