<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\Posicion;

trait DelegacionIdentidadCombatiente
{
    public function id(): string
    {
        return $this->identidad->id();
    }

    public function nombre(): string
    {
        return $this->identidad->nombre();
    }

    public function iconName(): string
    {
        return $this->identidad->iconName();
    }

    public function speciesId(): int
    {
        return $this->identidad->speciesId();
    }

    public function formSuffix(): string
    {
        return $this->identidad->formSuffix();
    }

    public function shiny(): bool
    {
        return $this->identidad->shiny();
    }

    public function posicion(): Posicion
    {
        return $this->identidad->posicion();
    }

    public function setId(string $id): void
    {
        $this->identidad->setId($id);
    }

    public function setNombre(string $nombre): void
    {
        $this->identidad->setNombre($nombre);
    }

    public function setIconName(string $iconName): void
    {
        $this->identidad->setIconName($iconName);
    }

    public function setSpeciesId(int $speciesId): void
    {
        $this->identidad->setSpeciesId($speciesId);
    }

    public function setFormSuffix(string $formSuffix): void
    {
        $this->identidad->setFormSuffix($formSuffix);
    }

    public function setShiny(bool $shiny): void
    {
        $this->identidad->setShiny($shiny);
    }

    public function setPosicion(Posicion $posicion): void
    {
        $this->identidad->setPosicion($posicion);
    }

    public function estaEnVanguardia(): bool
    {
        return $this->identidad->estaEnVanguardia();
    }

    public function estaEnRetaguardia(): bool
    {
        return $this->identidad->estaEnRetaguardia();
    }
}
