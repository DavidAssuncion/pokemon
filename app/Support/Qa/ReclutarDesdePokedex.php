<?php

declare(strict_types=1);

namespace App\Support\Qa;

use App\Models\Pokemon;
use App\Models\Reclutado;
use App\Support\CrearReclutado;
use Illuminate\Support\Facades\DB;
use Src\Granjas\App\ResolverBonosFamilia;
use Src\Pokemon\Domain\Stats\GeneradorIvs;
use Src\Reclutamiento\Domain\DataTransferObjects\DatosNuevoReclutado;

/**
 * Reclutamiento directo desde la Pokédex (solo QA). Reutiliza el flujo normal:
 * bonos de familia (nido/EVs), IVs de reclutamiento y CrearReclutado, que fija
 * la experiencia inicial y actualiza la Pokédex del usuario.
 */
final class ReclutarDesdePokedex
{
    public function __construct(
        private readonly CrearReclutado $crearReclutado,
        private readonly ResolverBonosFamilia $bonosFamilia,
    ) {
    }

    public function para(int $userId, int $pokemonId): Reclutado
    {
        $pokemon = Pokemon::query()->findOrFail($pokemonId);
        $chainId = $pokemon->evolution_chain_id ?? 0;

        $bonos = $chainId > 0
            ? $this->bonosFamilia->paraFamilia($userId, $chainId)
            : null;

        $ivs = GeneradorIvs::reclutamiento($bonos?->nido->nivel ?? 0);
        $evs = $bonos?->evsFamilia;

        return DB::transaction(fn (): Reclutado => $this->crearReclutado->crear(
            new DatosNuevoReclutado(
                userId: $userId,
                pokemonId: $pokemonId,
                ivs: $ivs,
            ),
            $evs,
        ));
    }
}
