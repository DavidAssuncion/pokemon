<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use App\Enums\StatEnum;
use App\Models\Complemento;
use App\Models\Refugio;
use Illuminate\Support\Facades\DB;
use Src\Granjas\Domain\CalculadoraBonosGranja;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * Sube un complemento EV de un stat. Requiere refugio construido y condiciones
 * salubres. Aplica el delta de EV a toda la familia (retroactivo).
 */
final class MejorarComplemento
{
    public function __construct(
        private readonly AsegurarGranja $asegurarGranja,
        private readonly FamiliaEnHabitat $familiaEnHabitat,
        private readonly InventarioGranja $inventario,
        private readonly AplicarEvsFamilia $aplicarEvs,
    ) {
    }

    public function handle(int $userId, int $habitatId, int $evolutionChainId, string $statKey): void
    {
        $this->familiaEnHabitat->asegurar($habitatId, $evolutionChainId);

        $stat = StatEnum::fromKey($statKey);
        if ($stat === null) {
            throw new ViolacionReglaNegocio('Stat inválido');
        }

        DB::transaction(function () use ($userId, $habitatId, $evolutionChainId, $stat): void {
            $granja = $this->asegurarGranja->para($userId, $habitatId);

            $refugio = Refugio::where('farm_id', $granja->id)
                ->where('evolution_chain_id', $evolutionChainId)
                ->lockForUpdate()
                ->first();

            if ($refugio === null || $refugio->level < 1) {
                throw new ViolacionReglaNegocio('Primero construye el refugio de esta familia');
            }

            if ($refugio->unhealthy_conditions) {
                throw new ViolacionReglaNegocio('Desactiva las condiciones insalubres para mejorar complementos');
            }

            $complemento = Complemento::where('shelter_id', $refugio->id)
                ->where('stat', $stat->value)
                ->first();

            if ($complemento === null) {
                $complemento = new Complemento();
                $complemento->shelter_id = $refugio->id;
                $complemento->stat = $stat;
                $complemento->level = 0;
                $complemento->save();
            }

            $complemento = Complemento::whereKey($complemento->id)->lockForUpdate()->firstOrFail();

            $max = CalculadoraBonosGranja::MAX_COMPLEMENTO;
            $nivel = $complemento->level;

            if ($nivel >= $max) {
                throw new ViolacionReglaNegocio('El complemento ya está al nivel máximo');
            }

            $coste = CalculadoraBonosGranja::costeComplemento($nivel + 1);

            $this->inventario->consumirEv($userId, $stat->value, $coste->ev);
            $this->inventario->consumirFamilia($userId, $evolutionChainId, $coste->familia);

            $complemento->increment('level');

            $delta = CalculadoraBonosGranja::evComplemento($nivel + 1)
                - CalculadoraBonosGranja::evComplemento($nivel);
            $this->aplicarEvs->aplicar($userId, $evolutionChainId, $stat, $delta);
        });
    }
}
