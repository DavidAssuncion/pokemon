<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\Reclutado;
use App\Models\User;
use Src\Exploraciones\Domain\CapacidadesStats;
use Src\Shared\Domain\NivelHelper;

/**
 * Fábrica de CapacidadesStats desde modelos Eloquent.
 *
 * Extraída de CapacidadesStats (dominio puro) para mantener la separación
 * de dependencias DDD: el dominio no importa App\Models ni App\Enums.
 */
final class FabricaCapacidadesStats
{
    /**
     * Factory desde modelos Eloquent: calcula nivel y stats del reclutado.
     * Los stats base se obtienen del pokémon asociado (misma lógica que
     * MapeadorPokemonBatalla::statsDe()). Los faltantes se rellenan con 0.
     *
     * `$user` es opcional porque el llamador puede no tener sesión (jobs/QA);
     * en ese caso se asume nivel 1. Las rutas HTTP que lo usan exigen auth.
     */
    public static function desdeReclutado(Reclutado $reclutado, ?User $user = null): CapacidadesStats
    {
        $nivelPokemon = NivelHelper::nivelDesdeExperiencia($reclutado->exp->total());
        $nivelEntrenador = $user?->nivel() ?? 1;

        $stats = ['hp' => 0, 'atk' => 0, 'def' => 0, 'spAtk' => 0, 'spDef' => 0, 'speed' => 0];
        $pokemon = $reclutado->pokemon;

        if ($pokemon !== null) {
            foreach ($pokemon->stats as $stat) {
                $stats[$stat->stat->key()] = (int) $stat->base_stat;
            }
        }

        return new CapacidadesStats(
            hp: $stats['hp'],
            atk: $stats['atk'],
            def: $stats['def'],
            spAtk: $stats['spAtk'],
            spDef: $stats['spDef'],
            speed: $stats['speed'],
            nivelPokemon: $nivelPokemon,
            nivelEntrenador: $nivelEntrenador,
        );
    }
}
