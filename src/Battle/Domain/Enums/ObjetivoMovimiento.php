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
}
