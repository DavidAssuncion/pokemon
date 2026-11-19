<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use App\Models\Granja;
use App\Models\Nido;
use App\Models\PlantacionNido;
use Illuminate\Support\Facades\DB;
use Src\Granjas\Domain\Collections\BayasCosechadasCollection;
use Src\Granjas\Domain\Collections\ErroresCosechaCollection;
use Src\Granjas\Domain\DataTransferObjects\BayaCosechada;
use Src\Granjas\Domain\DataTransferObjects\CosechaNido;
use Src\Granjas\Domain\DataTransferObjects\ErrorCosecha;
use Src\Granjas\Domain\DataTransferObjects\ResultadoCosechaGlobal;
use Src\Granjas\Domain\Entities\Plantacion;
use Src\Granjas\Domain\Ports\PokedexLecturaInterface;
use Src\Granjas\Domain\ReglasPlantacion;
use Src\Shared\Collections\IntCollection;
use Src\Shared\Domain\Exceptions\DominioExcepcion;
use Src\Shared\Domain\Ports\InventarioJugador;
use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Cosecha todas las plantaciones listas del jugador en todos sus hábitats y
 * nidos. Selecciona en tres consultas y luego abre una transacción por nido
 * con lock del nido y de sus plantaciones por id, recalculando el stock bajo
 * lock para evitar dobles cosechas. Un fallo en un nido no detiene el resto:
 * se acumula como error parcial en el resultado.
 */
final class CosecharTodo
{
    private const int INTENTOS_TRANSACCION = 3;

    public function __construct(
        private readonly InventarioJugador $inventario,
        private readonly PokedexLecturaInterface $pokedex,
    ) {
    }

    public function handle(int $userId, bool $incluirNoAvistadas = false): ResultadoCosechaGlobal
    {
        $granjas = Granja::query()
            ->where('user_id', $userId)
            ->get(['id', 'habitat_id']);

        if ($granjas->isEmpty()) {
            return ResultadoCosechaGlobal::vacio();
        }

        /** @var array<int, int> $habitatPorGranja */
        $habitatPorGranja = [];
        foreach ($granjas as $granja) {
            $habitatPorGranja[$granja->id] = $granja->habitat_id;
        }

        $nidos = Nido::hydrate(
            Nido::query()->getQuery()
                ->whereIn('farm_id', array_keys($habitatPorGranja))
                ->where('level', '>=', 1)
                ->orderBy('id')
                ->get()
                ->all(),
        );

        if ($nidos->isEmpty()) {
            return ResultadoCosechaGlobal::vacio();
        }

        $nidos = $this->soloCadenasVisibles($userId, $nidos, $incluirNoAvistadas);

        if ($nidos->isEmpty()) {
            return ResultadoCosechaGlobal::vacio();
        }

        $plantacionesPorNido = $this->plantacionesPorNido($nidos->pluck('id')->all());
        $ahora = now()->toDateTimeImmutable();

        $candidatos = [];
        foreach ($nidos as $nido) {
            foreach ($plantacionesPorNido[$nido->id] ?? [] as $plantacion) {
                if (ReglasPlantacion::stockDe($plantacion, $ahora) > 0) {
                    $candidatos[] = $nido;
                    break;
                }
            }
        }

        if ($candidatos === []) {
            return ResultadoCosechaGlobal::vacio();
        }

        $bayas = MapaConteo::vacio();
        $errores = new ErroresCosechaCollection();
        $totalPlantaciones = 0;
        $nidosCosechados = 0;

        foreach ($candidatos as $nido) {
            try {
                $cosecha = $this->cosecharNido($userId, $nido);
            } catch (DominioExcepcion $excepcion) {
                $errores->add($this->errorDesde($nido, $habitatPorGranja, $excepcion->getMessage()));
                continue;
            } catch (\Throwable $excepcion) {
                report($excepcion);
                $errores->add($this->errorDesde($nido, $habitatPorGranja, 'No se pudo cosechar este nido'));
                continue;
            }

            if ($cosecha->plantaciones === 0) {
                continue;
            }

            $nidosCosechados++;
            $totalPlantaciones += $cosecha->plantaciones;

            foreach ($cosecha->bayas->toArray() as $clave => $cantidad) {
                $bayas = $bayas->con($clave, $bayas->obtener($clave) + $cantidad);
            }
        }

        return new ResultadoCosechaGlobal(
            totalBayas: array_sum($bayas->toArray()),
            totalPlantaciones: $totalPlantaciones,
            nidos: $nidosCosechados,
            bayas: $this->bayasOrdenadas($bayas),
            errores: $errores,
        );
    }

