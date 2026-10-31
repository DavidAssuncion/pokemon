<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use App\Models\Nido;
use Illuminate\Support\Facades\DB;
use Src\Granjas\Domain\CalculadoraBonosGranja;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * Sube un nido un nivel consumiendo caramelos de la familia. El nivel máximo
 * y los costes viven en CalculadoraBonosGranja.
 */
final class MejorarNido
{
    public function __construct(
        private readonly AsegurarGranja $asegurarGranja,
        private readonly FamiliaEnHabitat $familiaEnHabitat,
        private readonly InventarioGranja $inventario,
    ) {
    }

    public function handle(int $userId, int $habitatId, int $evolutionChainId): void
    {
        $this->familiaEnHabitat->asegurar($habitatId, $evolutionChainId);

        DB::transaction(function () use ($userId, $habitatId, $evolutionChainId): void {
            $granja = $this->asegurarGranja->para($userId, $habitatId);

            $nido = Nido::where('farm_id', $granja->id)
                ->where('evolution_chain_id', $evolutionChainId)
                ->first();

            if ($nido === null) {
                $nido = new Nido();
                $nido->farm_id = $granja->id;
                $nido->evolution_chain_id = $evolutionChainId;
                $nido->level = 0;
                $nido->save();
            }

            $nido = Nido::whereKey($nido->id)->lockForUpdate()->firstOrFail();

            $max = CalculadoraBonosGranja::MAX_NIDO;
            $nivel = $nido->level;

            if ($nivel >= $max) {
                throw new ViolacionReglaNegocio('El nido ya está al nivel máximo');
            }

            $coste = CalculadoraBonosGranja::costeNido($nivel + 1);
            if ($coste <= 0) {
                throw new ViolacionReglaNegocio('No hay coste configurado para el siguiente nivel del nido');
            }

            $this->inventario->consumirFamilia($userId, $evolutionChainId, $coste);
            $nido->increment('level');
        });
    }
}
