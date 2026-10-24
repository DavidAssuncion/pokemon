<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Habitats\Domain\Collections\EvolucionCadenaCollection;
use Src\Habitats\Domain\Collections\PokemonCadenaCollection;
use Src\Habitats\Domain\DataTransferObjects\EvolucionCadena;
use Src\Habitats\Domain\DataTransferObjects\MiembroCadena;
use Src\Habitats\Domain\DataTransferObjects\PokemonCadenaBase;
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

    /**
     * @param  list<array{id: int, name: string, species_id: int}>  $filas
     */
    private function pokemon(array $filas): PokemonCadenaCollection
    {
        return new PokemonCadenaCollection(array_map(
            fn (array $fila): PokemonCadenaBase => new PokemonCadenaBase(
                id: $fila['id'],
                nombre: $fila['name'],
                speciesId: $fila['species_id'],
            ),
            $filas,
        ));
    }

    /**
     * @param  list<array{evolved_species_id: int, evolves_from_species_id: int|null}>  $filas
     */
    private function evoluciones(array $filas): EvolucionCadenaCollection
    {
        return new EvolucionCadenaCollection(array_map(
            fn (array $fila): EvolucionCadena => new EvolucionCadena(
                evolvedSpeciesId: $fila['evolved_species_id'],
                evolvesFromSpeciesId: $fila['evolves_from_species_id'],
            ),
            $filas,
        ));
    }

    #[Test]
    public function cadena_simple_de_dos_miembros_asigna_fases_uno_y_dos(): void
    {
        $members = $this->resolvedor->getFamilyMembersByChain(
            $this->pokemon([
                ['id' => 4, 'name' => 'charmander', 'species_id' => 4],
                ['id' => 5, 'name' => 'charmeleon', 'species_id' => 5],
            ]),
            $this->evoluciones([
                ['evolved_species_id' => 4, 'evolves_from_species_id' => null],
                ['evolved_species_id' => 5, 'evolves_from_species_id' => 4],
            ]),
        );

        $primero = $members->en(0);
        $segundo = $members->en(1);

        $this->assertInstanceOf(MiembroCadena::class, $primero);
        $this->assertSame(1, $primero->stage);
        $this->assertSame('charmander', $primero->nombre);
        $this->assertSame('/images/iconos_webp/4.webp', $primero->icono);
        $this->assertSame(2, $segundo?->stage);
    }

    #[Test]
    public function cadena_ramificada_de_tres_miembros_etapa_dos_para_todas_las_evoluciones(): void
    {
        $members = $this->resolvedor->getFamilyMembersByChain(
            $this->pokemon([
                ['id' => 133, 'name' => 'eevee', 'species_id' => 133],
                ['id' => 134, 'name' => 'vaporeon', 'species_id' => 134],
                ['id' => 135, 'name' => 'jolteon', 'species_id' => 135],
            ]),
            $this->evoluciones([
                ['evolved_species_id' => 133, 'evolves_from_species_id' => null],
                ['evolved_species_id' => 134, 'evolves_from_species_id' => 133],
                ['evolved_species_id' => 135, 'evolves_from_species_id' => 133],
            ]),
        );

        $this->assertSame([1, 2, 2], $members->map(fn (MiembroCadena $m): int => $m->stage));
        $this->assertSame('eevee', $members->en(0)?->nombre);
        $this->assertEqualsCanonicalizing(
            ['vaporeon', 'jolteon'],
            array_slice($members->map(fn (MiembroCadena $m): string => $m->nombre), 1),
        );
    }

    #[Test]
    public function tercera_evolucion_recibe_fase_tres(): void
    {
        $members = $this->resolvedor->getFamilyMembersByChain(
            $this->pokemon([
                ['id' => 4, 'name' => 'charmander', 'species_id' => 4],
                ['id' => 5, 'name' => 'charmeleon', 'species_id' => 5],
                ['id' => 6, 'name' => 'charizard', 'species_id' => 6],
            ]),
            $this->evoluciones([
                ['evolved_species_id' => 4, 'evolves_from_species_id' => null],
                ['evolved_species_id' => 5, 'evolves_from_species_id' => 4],
                ['evolved_species_id' => 6, 'evolves_from_species_id' => 5],
            ]),
        );

        $this->assertSame([1, 2, 3], $members->map(fn (MiembroCadena $m): int => $m->stage));
        $this->assertSame([4, 5, 6], $members->map(fn (MiembroCadena $m): int => $m->id));
        $this->assertSame([4, 5, 6], $members->map(fn (MiembroCadena $m): int => $m->speciesId));
    }

    #[Test]
    public function especie_sin_fila_de_evolucion_devuelve_estructura_con_fase_residual(): void
    {
        // raticate no tiene fila en `pokemon_evolution` (especie no encontrada en las
        // evoluciones): el resolvedor no la alcanza en el BFS y la deja en stage 3
        // (fallback definido en `getFamilyMembersByChain`), con la estructura completa.
        $members = $this->resolvedor->getFamilyMembersByChain(
            $this->pokemon([
                ['id' => 19, 'name' => 'rattata', 'species_id' => 19],
                ['id' => 20, 'name' => 'raticate', 'species_id' => 20],
            ]),
            $this->evoluciones([
                ['evolved_species_id' => 19, 'evolves_from_species_id' => null],
            ]),
        );

        $segundo = $members->en(1);

        $this->assertCount(2, $members);
        $this->assertSame(1, $members->en(0)?->stage);
        $this->assertSame(3, $segundo?->stage);
        $this->assertSame(20, $segundo?->id);
        $this->assertSame(20, $segundo?->speciesId);
        $this->assertSame('/images/iconos_webp/20.webp', $segundo?->icono);
    }
}
