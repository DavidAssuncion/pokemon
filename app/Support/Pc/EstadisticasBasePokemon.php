<?php

declare(strict_types=1);

namespace App\Support\Pc;

use App\Enums\StatEnum;
use App\Models\Pokemon;
use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * Mapea los stats base de un `Pokemon` Eloquent al Value Object de dominio.
 *
 * Extraído de `ReclutadoSerializer` para que `/equipos` y la comparación de PC
 * usen una única conversión.
 */
final class EstadisticasBasePokemon
{
    public static function desdePokemon(?Pokemon $pokemon): ?StatsValue
    {
        if ($pokemon === null || $pokemon->stats->isEmpty()) {
            return null;
        }

        $base = ['hp' => 0.0, 'atk' => 0.0, 'def' => 0.0, 'spAtk' => 0.0, 'spDef' => 0.0, 'speed' => 0.0];

        foreach ($pokemon->stats as $stat) {
            $clave = match ($stat->stat) {
                StatEnum::HP => 'hp',
                StatEnum::ATTACK => 'atk',
                StatEnum::DEFENSE => 'def',
                StatEnum::SPECIAL_ATTACK => 'spAtk',
                StatEnum::SPECIAL_DEFENSE => 'spDef',
                StatEnum::SPEED => 'speed',
            };
            $base[$clave] = (float) $stat->base_stat;
        }

        return new StatsValue(
            hp: $base['hp'],
            attack: $base['atk'],
            defense: $base['def'],
            spAtk: $base['spAtk'],
            spDef: $base['spDef'],
            speed: $base['speed'],
        );
    }
}
