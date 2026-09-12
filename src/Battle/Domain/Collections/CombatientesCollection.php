<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Collections;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\Posicion;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de combatientes de batalla.
 *
 * Centraliza la lógica de filtrado que antes estaba duplicada
 * en EquipoBatalla y GestorTurnos (vivos, vanguardia, retaguardia, etc.).
 */
class CombatientesCollection extends Collection
{
    public string $type = Combatiente::class;

    /**
     * @param  list<Combatiente>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<Combatiente> */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Retorna solo los combatientes vivos (hp > 0).
     */
    public function vivos(): static
    {
        return $this->filter(fn (Combatiente $c) => $c->estaVivo());
    }

    /**
     * Retorna solo los combatientes vivos en vanguardia.
     */
    public function vanguardia(): static
    {
        return $this->filter(fn (Combatiente $c) => $c->estaVivo() && $c->posicion() === Posicion::VANGUARDIA);
    }

    /**
     * Retorna solo los combatientes vivos en retaguardia.
     */
    public function retaguardia(): static
    {
        return $this->filter(fn (Combatiente $c) => $c->estaVivo() && $c->posicion() === Posicion::RETAGUARDIA);
    }

    /**
     * Retorna los combatientes de una posición dada (vivos o muertos).
     */
    public function porPosicion(Posicion $posicion): static
    {
        return $this->filter(fn (Combatiente $c) => $c->posicion() === $posicion);
    }

    /**
     * Indica si hay al menos un combatiente vivo.
     */
    public function alMenosUnoVivo(): bool
    {
        return ! $this->vivos()->isEmpty();
    }

    /**
     * Primer combatiente de la colección (orden de inserción) o null si está vacía.
     */
    public function primero(): ?Combatiente
    {
        $primer = $this->first();

        return $primer instanceof Combatiente ? $primer : null;
    }

    /**
     * Combatiente vivo con mayor velocidad acumulada actual.
     */
    public function mayorVelocidadActual(): ?Combatiente
    {
        $vivos = $this->vivos()->toList();
        if ($vivos === []) {
            return null;
        }

        $mayor = $vivos[0];
        foreach ($vivos as $c) {
            if ($c->velocidadAcumulada() > $mayor->velocidadAcumulada()) {
                $mayor = $c;
            }
        }

        return $mayor;
    }

    /**
     * Combatiente vivo con menor velocidad acumulada actual.
     */
    public function menorVelocidadActual(): ?Combatiente
    {
        $vivos = $this->vivos()->toList();
        if ($vivos === []) {
            return null;
        }

        $menor = $vivos[0];
        foreach ($vivos as $c) {
            if ($c->velocidadAcumulada() < $menor->velocidadAcumulada()) {
                $menor = $c;
            }
        }

        return $menor;
    }

    /**
     * Retorna la menor velocidad efectiva (StatClave::VELOCIDAD) entre vivos.
     * 0 si no hay vivos.
     */
    public function menorVelocidadEfectiva(): float
    {
        $vivos = $this->vivos()->toList();
        if ($vivos === []) {
            return 0;
        }

        $min = $vivos[0]->obtenerStatEfectivo(StatClave::VELOCIDAD);
        foreach ($vivos as $c) {
            $val = $c->obtenerStatEfectivo(StatClave::VELOCIDAD);
            if ($val < $min) {
                $min = $val;
            }
        }

        return $min;
    }

    /**
     * Retorna la menor velocidad base (battleStats()->speed) entre vivos.
     * 0 si no hay vivos.
     */
    public function menorVelocidadBase(): float
    {
        $vivos = $this->vivos()->toList();
        if ($vivos === []) {
            return 0;
        }

        $min = $vivos[0]->pokemon()->battleStats()->speed;
        foreach ($vivos as $c) {
            $speed = $c->pokemon()->battleStats()->speed;
            if ($speed < $min) {
                $min = $speed;
            }
        }

        return $min;
    }
}
