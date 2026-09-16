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
use Src\Huevos\Domain\EstadoCalculadoHuevo;
use Src\Huevos\Domain\EstadoHuevo;

/**
 * Lista huevos del usuario, especies disponibles para crear huevos y límites.
 */
final class ListarHuevos
{
    /**
     * Resumen de huevos activos, familias disponibles y límites.
     *
     * Contrato de tiempos (B1): `tiempo_total_horas` y `tiempo_restante` están
     * en la misma unidad — horas, float — y `progreso` es un entero 0-100.
     *
     * @return array{
     *     huevos: list<array{
     *         id: int,
     *         estado: string,
     *         tiempo_total_horas: float,
     *         tiempo_restante: float,
     *         progreso: int,
     *         puede_eclosionar: bool,
     *         pokemon: array{id: int, name: string, icon: string},
     *     }>,
     *     disponibles: list<array{
     *         evolution_chain_id: int,
     *         pokemon: array{id: int, name: string, icon: string},
     *         capture_rate: int,
     *         hatch: int,
     *     }>,
     *     limites: array{
     *         max_gestacion: int,
     *         max_incubando: int,
     *         gestacion_activa: bool,
     *         incubacion_activa: bool,
     *     },
     * }
     */
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
     *
     * @return array{
     *     id: int,
     *     estado: string,
     *     tiempo_total_horas: float,
     *     tiempo_restante: float,
     *     progreso: int,
     *     puede_eclosionar: bool,
     *     pokemon: array{id: int, name: string, icon: string},
     * }
     */
    public function serializarUno(Huevo $huevo, ?Carbon $ahora = null): array
    {
        $ahora ??= now();
        $pokemon = $huevo->pokemon;
        $captureRate = $huevo->capture_rate;
        $hatch = $huevo->hatch ?? 1;
        $estado = self::estadoDe($huevo, $ahora);
        $totalHoras = round(CalculadorTiemposHuevo::sumaHoras($captureRate, $hatch), 2);

        return [
            'id' => $huevo->id,
            'estado' => $estado->estado->value,
            'tiempo_total_horas' => $totalHoras,
            'tiempo_restante' => $estado->restanteHoras,
            'progreso' => self::progresoDe($totalHoras, $estado->restanteHoras),
            'puede_eclosionar' => $estado->estado === EstadoHuevo::LISTO,
            'pokemon' => self::serializarPokemon($pokemon),
        ];
    }

    /**
     * Familias con ≥2 reclutados DISTINTOS (pueden ser especies distintas de la
     * misma cadena), sin huevo activo de esa familia y cuyo representante tiene
     * hatch. 1 entrada por evolution_chain_id; se elige el miembro con menor
     * species_id (regla "primer integrante" del proyecto).
     *
     * @return list<array{
     *     evolution_chain_id: int,
     *     pokemon: array{id: int, name: string, icon: string},
     *     capture_rate: int,
     *     hatch: int,
     * }>
     */
    private function disponibles(int $userId): array
    {
        $reclutados = Reclutado::with('pokemon')
            ->where('user_id', $userId)
            ->get()
            ->reject(
                fn (Reclutado $reclutado): bool => $reclutado->pokemon === null
                    || $reclutado->pokemon->evolution_chain_id === null,
            );

        $familiasActivas = Huevo::where('user_id', $userId)
            ->whereNull('eclosionado_en')
            ->whereNotNull('evolution_chain_id')
            ->pluck('evolution_chain_id')
            ->map(fn ($cadena): int => (int) $cadena)
            ->all();

        /** @var array<int, Pokemon> $porCadena keyed por evolution_chain_id */
        $porCadena = [];
        foreach ($reclutados->groupBy(fn (Reclutado $reclutado): ?int => $reclutado->pokemon->evolution_chain_id) as $cadena => $grupo) {
            if (!is_int($cadena) || $grupo->count() < 2 || in_array($cadena, $familiasActivas, true)) {
                continue;
            }

            /** @var Reclutado $representante */
            $representante = $grupo
                ->sortBy(fn (Reclutado $reclutado): int => $reclutado->pokemon->species_id)
                ->first();
            $pokemon = $representante->pokemon;

            if ($pokemon === null || $pokemon->hatch === null) {
                continue;
            }

            $porCadena[$cadena] = $pokemon;
        }

        ksort($porCadena);

        return array_map(
            fn (Pokemon $pokemon, int $cadena): array => [
                'evolution_chain_id' => $cadena,
                'pokemon' => self::serializarPokemon($pokemon),
                'capture_rate' => $pokemon->capture_rate,
                'hatch' => (int) $pokemon->hatch,
            ],
            $porCadena,
            array_keys($porCadena),
        );
    }

    /**
     * @param  Collection<int, Huevo>  $huevos
     * @return array{
     *     max_gestacion: int,
     *     max_incubando: int,
     *     gestacion_activa: bool,
     *     incubacion_activa: bool,
     * }
     */
    private function limites(Collection $huevos, Carbon $ahora): array
    {
        $gestacionActiva = false;
        $incubacionActiva = false;

        foreach ($huevos as $huevo) {
            $estado = self::estadoDe($huevo, $ahora)->estado;

            if ($estado === EstadoHuevo::GESTANDO) {
                $gestacionActiva = true;
            }
            if (in_array($estado, [EstadoHuevo::INCUBANDO, EstadoHuevo::LISTO], true)) {
                $incubacionActiva = true;
            }
        }

        return [
            'max_gestacion' => 1,
            'max_incubando' => 1,
            'gestacion_activa' => $gestacionActiva,
            'incubacion_activa' => $incubacionActiva,
        ];
    }

    private static function progresoDe(float $totalHoras, float $restanteHoras): int
    {
        if ($totalHoras <= 0.0) {
            return 0;
        }

        return (int) round(max(0.0, min(100.0, ($totalHoras - $restanteHoras) / $totalHoras * 100)));
    }

    /**
     * Estado calculado de un huevo (requiere pokemon cargado con ->with).
     */
    private static function estadoDe(Huevo $huevo, Carbon $ahora): EstadoCalculadoHuevo
    {
        return CalculadorEstadoHuevo::estadoDe(
            $huevo->created_at,
            $huevo->capture_rate,
            $huevo->hatch ?? 1,
            $ahora,
        );
    }

    /**
     * @return array{id: int, name: string, icon: string}
     */
    private static function serializarPokemon(Pokemon $pokemon): array
    {
        return [
            'id' => $pokemon->id,
            'name' => $pokemon->name,
            'icon' => "/images/iconos_webp/{$pokemon->id}.webp",
        ];
    }
}
