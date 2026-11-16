<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\ExploracionActiva;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Consulta de solo lectura de las expediciones de un usuario, particionadas en
 * activas, terminadas y archivadas con el orden canónico que consume el panel.
 *
 * Reglas:
 * - Las canceladas se excluyen de las tres agrupaciones.
 * - Las archivadas solo se cargan (ni se devuelven) si `incluirArchivadas`.
 * - Orden: activas por fin ascendente (indefinidas al final) y, a igual fin,
 *   por inicio; terminadas por `regreso` descendente; archivadas por
 *   `archived_at` descendente.
 * - No muta filas ni ejecuta procesamiento.
 */
final class ConsultarExpedicionesUsuario
{
    public function __construct(
        private readonly ConsultorEstadoExpedicion $consultor,
    ) {
    }

    public function para(int $userId, bool $incluirArchivadas = false): ExpedicionesAgrupadas
    {
        $consulta = ExploracionActiva::query()
            ->where('user_id', $userId)
            ->with(['reclutado.pokemon.types', 'habitat']);

        if (! $incluirArchivadas) {
            $consulta->where('archived_at', null);
        }

        /** @var Collection<int, ExploracionActiva> $expediciones */
        $expediciones = $consulta->get();

        $activas = new Collection();
        $terminadas = new Collection();
        $archivadas = new Collection();

        foreach ($expediciones as $expedicion) {
            if ($this->consultor->estaCancelada($expedicion)) {
                continue;
            }

            if ($expedicion->archived_at !== null) {
                $archivadas->push($expedicion);

                continue;
            }

            if ($expedicion->regreso === null) {
                $activas->push($expedicion);

                continue;
            }

            $terminadas->push($expedicion);
        }

        return new ExpedicionesAgrupadas(
            activas: $this->ordenarActivas($activas),
            terminadas: $this->ordenarTerminadas($terminadas),
            archivadas: $this->ordenarArchivadas($archivadas),
        );
    }

    /**
     * @param  Collection<int, ExploracionActiva>  $activas
     * @return Collection<int, ExploracionActiva>
     */
    private function ordenarActivas(Collection $activas): Collection
    {
        return $activas
            ->sort(function (ExploracionActiva $a, ExploracionActiva $b): int {
                $comparacion = $this->compararFines(
                    $this->consultor->fin($a),
                    $this->consultor->fin($b),
                );

                if ($comparacion !== 0) {
                    return $comparacion;
                }

                return $this->consultor->inicio($a)->getTimestamp()
                    <=> $this->consultor->inicio($b)->getTimestamp();
            })
            ->values();
    }

    /**
     * Indefinidas (fin null) al final; el resto, por fin ascendente.
     */
    private function compararFines(?CarbonInterface $finA, ?CarbonInterface $finB): int
    {
        if ($finA === null || $finB === null) {
            return match (true) {
                $finA === null && $finB === null => 0,
                $finA === null => 1,
                default => -1,
            };
        }

        return $finA->getTimestamp() <=> $finB->getTimestamp();
    }

    /**
     * @param  Collection<int, ExploracionActiva>  $terminadas
     * @return Collection<int, ExploracionActiva>
     */
    private function ordenarTerminadas(Collection $terminadas): Collection
    {
        return $terminadas
            ->sortByDesc(fn (ExploracionActiva $exp): int => $exp->regreso?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * @param  Collection<int, ExploracionActiva>  $archivadas
     * @return Collection<int, ExploracionActiva>
     */
    private function ordenarArchivadas(Collection $archivadas): Collection
    {
        return $archivadas
            ->sortByDesc(fn (ExploracionActiva $exp): int => $exp->archived_at?->getTimestamp() ?? 0)
            ->values();
    }
}
