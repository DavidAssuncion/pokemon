<?php

declare(strict_types=1);

namespace Src\Reclutamiento\Infra;

use App\Models\Reclutado;
use Src\Reclutamiento\Domain\ReclutadoEntity;
use Src\Reclutamiento\Domain\ReclutamientoRepositoryInterface;

class EloquentReclutamientoRepository implements ReclutamientoRepositoryInterface
{
    /** @return ReclutadoEntity[] */
    public function obtenerTodos(): array
    {
        return Reclutado::with('pokemon')->get()->all();
    }

    public function obtenerPorId(int $id): ?ReclutadoEntity
    {
        $reclutado = Reclutado::with('pokemon')->find($id);

        if ($reclutado === null) {
            return null;
        }

        return $this->toDomain($reclutado);
    }

    public function obtenerPorPokemonId(int $pokemonId): ?ReclutadoEntity
    {
        $reclutado = Reclutado::with('pokemon')->where('pokemon_id', $pokemonId)->first();

        if ($reclutado === null) {
            return null;
        }

        return $this->toDomain($reclutado);
    }

    public function guardar(ReclutadoEntity $reclutado): void
    {
        Reclutado::updateOrCreate(
            ['id' => $reclutado->id],
            [
                'nombre' => $reclutado->nombre,
                'pokemon_id' => $reclutado->pokemonId,
            ],
        );
    }

    public function eliminar(int $id): void
    {
        Reclutado::destroy($id);
    }

    private function toDomain(Reclutado $reclutado): ReclutadoEntity
    {
        return new ReclutadoEntity(
            id: $reclutado->id,
            nombre: $reclutado->nombre,
            pokemonId: $reclutado->pokemon_id,
            pokemonName: $reclutado->pokemon->name ?? '',
        );
    }
}
