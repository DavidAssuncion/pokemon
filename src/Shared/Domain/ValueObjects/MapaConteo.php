<?php

declare(strict_types=1);

namespace Src\Shared\Domain\ValueObjects;

use Src\Shared\Collections\StringCollection;

/**
 * Mapa de contadores indexado por clave (nombre de movimiento, estadística…).
 *
 * Sustituye los `array<string, int>` que circulaban como contrato.
 */
final readonly class MapaConteo
{
    /**
     * @param  array<string, int>  $valores
     */
    private function __construct(
        private array $valores,
    ) {
    }

    /**
     * Frontera — reconstrucción desde datos serializados.
     *
     * @param  array<mixed, mixed>  $valores
     */
    public static function desdeArray(array $valores): self
    {
        $normalizados = [];
        foreach ($valores as $clave => $valor) {
            if (is_string($clave)) {
                $normalizados[$clave] = max(0, (int) $valor);
            }
        }

        return new self($normalizados);
    }

    public static function vacio(): self
    {
        return new self([]);
    }

    public function obtener(string $clave): int
    {
        return $this->valores[$clave] ?? 0;
    }

    public function con(string $clave, int $valor): self
    {
        $valores = $this->valores;
        $valores[$clave] = $valor;

        return new self($valores);
    }

    public function sin(string $clave): self
    {
        $valores = $this->valores;
        unset($valores[$clave]);

        return new self($valores);
    }

    public function isEmpty(): bool
    {
        return $this->valores === [];
    }

    public function claves(): StringCollection
    {
        return new StringCollection(array_keys($this->valores));
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->valores;
    }
}
