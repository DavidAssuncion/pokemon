<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Datagrid\DatagridDefinition;
use App\Datagrid\DatagridRegistry;
use App\Models\Pokemon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DatagridRegistryTest extends TestCase
{
    private function definition(): DatagridDefinition
    {
        return new DatagridDefinition(model: Pokemon::class);
    }

    public function test_register_is_case_insensitive(): void
    {
        $registry = new DatagridRegistry();
        $definition = $this->definition();

        $registry->register('Pokemon', $definition);

        $this->assertTrue($registry->has('pokemon'));
        $this->assertSame($definition, $registry->get('PoKeMoN'));
    }

    public function test_unknown_slug_returns_false_and_get_throws(): void
    {
        $registry = new DatagridRegistry();

        $this->assertFalse($registry->has('unknown'));

        $this->expectException(InvalidArgumentException::class);

        $registry->get('unknown');
    }

    public function test_register_same_slug_overwrites(): void
    {
        $registry = new DatagridRegistry();
        $first = $this->definition();
        $second = new DatagridDefinition(model: Pokemon::class, searchable: ['name']);

        $registry->register('pokemon', $first);
        $registry->register('POKEMON', $second);

        $this->assertSame($second, $registry->get('pokemon'));
    }
}
