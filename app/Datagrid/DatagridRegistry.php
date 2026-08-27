<?php

declare(strict_types=1);

namespace App\Datagrid;

use InvalidArgumentException;

/**
 * Registro explícito de modelos expuestos en el datagrid.
 *
 * Un slug no registrado nunca revela la clase subyacente: el controlador
 * responde 404 antes de llegar al servicio.
 */
final class DatagridRegistry
{
    /** @var array<string, DatagridDefinition> */
    private array $definitions = [];

    public function register(string $slug, DatagridDefinition $definition): void
    {
        $this->definitions[strtolower($slug)] = $definition;
    }

    public function has(string $slug): bool
    {
        return isset($this->definitions[strtolower($slug)]);
    }

    public function get(string $slug): DatagridDefinition
    {
        $definition = $this->definitions[strtolower($slug)] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Datagrid model not registered: {$slug}");
        }

        return $definition;
    }
}
