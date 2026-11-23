<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Objetivo de un movimiento, alineado con los MoveTarget de Pokémon Showdown
 * y adaptado al modelo de vanguardia/retaguardia del juego.
 */
enum ObjetivoMovimiento: string
{
    case NORMAL = 'normal';
    case SELF = 'self';
    case ALL_ADJACENT_FOES = 'allAdjacentFoes';
    case ALL_ADJACENT = 'allAdjacent';
    case FILA_VANGUARDIA = 'filaVanguardia';
    case FILA_RETAGUARDIA = 'filaRetaguardia';
    case FILA_OBJETIVO = 'filaObjetivo';
    case ALLY_SIDE = 'allySide';
    case FOE_SIDE = 'foeSide';
    case ALL = 'all';
    case SCRIPTED = 'scripted';
    case RANDOM_NORMAL = 'randomNormal';
    case ANY = 'any';
    case ADJACENT_ALLY = 'adjacentAlly';
    case ADJACENT_ALLY_OR_SELF = 'adjacentAllyOrSelf';
    case ALLIES = 'allies';
    case ALLY_TEAM = 'allyTeam';

    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'un rival',
            self::SELF => 'el usuario',
            self::ALL_ADJACENT_FOES => 'todos los rivales adyacentes',
            self::ALL_ADJACENT => 'todos los adyacentes',
            self::FILA_VANGUARDIA => 'toda la vanguardia rival',
            self::FILA_RETAGUARDIA => 'toda la retaguardia rival',
            self::FILA_OBJETIVO => 'toda la fila del objetivo',
            self::ALLY_SIDE => 'el lado aliado',
            self::FOE_SIDE => 'el lado rival',
            self::ALL => 'todo el campo',
            self::SCRIPTED => 'el rival que le dañó',
            self::RANDOM_NORMAL => 'un rival aleatorio',
            self::ANY => 'cualquier objetivo',
            self::ADJACENT_ALLY => 'un aliado adyacente',
            self::ADJACENT_ALLY_OR_SELF => 'un aliado o el usuario',
            self::ALLIES => 'todos los aliados',
            self::ALLY_TEAM => 'todo el equipo aliado',
        };
    }

    /**
     * Movimientos que eligen un único objetivo (base de la redirección de
     * Colector/Pararrayos).
     */
    public function esUnSoloObjetivo(): bool
    {
        return in_array($this, [
            self::NORMAL,
            self::ANY,
            self::SELF,
            self::ADJACENT_ALLY,
            self::ADJACENT_ALLY_OR_SELF,
            self::SCRIPTED,
            self::RANDOM_NORMAL,
        ], true);
    }

    public function esFila(): bool
    {
        return in_array($this, [
            self::FILA_VANGUARDIA,
            self::FILA_RETAGUARDIA,
            self::FILA_OBJETIVO,
        ], true);
    }

    /**
     * Movimientos que alcanzan a más de un combatiente.
     *
     * `ALL` queda fuera: en el catálogo identifica movimientos de campo/tiempo
     * (Día Soleado, Danza Lluvia, Trick Room…), no ataques que golpeen a todos.
     */
    public function esMultiobjetivo(): bool
    {
        return in_array($this, [
            self::ALL_ADJACENT_FOES,
            self::ALL_ADJACENT,
            self::FILA_VANGUARDIA,
            self::FILA_RETAGUARDIA,
            self::FILA_OBJETIVO,
        ], true);
    }

    /**
     * El alcance incluye a los aliados del atacante.
     */
    public function incluyeAliados(): bool
    {
        return in_array($this, [
            self::ALL_ADJACENT,
        ], true);
    }

    /**
     * Texto completo del alcance para tooltips. `null` cuando el movimiento no
     * es multiobjetivo y no debe mostrar indicador.
     */
    public function etiquetaAlcance(): ?string
    {
        return match ($this) {
            self::ALL_ADJACENT_FOES => 'Afecta a todos los rivales',
            self::ALL_ADJACENT => 'Afecta a todos los combatientes (incluye aliados)',
            self::FILA_VANGUARDIA => 'Afecta a toda la vanguardia rival',
            self::FILA_RETAGUARDIA => 'Afecta a toda la retaguardia rival',
            self::FILA_OBJETIVO => 'Afecta a toda la fila del objetivo',
            default => null,
        };
    }

    /**
     * Texto corto del alcance para badges. `null` cuando no aplica.
     */
    public function etiquetaCortaAlcance(): ?string
    {
        return match ($this) {
            self::ALL_ADJACENT_FOES => 'TODOS RIVALES',
            self::ALL_ADJACENT => 'A TODOS',
            self::FILA_VANGUARDIA, self::FILA_RETAGUARDIA, self::FILA_OBJETIVO => 'FILA COMPLETA',
            default => null,
        };
    }
}
