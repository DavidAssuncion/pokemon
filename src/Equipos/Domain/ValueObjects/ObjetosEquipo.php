<?php

declare(strict_types=1);

namespace Src\Equipos\Domain\ValueObjects;

/**
 * Objetos equipados por slot de un equipo.
 *
 * Sustituye el mapa `slot => item_key` que entraba y salía de
 * ActualizarObjetosEquipo.
 */
final readonly class ObjetosEquipo
{
    /**
     * @param  array<string, string>  $porSlot  slot => item_key ('' = vacío)
     */
    private function __construct(
        private array $porSlot,
    ) {
    }

    /**
     * Frontera — entrada validada del FormRequest.
     *
     * @param  array<string|int, mixed>  $items
     */
    public static function desde(array $items): self
    {
        $porSlot = [];

        foreach ($items as $slot => $itemKey) {
            $porSlot[(string) $slot] = is_string($itemKey) ? $itemKey : '';
        }

        return new self($porSlot);
    }

    public function porSlot(int|string $slot): string
    {
        return $this->porSlot[(string) $slot] ?? '';
    }

    public function tieneSlot(int|string $slot): bool
    {
        return array_key_exists((string) $slot, $this->porSlot);
    }

    public function conSlot(int|string $slot, string $itemKey): self
    {
        $porSlot = $this->porSlot;
        $porSlot[(string) $slot] = $itemKey;

        return new self($porSlot);
    }

    public function isEmpty(): bool
    {
        return $this->porSlot === [];
    }

    /**
     * Frontera — contrato JSON de la API.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->porSlot;
    }
}
