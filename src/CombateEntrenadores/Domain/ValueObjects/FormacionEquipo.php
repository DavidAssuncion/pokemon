<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain\ValueObjects;

use Src\Battle\Domain\Posicion;

/**
 * Formación de equipo por slot (1-3): 'vanguardia' | 'retaguardia'.
 *
 * Sustituye el mapa `slot => posición` que circulaba entre controlador y caso
 * de uso.
 */
final readonly class FormacionEquipo
{
    /**
     * @param  array<int, Posicion>  $porSlot
     */
    private function __construct(
        private array $porSlot,
    ) {
    }

    /**
     * Frontera — entrada validada del controlador.
     *
     * @param  array<int|string, mixed>  $formacion
     */
    public static function desde(array $formacion): self
    {
        $porSlot = [];

        foreach ($formacion as $slot => $posicion) {
            if (! is_string($posicion)) {
                continue;
            }

            $valor = Posicion::tryFrom($posicion);
            if ($valor !== null) {
                $porSlot[(int) $slot] = $valor;
            }
        }

        return new self($porSlot);
    }

    public static function vacia(): self
    {
        return new self([]);
    }

    public function para(int $slot): ?Posicion
    {
        return $this->porSlot[$slot] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->porSlot === [];
    }

    /**
     * Frontera — persistencia en `teams.formacion`.
     *
     * @return array<int, string>
     */
    public function toArray(): array
    {
        $resultado = [];
        foreach ($this->porSlot as $slot => $posicion) {
            $resultado[$slot] = $posicion->value;
        }

        return $resultado;
    }
}
