<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use App\Models\Refugio;
use Illuminate\Support\Facades\DB;
use Src\Granjas\Domain\CalculadoraBonosGranja;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * Construye/sube un refugio consumiendo caramelos de la familia.
 */
final class MejorarRefugio
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

            $refugio = Refugio::where('farm_id', $granja->id)
                ->where('evolution_chain_id', $evolutionChainId)
                ->first();

            if ($refugio === null) {
                $refugio = new Refugio();
                $refugio->farm_id = $granja->id;
                $refugio->evolution_chain_id = $evolutionChainId;
                $refugio->level = 0;
                $refugio->unhealthy_conditions = false;
                $refugio->save();
            }

            $refugio = Refugio::whereKey($refugio->id)->lockForUpdate()->firstOrFail();

            $max = CalculadoraBonosGranja::MAX_REFUGIO;
            $nivel = $refugio->level;

            if ($nivel >= $max) {
                throw new ViolacionReglaNegocio('El refugio ya está al nivel máximo');
            }

            $coste = CalculadoraBonosGranja::costeRefugio($nivel + 1);
            if ($coste <= 0) {
                throw new ViolacionReglaNegocio('No hay coste configurado para el siguiente nivel del refugio');
            }

            $this->inventario->consumirFamilia($userId, $evolutionChainId, $coste);
            $refugio->increment('level');
        });
    }
}