    /**
     * Descarta silenciosamente los nidos de cadenas sin ningún miembro visto.
     * El prefiltro va antes de construir candidatos y no lanza excepción por
     * nido: los errores parciales exponen `evolution_chain_id` y el contador de
     * nidos no debe describir familias ocultas.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Nido>  $nidos
     * @return \Illuminate\Database\Eloquent\Collection<int, Nido>
     */
    private function soloCadenasVisibles(
        int $userId,
        \Illuminate\Database\Eloquent\Collection $nidos,
        bool $incluirNoAvistadas,
    ): \Illuminate\Database\Eloquent\Collection {
        if ($incluirNoAvistadas) {
            return $nidos;
        }

        $cadenas = new IntCollection(array_values(array_unique(
            $nidos->pluck('evolution_chain_id')
                ->map(static fn (mixed $chainId): int => (int) $chainId)
                ->all(),
        )));

        $visibles = $this->pokedex->cadenasConAlgunVisto($userId, $cadenas)->all();

        return $nidos
            ->filter(static fn (Nido $nido): bool => in_array($nido->evolution_chain_id, $visibles, true))
            ->values();
    }

    /**
     * Una transacción por nido: lock del nido, lock de sus plantaciones por id
     * ascendente, recálculo de stock y actualización agregada del inventario
     * ordenada por `item_key`.
     */
    private function cosecharNido(int $userId, Nido $nido): CosechaNido
    {
        return DB::transaction(function () use ($userId, $nido): CosechaNido {
            $farmId = $nido->farm_id;

            $bloqueado = Nido::query()
                ->whereKey($nido->id)
                ->where('farm_id', $farmId)
                ->lockForUpdate()
                ->first();

            if ($bloqueado === null || $bloqueado->level < 1) {
                return CosechaNido::vacia();
            }

            $modelos = PlantacionNido::hydrate(
                PlantacionNido::query()->getQuery()
                    ->where('nest_id', $bloqueado->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->all(),
            );

            $ahora = now()->toDateTimeImmutable();
            $bayas = MapaConteo::vacio();
            $plantaciones = 0;

            foreach ($modelos as $modelo) {
                $plantacion = $this->plantacion($modelo);
                $stock = ReglasPlantacion::stockDe($plantacion, $ahora);

                if ($stock <= 0) {
                    continue;
                }

                $plantaciones++;
                $bayas = $bayas->con(
                    $plantacion->berryKey,
                    $bayas->obtener($plantacion->berryKey) + $stock,
                );

                PlantacionNido::query()
                    ->whereKey($plantacion->id)
                    ->increment('harvest_count', $stock);
            }

            $ordenadas = $bayas->toArray();
            ksort($ordenadas, SORT_STRING);

            foreach ($ordenadas as $clave => $cantidad) {
                $this->inventario->incrementar($userId, $clave, $cantidad);
            }

            return new CosechaNido(bayas: $bayas, plantaciones: $plantaciones);
        }, self::INTENTOS_TRANSACCION);
    }

    /**
     * @param  array<mixed>  $nidosIds
     * @return array<int, list<Plantacion>>
     */
    private function plantacionesPorNido(array $nidosIds): array
    {
        if ($nidosIds === []) {
            return [];
        }

        $resultado = [];

        $modelos = PlantacionNido::hydrate(
            PlantacionNido::query()->getQuery()
                ->whereIn('nest_id', $nidosIds)
                ->orderBy('id')
                ->get()
                ->all(),
        );

        foreach ($modelos as $modelo) {
            $resultado[$modelo->nest_id][] = $this->plantacion($modelo);
        }

        return $resultado;
    }

    private function plantacion(PlantacionNido $modelo): Plantacion
    {
        return new Plantacion(
            id: $modelo->id,
            slotIndex: $modelo->slot_index,
            berryKey: $modelo->berry_key,
            plantedAt: new \DateTimeImmutable((string) $modelo->planted_at),
            harvestCount: $modelo->harvest_count,
        );
    }

    /**
     * @param  array<int, int>  $habitatPorGranja
     */
    private function errorDesde(Nido $nido, array $habitatPorGranja, string $mensaje): ErrorCosecha
    {
        $farmId = $nido->farm_id;

        return ErrorCosecha::desde(
            farmId: $farmId,
            habitatId: $habitatPorGranja[$farmId] ?? 0,
            nidoId: $nido->id,
            evolutionChainId: $nido->evolution_chain_id,
            mensaje: $mensaje,
        );
    }

    /**
     * Bayas ordenadas por cantidad descendente y nombre ascendente.
     */
    private function bayasOrdenadas(MapaConteo $bayas): BayasCosechadasCollection
    {
        $lista = [];

        foreach ($bayas->toArray() as $clave => $cantidad) {
            $lista[] = BayaCosechada::desde($clave, $cantidad);
        }

        usort($lista, static function (BayaCosechada $a, BayaCosechada $b): int {
            $porCantidad = $b->cantidad <=> $a->cantidad;

            return $porCantidad !== 0 ? $porCantidad : strcmp($a->nombre, $b->nombre);
        });

        return new BayasCosechadasCollection($lista);
    }
}
