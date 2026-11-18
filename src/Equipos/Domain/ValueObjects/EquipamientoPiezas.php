<?php

declare(strict_types=1);

namespace Src\Equipos\Domain\ValueObjects;

use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * Piezas forjadas equipadas por un reclutado (3 slots fijos).
 *
 * Inmutable y sin dependencias de Eloquent/HTTP: guarda claves de inventario
 * `{tipo}:{species_id}`. `toArray()` es la única frontera de serialización.
 */
final readonly class EquipamientoPiezas
{
    public const SLOTS = 3;

    private function __construct(
        private ?string $slot1 = null,
        private ?string $slot2 = null,
        private ?string $slot3 = null,
    ) {
    }

    public static function vacio(): self
    {
        return new self();
    }

    /**
     * Normaliza el slot recibido por URL (string) o por código (int) a 1..3.
     *
     * @throws ViolacionReglaNegocio
     */
    public static function normalizarSlot(string|int $slot): int
    {
        $valor = is_int($slot) ? $slot : filter_var($slot, FILTER_VALIDATE_INT);

        if ($valor === false || $valor < 1 || $valor > self::SLOTS) {
            throw new ViolacionReglaNegocio('Slot inválido: debe ser 1, 2 o 3.');
        }

        return $valor;
    }

    /**
     * @throws ViolacionReglaNegocio
     */
    public function claveDe(int $slot): ?string
    {
        return match (self::normalizarSlot($slot)) {
            1 => $this->slot1,
            2 => $this->slot2,
            default => $this->slot3,
        };
    }

    /**
     * @throws ViolacionReglaNegocio
     */
    public function conSlot(int $slot, string $clave): self
    {
        return $this->reemplazar(self::normalizarSlot($slot), $clave);
    }

    /**
     * @throws ViolacionReglaNegocio
     */
    public function sinSlot(int $slot): self
    {
        return $this->reemplazar(self::normalizarSlot($slot), null);
    }

    /**
     * Claves equipadas, en orden de slot y sin huecos.
     */
    public function todas(): StringCollection
    {
        $claves = array_filter(
            [$this->slot1, $this->slot2, $this->slot3],
            static fn (?string $clave): bool => $clave !== null,
        );

        return new StringCollection(array_values($claves));
    }

    /**
     * Frontera HTTP: SIEMPRE 3 entradas (1, 2, 3), con `clave` nula en vacíos.
     *
     * @return list<array{slot: int, clave: string|null}>
     */
    public function toArray(): array
    {
        return [
            ['slot' => 1, 'clave' => $this->slot1],
            ['slot' => 2, 'clave' => $this->slot2],
            ['slot' => 3, 'clave' => $this->slot3],
        ];
    }

    private function reemplazar(int $slot, ?string $clave): self
    {
        return match ($slot) {
            1 => new self($clave, $this->slot2, $this->slot3),
            2 => new self($this->slot1, $clave, $this->slot3),
            default => new self($this->slot1, $this->slot2, $clave),
        };
    }
}
