<?php

declare(strict_types=1);

namespace Src\Huevos\App;

use App\Models\Huevo;
use App\Models\Reclutado;
use Src\Pokemon\Domain\Stats\GeneradorIvs;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;
use Src\Shared\Domain\ProbabilidadesShiny;

/**
 * Crea un huevo validando: ≥2 ejemplares de la misma especie + ≤1 huevo activo.
 * Lanzado desde POST /api/huevos (user-initiated; sin auto-trigger).
 */
final class CrearHuevo
{
    public function crear(
        int $userId,
        int $evolutionChainId,
        ?callable $aleatorioShiny = null,
        ?callable $aleatorioIvs = null,
    ): Huevo {
        $reclutados = Reclutado::with('pokemon')
            ->where('user_id', $userId)
            ->whereHas('pokemon', fn ($q) => $q->where('evolution_chain_id', $evolutionChainId))
            ->get();

        $conteoPorEspecie = $reclutados->groupBy('pokemon_id')
            ->filter(fn ($grupo) => $grupo->count() >= 2);

        if ($conteoPorEspecie->isEmpty()) {
            throw new ViolacionReglaNegocio('Necesitas 2 ejemplares de la misma especie para crear un huevo');
        }

        $activos = Huevo::where('user_id', $userId)->whereNull('eclosionado_en')->count();

        if ($activos > 0) {
            throw new ViolacionReglaNegocio('Ya tienes un huevo en gestación o eclosión');
        }

        $especieId = $conteoPorEspecie->keys()->sort()->first();
        $pokemon = $reclutados->firstWhere('pokemon_id', $especieId)?->pokemon;

        if ($pokemon === null) {
            throw new ViolacionReglaNegocio('Especie no encontrada');
        }

        $shiny = ProbabilidadesShiny::esShiny(ProbabilidadesShiny::HUEVO, $aleatorioShiny);
        $ivs = $shiny ? GeneradorIvs::maximos() : GeneradorIvs::huevo($aleatorioIvs);

        return Huevo::create([
            'user_id' => $userId,
            'pokemon_id' => (int) $especieId,
            'capture_rate' => $pokemon->capture_rate,
            'es_shiny' => $shiny,
            'ivs' => $ivs,
        ]);
    }
}
