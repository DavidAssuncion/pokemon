<?php

declare(strict_types=1);

namespace Src\Habitats\Infra;

use App\Models\Habitat;
use App\Models\Province;
use Src\Habitats\Domain\HabitatEntity;
use Src\Habitats\Domain\HabitatsCollection;
use Src\Habitats\Domain\ProvinceEntity;
use Src\Habitats\Domain\ProvinciasCollection;
use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;
use Src\Habitats\Presentation\DTOHabitatDetalle;

class HabitatRepository implements HabitatRepositoryInterface
{
    public function allProvinciasWithHabitats(): ProvinciasCollection
    {
        $provinces = Province::with('habitats')->get()->sortBy('id');

        $items = [];
        foreach ($provinces as $province) {
            $habitats = new HabitatsCollection();
            foreach ($province->habitats as $habitat) {
                $habitats->add(new HabitatEntity(
                    id: $habitat->id,
                    name: $habitat->name,
                    provinceId: $habitat->province_id,
                ));
            }

            $items[] = new ProvinceEntity(
                id: $province->id,
                name: $province->name,
                habitats: $habitats,
            );
        }

        return new ProvinciasCollection($items);
    }

    public function getPokemonsByHabitat(int $habitatId): array
    {
        $habitat = Habitat::find($habitatId);
        if (! $habitat) {
            return [];
        }

        return $habitat->pokemon()
            ->select(['pokemon.id', 'pokemon.name'])
            ->get()
            ->map(fn ($pokemon) => [
                'id' => $pokemon->id,
                'name' => $pokemon->name,
            ])
            ->toArray();
    }

    public function getHabitatDetail(int $habitatId): DTOHabitatDetalle
    {
        $habitat = Habitat::find($habitatId);
        if (! $habitat) {
            return new DTOHabitatDetalle(0, '', '', [1 => [], 2 => [], 3 => []]);
        }

        $levels = [1 => [], 2 => [], 3 => []];

        $habitatPokemon = $habitat->pokemon()
            ->select(['pokemon.id', 'pokemon.name'])
            ->get()
            ->sortBy('pokemon.id')
            ->map(fn ($pokemon) => [
                'id' => $pokemon->id,
                'name' => $pokemon->name,
                'level' => intval($pokemon->pivot->level ?? 2),
                'icon' => "/iconos/{$pokemon->name}.png",
            ]);

        foreach ($habitatPokemon as $pokemon) {
            $level = $pokemon['level'];
            if (! in_array($level, [1, 2, 3], true)) {
                $level = 2;
            }

            $levels[$level][] = [
                'id' => $pokemon['id'],
                'name' => $pokemon['name'],
                'icon' => $pokemon['icon'],
            ];
        }

        return new DTOHabitatDetalle(
            id: $habitat->id,
            name: $habitat->name,
            image: "/habitats-img/{$habitat->id}.webp",
            levels: $levels,
        );
    }
}
