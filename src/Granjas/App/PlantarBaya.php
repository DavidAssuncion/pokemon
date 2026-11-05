<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use App\Models\Nido;
use App\Models\PlantacionNido;
use Illuminate\Support\Facades\DB;
use Src\Granjas\Domain\ReglasPlantacion;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;
use Src\Shared\Domain\Items\CategoriaItem;
use Src\Shared\Domain\Items\ClaveBaya;
use Src\Shared\Domain\Ports\InventarioJugador;

/**
 * Planta (o replanta) una baya en un hueco de nido. Consume 1 baya del
 * inventario y persiste la plantación; replantar reinicia el crecimiento.
 */
final class PlantarBaya
{
    public function __construct(
        private readonly AsegurarGranja $asegurarGranja,
        private readonly FamiliaEnHabitat $familiaEnHabitat,
        private readonly InventarioJugador $inventario,
    ) {
    }

    public function handle(
        int $userId,
        int $habitatId,
        int $evolutionChainId,
        int $slot,
        string $berryKey,
    ): void {
        $baya = $this->resolverBaya($berryKey);
        $this->familiaEnHabitat->asegurar($habitatId, $evolutionChainId);

        DB::transaction(function () use ($userId, $habitatId, $evolutionChainId, $slot, $baya): void {
            $granja = $this->asegurarGranja->para($userId, $habitatId);

            $nido = Nido::query()
                ->where('farm_id', $granja->id)
                ->where('evolution_chain_id', $evolutionChainId)
                ->lockForUpdate()
                ->first();

            if ($nido === null || $nido->level < 1) {
                throw new ViolacionReglaNegocio('El nido no existe o todavía no permite plantar');
            }

            $huecos = ReglasPlantacion::huecosDe($nido->level);

            if ($slot < 1 || $slot > $huecos) {
                throw new ViolacionReglaNegocio('Ese hueco no existe en este nido');
            }

            $this->inventario->consumir(
                $userId,
                $baya->clave(),
                ReglasPlantacion::COSTE_PLANTAR,
                'No tienes esa baya',
            );

            $plantacion = PlantacionNido::query()
                ->getQuery()
                ->where('nest_id', $nido->id)
                ->where('slot_index', $slot)
                ->lockForUpdate()
                ->first();

            if ($plantacion !== null) {
                PlantacionNido::query()
                    ->where('id', (int) $plantacion->id)
                    ->update([
                        'berry_key' => $baya->clave(),
                        'planted_at' => now(),
                        'harvest_count' => 0,
                    ]);

                return;
            }

            PlantacionNido::create([
                'nest_id' => $nido->id,
                'slot_index' => $slot,
                'berry_key' => $baya->clave(),
                'planted_at' => now(),
                'harvest_count' => 0,
            ]);
        });
    }

    /**
     * Solo acepta claves de inventario `baya:{slug}` válidas del catálogo.
     * Nunca se acepta un `item_key` libre.
     */
    private function resolverBaya(string $berryKey): ClaveBaya
    {
        $prefijo = CategoriaItem::BAYA->prefijo();

        if (! str_starts_with($berryKey, $prefijo)) {
            throw new ViolacionReglaNegocio('La baya indicada no es válida');
        }

        $baya = ClaveBaya::tryFrom(substr($berryKey, strlen($prefijo)));

        if ($baya === null) {
            throw new ViolacionReglaNegocio('La baya indicada no es válida');
        }

        return $baya;
    }
}
