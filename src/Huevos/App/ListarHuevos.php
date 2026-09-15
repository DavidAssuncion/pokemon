<?php

declare(strict_types=1);

namespace Src\Huevos\App;

use App\Models\Huevo;
use App\Models\Pokemon;
use App\Models\Reclutado;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Src\Huevos\Domain\CalculadorEstadoHuevo;
use Src\Huevos\Domain\CalculadorTiemposHuevo;
use Src\Huevos\Domain\EstadoHuevo;

/**
 * Lista huevos del usuario, especies disponibles para crear huevos y límites.
 */
final class ListarHuevos
{
    public function listar(int $userId): array
    {
        $huevos = Huevo::where('user_id', $userId)
            ->with('pokemon')
            ->whereNull('eclosionado_en')
            ->orderByDesc('id')
            ->get();

        $ahora = now();

        $huevosSerializados = $huevos
            ->map(fn (Huevo $huevo): array => $this->serializarUno($huevo, $ahora))
            ->values()
            ->all();

        $disponibles = $this->disponibles($userId);
        $limites = $this->limites($huevos, $ahora);

        return [
            'huevos' => $huevosSerializados,
            'disponibles' => $disponibles,
            'limites' => $limites,
        ];
    }

    /**
     * Serializa un huevo individual (usado también por HuevoController::store).
     */
    public function serializarUno(Huevo $huevo, ?Carbon $ahora = null): array
    {
        $ahora ??= now();
        $captureRate = $huevo->capture_rate;
        $estado = CalculadorEstadoHuevo::estadoDe($huevo->created_at, $captureRate, $ahora);
        $totalHoras = round(CalculadorTiemposHuevo::totalMinutos($captureRate) / 60, 1);

        return [
            'id' => $huevo->id,
            'estado' => $estado['estado']->value,
            'tiempo_total_horas' => $totalHoras,
            'tiempo_restante' => $estado['restante_minutos'],
            'puede_eclosionar' => $estado['estado'] === EstadoHuevo::LISTO,
            'pokemon' => [
                'id' => $huevo->pokemon->id,
                'name' => $huevo->pokemon->name,
                'icon' => "/images/iconos_webp/{$huevo->pokemon->id}.webp",
            ],
        ];
    }

    /**
     * Familias con ≥2 ejemplares de la misma especie; 1 entrada por evolution_chain.
     *
     * @return list<array{evolution_chain_id: int, pokemon: array{id: int, name: string, icon: string}, capture_rate: int, hatch: ?int}>
     */
    private function disponibles(int $userId): array
    {
        $reclutados = Reclutado::where('user_id', $userId)
            ->with('pokemon')
            ->get()
            ->reject(fn ($r) => $r->pokemon === null);

        $conteoPorEspecie = $reclutados
            ->groupBy(fn ($r) => $r->pokemon_id)
            ->filter(fn ($grupo) => $grupo->count() >= 2);

        /** @var array<int, Pokemon> $porCadena  keyed por evolution_chain_id */
        $porCadena = [];
        foreach ($conteoPorEspecie as $grupo) {
            /** @var Pokemon $pokemon */
            $pokemon = $grupo->first()->pokemon;
            $cadena = $pokemon->evolution_chain_id;
            if ($cadena === null) {
                continue;
            }
            $porCadena[$cadena] ??= $pokemon;
        }

        ksort($porCadena);

        return array_map(
            fn (Pokemon $pokemon, int $cadena): array => [
                'evolution_chain_id' => $cadena,
                'pokemon' => [
                    'id' => $pokemon->id,
                    'name' => $pokemon->name,
                    'icon' => "/images/iconos_webp/{$pokemon->id}.webp",
                ],
                'capture_rate' => $pokemon->capture_rate,
                'hatch' => $pokemon->hatch,
            ],
            $porCadena,
            array_keys($porCadena),
        );
    }

    private function limites(Collection $huevos, Carbon $ahora): array
    {
        $gestionActiva = false;
        $incubacionActiva = false;

        foreach ($huevos as $huevo) {
            $estado = CalculadorEstadoHuevo::estadoDe(
                $huevo->created_at,
                $huevo->capture_rate,
                $ahora,
            )['estado'];

            if ($estado === EstadoHuevo::GESTANDO) {
                $gestionActiva = true;
            }
            if (in_array($estado, [EstadoHuevo::INCUBANDO, EstadoHuevo::LISTO], true)) {
                $incubacionActiva = true;
            }
        }

        return [
            'max_gestacion' => 1,
            'max_incubando' => 1,
            'gestacion_activa' => $gestionActiva,
            'incubacion_activa' => $incubacionActiva,
        ];
    }
}
