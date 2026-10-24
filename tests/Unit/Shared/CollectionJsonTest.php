<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Equipos\Domain\Collections\TeamCollection;
use Src\Equipos\Domain\TeamAggregate;
use Src\Shared\Collections\IntCollection;

/**
 * Regresión: las colecciones se serializan a JSON como listas, no como
 * objetos {type, items}. Rompía `@json($teams)` y `this.teams.find(...)`.
 */
class CollectionJsonTest extends TestCase
{
    #[Test]
    public function una_coleccion_de_enteros_serializa_como_lista(): void
    {
        $collection = new IntCollection([1, 2, 3]);

        $this->assertSame('[1,2,3]', json_encode($collection));
    }

    #[Test]
    public function una_coleccion_de_agregados_serializa_como_lista_de_objetos(): void
    {
        $collection = new TeamCollection([
            new TeamAggregate(id: 1, name: 'Equipo A', userId: 7),
            new TeamAggregate(id: 2, name: 'Equipo B', userId: 7),
        ]);

        $json = json_encode($collection);

        $this->assertIsString($json);
        $this->assertStringStartsWith('[', $json);
        $this->assertStringContainsString('"name":"Equipo A"', $json);
        $this->assertStringContainsString('"name":"Equipo B"', $json);
        $this->assertStringNotContainsString('"type":', $json);
        $this->assertStringNotContainsString('"items":', $json);
    }
}
