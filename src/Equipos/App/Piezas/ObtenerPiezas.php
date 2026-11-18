<?php

declare(strict_types=1);

namespace Src\Equipos\App\Piezas;

use Illuminate\Support\Facades\DB;
use Src\Equipos\Domain\Collections\EquipamientosPorReclutado;
use Src\Equipos\Domain\ValueObjects\EquipamientoPiezas;
use Src\Shared\Collections\IntCollection;

/**
 * Lectura de las piezas equipadas de uno o varios reclutados.
 *
 * `paraReclutados` resuelve N reclutados con UNA consulta (`whereIn` + join
 * a `reclutados`), sin N+1.
 */
final readonly class ObtenerPiezas
{
    public function __construct(
        private VerificadorReclutadoPropio $verificador,
    ) {
    }

    /**
     * @throws \Src\Shared\Domain\Exceptions\RecursoNoExiste
     */
    public function paraReclutado(int $userId, int $reclutadoId): EquipamientoPiezas
    {
        $this->verificador->asegurar($userId, $reclutadoId);

        return $this->paraReclutados($userId, new IntCollection([$reclutadoId]))
            ->obtener($reclutadoId);
    }

    public function paraReclutados(int $userId, IntCollection $reclutadoIds): EquipamientosPorReclutado
    {
        $equipamientos = EquipamientosPorReclutado::vacio();
        $ids = $reclutadoIds->all();

        if ($ids === []) {
            return $equipamientos;
        }

        $filas = DB::table('recruit_gear')
            ->join('reclutados', 'reclutados.id', '=', 'recruit_gear.reclutado_id')
            ->where('reclutados.user_id', $userId)
            ->whereIn('recruit_gear.reclutado_id', $ids)
            ->select([
                'recruit_gear.reclutado_id',
                'recruit_gear.slot',
                'recruit_gear.item_key',
            ])
            ->get();

        foreach ($filas as $fila) {
            $reclutadoId = (int) $fila->reclutado_id;

            $equipamientos = $equipamientos->con(
                $reclutadoId,
                $equipamientos->obtener($reclutadoId)->conSlot(
                    (int) $fila->slot,
                    (string) $fila->item_key,
                ),
            );
        }

        return $equipamientos;
    }
}
