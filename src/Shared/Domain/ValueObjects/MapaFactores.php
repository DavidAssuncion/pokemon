<?php

declare(strict_types=1);

namespace Src\Shared\Domain\ValueObjects;

/**
 * Mapa de factores multiplicativos indexado por clave de estadística.
 */
final readonly class MapaFactores
{
    /**
     * @param  array<string, float>  $valores
     */
    private function __construct(
        private array $valores,
    ) {
    }

    /**
     * @param  array<string, float>  $valores
     */
    public static function desdeArray(array $valores): self
    {
        return new self($valores);
    }

    public function obtener(string $clave): float
    {
        return $this->valores[$clave] ?? 1.0;
    }

    /**
     * @return array<string, float>
     */
    public function toArray(): array
    {
        return $this->valores;
    }
}
