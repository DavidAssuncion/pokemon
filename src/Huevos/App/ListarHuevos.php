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
use Src\Huevos\Domain\Collections\HuevoDisponibleCollection;
use Src\Huevos\Domain\Collections\HuevoResumenCollection;
use Src\Huevos\Domain\DataTransferObjects\HuevoDisponible;
use Src\Huevos\Domain\DataTransferObjects\HuevoResumen;
use Src\Huevos\Domain\DataTransferObjects\LimitesHuevos;
use Src\Huevos\Domain\DataTransferObjects\ResumenHuevos;
use Src\Huevos\Domain\EstadoCalculadoHuevo;
use Src\Huevos\Domain\EstadoHuevo;
use Src\Shared\Domain\DataTransferObjects\PokemonResumen;

/**
 * Lista huevos del usuario, especies disponibles para crear huevos y límites.
 */
final class ListarHuevos
{
    /**
     * Resumen de huevos activos, familias disponibles y límites.
     *
     * Contrato de tiempos (B1): `tiempoTotalHoras` y `tiempoRestante` están en
     * la misma unidad — horas, float — y `progreso` es un entero 0-100.
     */
    public function listar(int $userId): ResumenHuevos
    {
        $huevos = Huevo::where('user_id', $userId)
            ->with('pokemon')
            ->whereNull('eclosionado_en')
            ->orderByDesc('id')
            ->get();

        $ahora = now();

        $huevosResumen = new HuevoResumenCollection(
            $huevos
                ->map(fn (Huevo $huevo): HuevoResumen => $this->serializarUno($huevo, $ahora))
                ->values()
                ->all()
        );

        return new ResumenHuevos(
            huevos: $huevosResumen,
            disponibles: $this->disponibles($userId),
            limites: $this->limites($huevos, $ahora),
        );
    }

    /**
     * Serializa un huevo individual (usado también por HuevoController::store).
     */
    public function serializarUno(Huevo $huevo, ?Carbon $ahora = null): HuevoResumen
    {
        $ahora ??= now();
        $captureRate = $huevo->capture_rate ?? 0;
        $hatch = $huevo->hatch ?? 1;
        $estado = self::estadoDe($huevo, $ahora);
        $totalHoras = round(CalculadorTiemposHuevo::sumaHoras($captureRate, $hatch), 2);

        return new HuevoResumen(
            id: $huevo->id,
            estado: $estado->estado->value,
            tiempoTotalHoras: $totalHoras,
            tiempoRestante: $estado->restanteHoras,
            progreso: self::progresoDe($totalHoras, $estado->restanteHoras),
            puedeEclosionar: $estado->estado === EstadoHuevo::LISTO,
            pokemon: self::resumenPokemon($huevo->pokemon),
        );
    }

    /**
     * Familias con ≥2 reclutados DISTINTOS (pueden ser especies distintas de la
     * misma cadena), sin huevo activo de esa familia y cuyo representante tiene
     * hatch. 1 entrada por evolution_chain_id; se elige el miembro con menor
     * species_id (regla "primer integrante" del proyecto).
     */
    private function disponibles(int $userId): HuevoDisponibleCollection
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

        $disponibles = new HuevoDisponibleCollection();
        foreach ($porCadena as $cadena => $pokemon) {
            $disponibles->add(new HuevoDisponible(
                evolutionChainId: $cadena,
                pokemon: self::resumenPokemon($pokemon),
                captureRate: $pokemon->capture_rate,
                hatch: (int) $pokemon->hatch,
            ));
        }

        return $disponibles;
    }

    /**
     * @param  Collection<int, Huevo>  $huevos
     */
    private function limites(Collection $huevos, Carbon $ahora): LimitesHuevos
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

        return new LimitesHuevos(
            maxGestacion: 1,
            maxIncubando: 1,
            gestacionActiva: $gestacionActiva,
            incubacionActiva: $incubacionActiva,
        );
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
            $huevo->capture_rate ?? 0,
            $huevo->hatch ?? 1,
            $ahora,
        );
    }

    private static function resumenPokemon(Pokemon $pokemon): PokemonResumen
    {
        return PokemonResumen::desdeIdNombre($pokemon->id, $pokemon->name);
    }
}
