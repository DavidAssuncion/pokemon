<?php

declare(strict_types=1);

namespace Tests\Unit\Pokemon\Evolution;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Pokemon\Domain\Evolution\AristaEvolutiva;
use Src\Pokemon\Domain\Evolution\FamiliaEvolutiva;
use Src\Pokemon\Domain\Evolution\MiembroFamiliaEvolutiva;
use Src\Pokemon\Domain\Evolution\PokemonFamilia;
use Src\Pokemon\Domain\Evolution\ResolvedorFamiliaEvolutiva;

class ResolvedorFamiliaEvolutivaTest extends TestCase
{
    private ResolvedorFamiliaEvolutiva $resolvedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolvedor = new ResolvedorFamiliaEvolutiva();
    }

    #[Test]
    public function cadena_lineal_asigna_stage_padre_y_nivel(): void
    {
        $familia = $this->resolvedor->resolver(
            [
                $this->nodo(1, 1, 'bulbasaur'),
                $this->nodo(2, 2, 'ivysaur'),
                $this->nodo(3, 3, 'venusaur'),
            ],
            [
                $this->arista(1, 2, 16),
                $this->arista(2, 3, 32),
            ],
        );

        $miembros = $familia->miembros();

        $this->assertCount(3, $miembros);
        $this->assertSame([1, 2, 3], $this->ids($miembros));
        $this->assertSame([1, 2, 3], $this->stages($miembros));
        $this->assertSame([null, 1, 2], $this->parents($miembros));
        $this->assertSame([null, 16, 32], $this->niveles($miembros));
        $this->assertSame([0, 0, 0], $this->branches($miembros));
        $this->assertSame(2, $familia->stageDe(2));
        $this->assertSame('ivysaur', $familia->porId(2)?->nombre);
        $this->assertNull($familia->porId(99));
        $this->assertNull($familia->stageDe(99));
    }

    #[Test]
    public function rama_ordena_hermanos_por_nivel_y_species_id(): void
    {
        $familia = $this->resolvedor->resolver(
            [
                $this->nodo(1, 1, 'base'),
                $this->nodo(200, 200, 'nivel-bajo'),
                $this->nodo(150, 150, 'nivel-alto-b'),
                $this->nodo(100, 100, 'nivel-alto-a'),
            ],
            [
                $this->arista(1, 200, 10),
                $this->arista(1, 150, 20),
                $this->arista(1, 100, 20),
            ],
        );

        $hijos = array_slice($familia->miembros(), 1);

        $this->assertSame([200, 100, 150], $this->ids($hijos));
        $this->assertSame([0, 1, 2], $this->branches($hijos));
        $this->assertSame([10, 20, 20], $this->niveles($hijos));
        $this->assertSame([1, 1, 1], $this->parents($hijos));
    }

    #[Test]
    public function familia_singleton_sin_aristas(): void
    {
        $familia = $this->resolvedor->resolver(
            [$this->nodo(133, 133, 'eevee')],
            [],
        );

        $miembros = $familia->miembros();

        $this->assertCount(1, $miembros);
        $this->assertSame(1, $miembros[0]->stage);
        $this->assertNull($miembros[0]->parentId);
        $this->assertNull($miembros[0]->nivelMinimo);
        $this->assertSame(0, $miembros[0]->branchIndex);
        $this->assertSame(1, $familia->stageDe(133));
    }

    #[Test]
    public function multi_raiz_recorre_todos_los_componentes(): void
    {
        $familia = $this->resolvedor->resolver(
            [
                $this->nodo(10, 10, 'raiz-a'),
                $this->nodo(11, 11, 'hijo-a'),
                $this->nodo(20, 20, 'raiz-b'),
                $this->nodo(21, 21, 'hijo-b'),
            ],
            [
                $this->arista(10, 11, 5),
                $this->arista(20, 21, 7),
            ],
            multiRaiz: true,
        );

        $miembros = $familia->miembros();

        $this->assertSame([10, 20, 11, 21], $this->ids($miembros));
        $this->assertSame([1, 1, 2, 2], $this->stages($miembros));
        $this->assertSame([null, null, 10, 20], $this->parents($miembros));
        $this->assertSame([0, 0, 0, 0], $this->branches($miembros));
    }

    #[Test]
    public function ciclo_no_entra_en_bucle_y_elige_raiz_determinista(): void
    {
        $familia = $this->resolvedor->resolver(
            [
                $this->nodo(1, 1, 'a'),
                $this->nodo(2, 2, 'b'),
            ],
            [
                $this->arista(1, 2, 10),
                $this->arista(2, 1, 20),
            ],
        );

        $miembros = $familia->miembros();

        $this->assertCount(2, $miembros);
        $this->assertSame([1, 2], $this->ids($miembros));
        $this->assertSame([1, 2], $this->stages($miembros));
        $this->assertNull($miembros[0]->parentId);
        $this->assertNull($miembros[0]->nivelMinimo);
        $this->assertSame(1, $miembros[1]->parentId);
        $this->assertSame(10, $miembros[1]->nivelMinimo);
    }

    #[Test]
    public function modo_compatibilidad_solo_recorre_la_primera_raiz(): void
    {
        $familia = $this->resolvedor->resolver(
            [
                $this->nodo(1, 1, 'raiz'),
                $this->nodo(2, 2, 'hijo'),
                $this->nodo(10, 10, 'otra-raiz'),
            ],
            [
                $this->arista(1, 2, 16),
            ],
            multiRaiz: false,
        );

        $miembros = $familia->miembros();

        $this->assertCount(2, $miembros);
        $this->assertSame([1, 2], $this->ids($miembros));
        $this->assertSame([1, 2], $this->stages($miembros));
        $this->assertNull($familia->stageDe(10));
    }

    #[Test]
    public function modo_compatibilidad_limita_a_tres_etapas(): void
    {
        $familia = $this->resolvedor->resolver(
            [
                $this->nodo(1, 1, 'etapa-1'),
                $this->nodo(2, 2, 'etapa-2'),
                $this->nodo(3, 3, 'etapa-3'),
                $this->nodo(4, 4, 'etapa-4'),
            ],
            [
                $this->arista(1, 2, 10),
                $this->arista(2, 3, 20),
                $this->arista(3, 4, 30),
            ],
            multiRaiz: false,
            maxEtapas: 3,
        );

        $miembros = $familia->miembros();

        $this->assertCount(3, $miembros);
        $this->assertSame([1, 2, 3], $this->ids($miembros));
        $this->assertSame([1, 2, 3], $this->stages($miembros));
        $this->assertNull($familia->stageDe(4));
    }

    #[Test]
    public function parent_id_usa_el_id_del_pokemon_y_no_el_species_id(): void
    {
        $familia = $this->resolvedor->resolver(
            [
                $this->nodo(1001, 10, 'padre'),
                $this->nodo(1002, 11, 'hijo'),
            ],
            [
                $this->arista(10, 11, 5),
            ],
        );

        $hijo = $familia->porId(1002);

        $this->assertInstanceOf(MiembroFamiliaEvolutiva::class, $hijo);
        $this->assertSame(11, $hijo->speciesId);
        $this->assertSame(1001, $hijo->parentId);
        $this->assertSame(2, $hijo->stage);
        $this->assertSame(5, $hijo->nivelMinimo);
        $this->assertSame(2, $familia->stageDe(1002));
        $this->assertNull($familia->stageDe(11));
    }

    #[Test]
    public function aristas_duplicadas_conservan_el_menor_nivel(): void
    {
        $familia = $this->resolvedor->resolver(
            [
                $this->nodo(1, 1, 'padre'),
                $this->nodo(2, 2, 'hijo'),
            ],
            [
                $this->arista(1, 2, 30),
                $this->arista(1, 2, 10),
            ],
        );

        $this->assertCount(2, $familia->miembros());
        $this->assertSame(10, $familia->porId(2)?->nivelMinimo);
    }

    #[Test]
    public function sin_nodos_devuelve_familia_vacia(): void
    {
        $familia = $this->resolvedor->resolver([], []);

        $this->assertInstanceOf(FamiliaEvolutiva::class, $familia);
        $this->assertCount(0, $familia);
        $this->assertSame([], $familia->miembros());
    }

    private function nodo(int $id, int $speciesId, string $nombre): PokemonFamilia
    {
        return new PokemonFamilia(
            id: $id,
            nombre: $nombre,
            speciesId: $speciesId,
        );
    }

    private function arista(int $desdeSpeciesId, int $haciaSpeciesId, ?int $nivelMinimo = null): AristaEvolutiva
    {
        return new AristaEvolutiva(
            desdeSpeciesId: $desdeSpeciesId,
            haciaSpeciesId: $haciaSpeciesId,
            nivelMinimo: $nivelMinimo,
        );
    }

    /**
     * @param  list<MiembroFamiliaEvolutiva>  $miembros
     * @return list<int>
     */
    private function ids(array $miembros): array
    {
        return array_map(fn (MiembroFamiliaEvolutiva $m): int => $m->id, $miembros);
    }

    /**
     * @param  list<MiembroFamiliaEvolutiva>  $miembros
     * @return list<int>
     */
    private function stages(array $miembros): array
    {
        return array_map(fn (MiembroFamiliaEvolutiva $m): int => $m->stage, $miembros);
    }

    /**
     * @param  list<MiembroFamiliaEvolutiva>  $miembros
     * @return list<int|null>
     */
    private function parents(array $miembros): array
    {
        return array_map(fn (MiembroFamiliaEvolutiva $m): ?int => $m->parentId, $miembros);
    }

    /**
     * @param  list<MiembroFamiliaEvolutiva>  $miembros
     * @return list<int|null>
     */
    private function niveles(array $miembros): array
    {
        return array_map(fn (MiembroFamiliaEvolutiva $m): ?int => $m->nivelMinimo, $miembros);
    }

    /**
     * @param  list<MiembroFamiliaEvolutiva>  $miembros
     * @return list<int>
     */
    private function branches(array $miembros): array
    {
        return array_map(fn (MiembroFamiliaEvolutiva $m): int => $m->branchIndex, $miembros);
    }
}
