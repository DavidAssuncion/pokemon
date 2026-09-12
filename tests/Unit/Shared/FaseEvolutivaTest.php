<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Habitats\Domain\ResolvedorCadenasEvolutivas;

/**
 * La fase evolutiva (`FaseEvolutiva`, eliminado en el refactor F1-F9) vive ahora como
 * `stage` (BFS) en `ResolvedorCadenasEvolutivas::getFamilyMembersByChain`: recibe los
 * miembros y las filas de evolución ya cargadas y devuelve la familia con su etapa.
 */
class FaseEvolutivaTest extends TestCase
{
    private ResolvedorCadenasEvolutivas $resolvedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolvedor = new ResolvedorCadenasEvolutivas(
            fn (int $id): string => "/images/iconos_webp/{$id}.webp",
        );
    }

    #[Test]
    public function cadena_simple_de_dos_miembros_asigna_fases_uno_y_dos(): void
    {
        $members = $this->resolvedor->getFamilyMembersByChain(
            [
                ['id' => 4, 'name' => 'charmander', 'species_id' => 4],
                ['id' => 5, 'name' => 'charmeleon', 'species_id' => 5],
            ],
            [
                ['evolved_species_id' => 4, 'evolves_from_species_id' => null],
                ['evolved_species_id' => 5, 'evolves_from_species_id' => 4],
            ],
        );

        $this->assertSame(1, $members[0]['stage']);
        $this->assertSame('charmander', $members[0]['name']);
        $this->assertSame('/images/iconos_webp/4.webp', $members[0]['icon']);
        $this->assertSame(2, $members[1]['stage']);
    }

    #[Test]
    public function cadena_ramificada_de_tres_miembros_etapa_dos_para_todas_las_evoluciones(): void
    {
        $members = $this->resolvedor->getFamilyMembersByChain(
            [
                ['id' => 133, 'name' => 'eevee', 'species_id' => 133],
                ['id' => 134, 'name' => 'vaporeon', 'species_id' => 134],
                ['id' => 135, 'name' => 'jolteon', 'species_id' => 135],
            ],
            [
                ['evolved_species_id' => 133, 'evolves_from_species_id' => null],
                ['evolved_species_id' => 134, 'evolves_from_species_id' => 133],
                ['evolved_species_id' => 135, 'evolves_from_species_id' => 133],
            ],
        );

        $this->assertSame([1, 2, 2], array_column($members, 'stage'));
        $this->assertSame('eevee', $members[0]['name']);
        $this->assertEqualsCanonicalizing(['vaporeon', 'jolteon'], array_slice(array_column($members, 'name'), 1));
    }

    #[Test]
    public function tercera_evolucion_recibe_fase_tres(): void
    {
        $members = $this->resolvedor->getFamilyMembersByChain(
            [
                ['id' => 4, 'name' => 'charmander', 'species_id' => 4],
                ['id' => 5, 'name' => 'charmeleon', 'species_id' => 5],
                ['id' => 6, 'name' => 'charizard', 'species_id' => 6],
            ],
            [
                ['evolved_species_id' => 4, 'evolves_from_species_id' => null],
                ['evolved_species_id' => 5, 'evolves_from_species_id' => 4],
                ['evolved_species_id' => 6, 'evolves_from_species_id' => 5],
            ],
        );

        $this->assertSame([1, 2, 3], array_column($members, 'stage'));
        $this->assertSame([4, 5, 6], array_column($members, 'id'));
        $this->assertSame([4, 5, 6], array_column($members, 'species_id'));
    }

    #[Test]
    public function especie_sin_fila_de_evolucion_devuelve_estructura_con_fase_residual(): void
    {
        // raticate no tiene fila en `pokemon_evolution` (especie no encontrada en las
        // evoluciones): el resolvedor no la alcanza en el BFS y la deja en stage 3
        // (fallback definido en `getFamilyMembersByChain`), con la estructura completa.
        $members = $this->resolvedor->getFamilyMembersByChain(
            [
                ['id' => 19, 'name' => 'rattata', 'species_id' => 19],
                ['id' => 20, 'name' => 'raticate', 'species_id' => 20],
            ],
            [
                ['evolved_species_id' => 19, 'evolves_from_species_id' => null],
            ],
        );

        $this->assertCount(2, $members);
        $this->assertSame(['id', 'name', 'icon', 'stage', 'species_id'], array_keys($members[1]));
        $this->assertSame(1, $members[0]['stage']);
        $this->assertSame(3, $members[1]['stage']);
        $this->assertSame(20, $members[1]['id']);
        $this->assertSame(20, $members[1]['species_id']);
        $this->assertSame('/images/iconos_webp/20.webp', $members[1]['icon']);
    }
}
