<?php

declare(strict_types=1);

namespace Src\Granjas\Infra\Nidos;

use Illuminate\Support\Facades\DB;
use Src\Forja\Domain\Piezas\Collections\NidosConstruidosCollection;
use Src\Forja\Domain\Piezas\DataTransferObjects\NidoConstruido;
use Src\Forja\Domain\Ports\NidosConstruidos;
use Src\Shared\Domain\Items\ClaveBonguri;

/**
 * Adaptador Eloquent del puerto `NidosConstruidos`.
 *
 * Devuelve una sola consulta por jugador, con join a `farms` y `habitats`, y
 * filtra por nivel ≥ 1. El bonguri cae al color por defecto cuando el hábitat
 * no tiene uno asignado, igual que `ObtenerGranja`.
 */
final class NidosConstruidosEloquent implements NidosConstruidos
{
    public function paraJugador(int $userId): NidosConstruidosCollection
    {
        $filas = DB::table('nests')
            ->join('farms', 'farms.id', '=', 'nests.farm_id')
            ->join('habitats', 'habitats.id', '=', 'farms.habitat_id')
            ->where('farms.user_id', $userId)
            ->where('nests.level', '>=', 1)
            ->orderBy('nests.evolution_chain_id')
            ->orderByDesc('nests.level')
            ->orderBy('farms.habitat_id')
            ->select([
                'nests.farm_id',
                'farms.habitat_id',
                'habitats.name as habitat_nombre',
                'nests.evolution_chain_id',
                'nests.level',
                'habitats.bonguri',
            ])
            ->get();

        $nidos = new NidosConstruidosCollection();

        foreach ($filas as $fila) {
            $nidos->add(new NidoConstruido(
                farmId: (int) $fila->farm_id,
                habitatId: (int) $fila->habitat_id,
                habitatNombre: (string) $fila->habitat_nombre,
                evolutionChainId: (int) $fila->evolution_chain_id,
                nivelNido: (int) $fila->level,
                bonguri: ClaveBonguri::tryFrom((string) $fila->bonguri) ?? ClaveBonguri::porDefecto(),
            ));
        }

        return $nidos;
    }

    public function bloquear(int $userId, int $farmId, int $evolutionChainId): ?int
    {
        $nido = DB::table('nests')
            ->join('farms', 'farms.id', '=', 'nests.farm_id')
            ->where('nests.farm_id', $farmId)
            ->where('nests.evolution_chain_id', $evolutionChainId)
            ->where('farms.user_id', $userId)
            ->lockForUpdate()
            ->first(['nests.level']);

        return $nido !== null ? (int) $nido->level : null;
    }
}
