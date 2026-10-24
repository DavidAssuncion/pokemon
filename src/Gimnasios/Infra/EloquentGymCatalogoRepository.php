<?php

declare(strict_types=1);

namespace Src\Gimnasios\Infra;

use App\Models\Gym;
use App\Models\GymStage;
use Src\Gimnasios\Domain\Collections\EquipoEtapaGimnasioCollection;
use Src\Gimnasios\Domain\Collections\GimnasioCollection;
use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;
use Src\Gimnasios\Domain\Exceptions\GimnasioNoExiste;
use Src\Gimnasios\Domain\Gimnasio;
use Src\Gimnasios\Domain\Repositories\GymCatalogoRepositoryInterface;
use Src\Shared\Collections\IntCollection;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Repositorio de gimnasios persistidos en BD (tablas gyms + gym_stages).
 *
 * Vuelca las filas a entidades de dominio `Gimnasio` con sus `EquipoEtapaGimnasio`
 * (vanguardia/retaguardia como IntCollection de species_id).
 */
final class EloquentGymCatalogoRepository implements GymCatalogoRepositoryInterface
{
    public function obtenerTodos(): GimnasioCollection
    {
        $gyms = Gym::query()->with('stages')->get();

        return new GimnasioCollection(
            $gyms->map(fn (Gym $model): Gimnasio => $this->desdeModelo($model))->values()->all()
        );
    }

    public function obtenerPorSlugOrFail(string $slug): Gimnasio
    {
        $model = Gym::query()->with('stages')->where('slug', $slug)->first();

        if ($model === null) {
            throw new GimnasioNoExiste();
        }

        return $this->desdeModelo($model);
    }

    public function insertar(string $slug, string $medalla, TipoPokemon $tipo, int $nivelMinimo, EquipoEtapaGimnasioCollection $equipos = new EquipoEtapaGimnasioCollection()): Gimnasio
    {
        $gym = Gym::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'medalla' => $medalla,
                'tipo' => $tipo->value,
                'nivel_minimo' => $nivelMinimo,
            ],
        );

        $this->persistirEtapas($gym, $equipos);

        return $this->desdeModelo($gym->load('stages'));
    }

    public function actualizarDatos(string $slug, string $medalla, TipoPokemon $tipo, int $nivelMinimo): Gimnasio
    {
        $model = $this->obtenerModeloOrFail($slug);

        $model->update([
            'medalla' => $medalla,
            'tipo' => $tipo->value,
            'nivel_minimo' => $nivelMinimo,
        ]);

        return $this->desdeModelo($model->load('stages'));
    }

    public function actualizarEtapas(string $slug, EquipoEtapaGimnasioCollection $equipos): void
    {
        $model = $this->obtenerModeloOrFail($slug);

        $this->persistirEtapas($model, $equipos);
    }

    private function persistirEtapas(Gym $gym, EquipoEtapaGimnasioCollection $equipos): void
    {
        $existentes = $gym->stages()->pluck('id', 'etapa');

        foreach ($equipos->etapas() as $etapa => $equipo) {
            $data = [
                'vanguardia' => $equipo->vanguardia->all(),
                'retaguardia' => $equipo->retaguardia->all(),
            ];

            if (isset($existentes[$etapa])) {
                GymStage::query()->where('id', $existentes[$etapa])->update($data);
            } else {
                GymStage::query()->create($data + [
                    'gym_id' => $gym->id,
                    'etapa' => $etapa,
                ]);
            }
        }
    }

    private function obtenerModeloOrFail(string $slug): Gym
    {
        $model = Gym::query()->where('slug', $slug)->first();

        if ($model === null) {
            throw new GimnasioNoExiste();
        }

        return $model;
    }

    private function desdeModelo(Gym $model): Gimnasio
    {
        $equipos = [];

        foreach ($model->stages as $stage) {
            $equipos[(int) $stage->etapa] = new EquipoEtapaGimnasio(
                vanguardia: new IntCollection($stage->vanguardia ?? []),
                retaguardia: new IntCollection($stage->retaguardia ?? []),
            );
        }

        return new Gimnasio(
            slug: $model->slug,
            medalla: $model->medalla,
            tipo: TipoPokemon::from((int) $model->tipo),
            nivelMinimo: (int) $model->nivel_minimo,
            equipos: new EquipoEtapaGimnasioCollection($equipos),
        );
    }
}
