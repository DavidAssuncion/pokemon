<?php

declare(strict_types=1);

namespace App\Support\Pokedex;

use App\Models\Ability;
use App\Models\Move;
use Illuminate\Support\Collection;

/**
 * Catálogos de opciones de los filtros de la Pokédex.
 *
 * El backend es dueño del orden; la vista solo filtra en cliente por texto.
 */
final class CatalogoFiltrosPokedex
{
    /**
     * @return Collection<int, OpcionFiltro>
     */
    public static function habilidades(): Collection
    {
        $query = Ability::query()->whereHas('pokemons');
        $query->getQuery()->orderBy('name');

        return $query
            ->get(['id', 'name'])
            ->map(fn (Ability $ability): OpcionFiltro => new OpcionFiltro(
                id: $ability->id,
                name: $ability->name,
            ))
            ->values();
    }

    /**
     * @return Collection<int, OpcionFiltro>
     */
    public static function movimientos(): Collection
    {
        $query = Move::query()->whereHas('pokemons');
        $query->getQuery()->orderBy('name');

        return $query
            ->get(['id', 'name'])
            ->map(fn (Move $move): OpcionFiltro => new OpcionFiltro(
                id: $move->id,
                name: $move->name,
            ))
            ->values();
    }
}
