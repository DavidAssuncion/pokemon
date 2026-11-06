<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\Posicion;

/**
 * Identidad y presentación del combatiente.
 */
final class IdentidadCombatiente
{
    public function __construct(
        private string $id = '',
        private string $nombre = '',
        private string $iconName = '',
        private int $speciesId = 0,
        private string $formSuffix = '',
        private bool $shiny = false,
        private Posicion $posicion = Posicion::VANGUARDIA,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function desdeArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            nombre: (string) ($data['nombre'] ?? ''),
            iconName: (string) ($data['iconName'] ?? ''),
            speciesId: (int) ($data['speciesId'] ?? 0),
            formSuffix: (string) ($data['formSuffix'] ?? ''),
            shiny: (bool) ($data['shiny'] ?? false),
            posicion: Posicion::tryFrom($data['posicion'] ?? Posicion::VANGUARDIA->value) ?? Posicion::VANGUARDIA,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'iconName' => $this->iconName,
            'speciesId' => $this->speciesId,
            'formSuffix' => $this->formSuffix,
            'shiny' => $this->shiny,
            'posicion' => $this->posicion->value,
        ];
    }

    public function id(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): void
    {
        $this->nombre = $nombre;
    }

    public function iconName(): string
    {
        return $this->iconName;
    }

    public function setIconName(string $iconName): void
    {
        $this->iconName = $iconName;
    }

    public function speciesId(): int
    {
        return $this->speciesId;
    }

    public function setSpeciesId(int $speciesId): void
    {
        $this->speciesId = $speciesId;
    }

    public function formSuffix(): string
    {
        return $this->formSuffix;
    }

    public function setFormSuffix(string $formSuffix): void
    {
        $this->formSuffix = $formSuffix;
    }

    public function shiny(): bool
    {
        return $this->shiny;
    }

    public function setShiny(bool $shiny): void
    {
        $this->shiny = $shiny;
    }

    public function posicion(): Posicion
    {
        return $this->posicion;
    }

    public function setPosicion(Posicion $posicion): void
    {
        $this->posicion = $posicion;
    }

    public function estaEnVanguardia(): bool
    {
        return $this->posicion === Posicion::VANGUARDIA;
    }

    public function estaEnRetaguardia(): bool
    {
        return $this->posicion === Posicion::RETAGUARDIA;
    }
}
