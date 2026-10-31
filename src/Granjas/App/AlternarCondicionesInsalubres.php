<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use App\Models\Refugio;
use Illuminate\Support\Facades\DB;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * Activa/desactiva las condiciones insalubres de un refugio. Mientras estén
 * activas, el refugio invierte sus multiplicadores y no se pueden mejorar los
 * complementos (se avisa en la UI).
 */
final class AlternarCondicionesInsalubres
{
    public function __construct(
        private readonly AsegurarGranja $asegurarGranja,
        private readonly FamiliaEnHabitat $familiaEnHabitat,
    ) {
    }

    public function handle(int $userId, int $habitatId, int $evolutionChainId): void
    {
        $this->familiaEnHabitat->asegurar($habitatId, $evolutionChainId);

        DB::transaction(function () use ($userId, $habitatId, $evolutionChainId): void {
            $granja = $this->asegurarGranja->para($userId, $habitatId);

            $refugio = Refugio::where('farm_id', $granja->id)
                ->where('evolution_chain_id', $evolutionChainId)
                ->lockForUpdate()
                ->first();

            if ($refugio === null || $refugio->level < 1) {
                throw new ViolacionReglaNegocio('Primero construye el refugio de esta familia');
            }

            $refugio->unhealthy_conditions = ! $refugio->unhealthy_conditions;
            $refugio->save();
        });
    }
}
