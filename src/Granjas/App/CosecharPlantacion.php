<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use App\Models\Nido;
use App\Models\PlantacionNido;
use Illuminate\Support\Facades\DB;
use Src\Granjas\Domain\Entities\Plantacion;
use Src\Granjas\Domain\ReglasPlantacion;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;
use Src\Shared\Domain\Ports\InventarioJugador;

/**
 * Cosecha el stock pendiente de una plantación. Todo ocurre bajo transacción
 * con lock del nido y de la fila del hueco; el stock se recalcula después del
 * lock para evitar dobles cosechas.
 */
final class CosecharPlantacion
{
    public function __construct(
        private readonly AsegurarGranja $asegurarGranja,
        private readonly InventarioJugador $inventario,
    ) {
    }

    /**
     * @return int unidades cosechadas (siempre > 0)
     */
    public function handle(
        int $userId,
        int $habitatId,
        int $evolutionChainId,
        int $slot,
    ): int {
        return DB::transaction(function () use ($userId, $habitatId, $evolutionChainId, $slot): int {
            $granja = $this->asegurarGranja->para($userId, $habitatId);

            $nido = Nido::query()
                ->where('farm_id', $granja->id)
                ->where('evolution_chain_id', $evolutionChainId)
                ->lockForUpdate()
                ->first();

            if ($nido === null || $nido->level < 1) {
                throw new ViolacionReglaNegocio('El nido no existe o todavía no permite cosechar');
            }

            $huecos = ReglasPlantacion::huecosDe($nido->level);

            if ($slot < 1 || $slot > $huecos) {
                throw new ViolacionReglaNegocio('Ese hueco no existe en este nido');
            }

            $modelo = PlantacionNido::query()
                ->getQuery()
                ->where('nest_id', $nido->id)
                ->where('slot_index', $slot)
                ->lockForUpdate()
                ->first();

            if ($modelo === null) {
                throw new ViolacionReglaNegocio('No hay ninguna plantación en ese hueco');
            }

            $plantacion = new Plantacion(
                id: (int) $modelo->id,
                slotIndex: (int) $modelo->slot_index,
                berryKey: (string) $modelo->berry_key,
                plantedAt: new \DateTimeImmutable((string) $modelo->planted_at),
                harvestCount: (int) $modelo->harvest_count,
            );

            $stock = ReglasPlantacion::stockDe($plantacion, now()->toDateTimeImmutable());

            if ($stock <= 0) {
                throw new ViolacionReglaNegocio('La planta todavía no tiene bayas listas');
            }

            $this->inventario->incrementar($userId, $plantacion->berryKey, $stock);
            PlantacionNido::query()
                ->where('id', $plantacion->id)
                ->increment('harvest_count', $stock);

            return $stock;
        });
    }
}
