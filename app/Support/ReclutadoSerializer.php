<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\StatEnum;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Reclutado;
use Src\Pokemon\Domain\Stats\BattleStats;
use Src\Pokemon\Domain\Stats\CalculadorCP;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Domain\NivelHelper;

/**
 * Serializador compartido de Reclutado para los endpoints de listado
 * (PlayerController, ReclutadoController). Preserva el contrato existente
 * de /equipos.
 */
final class ReclutadoSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function serializar(Reclutado $reclutado): array
    {
        $datos = $reclutado->toArray();
        $datos['nivel'] = NivelHelper::nivelDesdeExperiencia($reclutado->exp->total());
        $datos['exp_total'] = $reclutado->exp->total();
        $datos['base_experience'] = $reclutado->pokemon?->base_experience;
        $datos['es_shiny'] = $reclutado->es_shiny;
        $datos['behavior'] = $reclutado->behavior;
        $datos['rol'] = $reclutado->rol()->value;
        $datos['stats'] = self::statsDe($reclutado);
        $datos['cp'] = self::cpDe($reclutado);

        // Fallback de nombre cuando es null (column nullable): usa el nombre del
        // pokémon para no romper/obtener "null" en el frontend.
        if (($datos['nombre'] ?? null) === null) {
            $datos['nombre'] = $reclutado->pokemon->name ?? 'Desconocido';
        }

        if ($reclutado->pokemon?->types->isNotEmpty()) {
            $datos['pokemon']['types'] = self::tiposDe($reclutado);
        }

        return $datos;
    }

    /**
     * @return list<array{name: string, value: int}>
     */
    private static function statsDe(Reclutado $reclutado): array
    {
        return $reclutado->pokemon?->stats
            ->sortBy(fn (PokemonStat $stat): int => $stat->stat->value)
            ->map(fn (PokemonStat $stat): array => [
                'name' => $stat->stat->label(),
                'value' => $stat->base_stat,
            ])
            ->values()
            ->all() ?? [];
    }

    /**
     * Combat Power del reclutado: BattleStats a su nivel actual con EV = 0
     * (los pokémon del jugador no tienen EVs; EV>0 es exclusivo de rivales).
     * Si el rekado no tiene pokémon o stats, devuelve 0 (valores nunca ausentes).
     */
    private static function cpDe(Reclutado $reclutado): int
    {
        $pokemon = $reclutado->pokemon;
        if ($pokemon === null || $pokemon->stats->isEmpty()) {
            return 0;
        }

        $base = ['hp' => 0, 'atk' => 0, 'def' => 0, 'spAtk' => 0, 'spDef' => 0, 'speed' => 0];

        foreach ($pokemon->stats as $stat) {
            $clave = match ($stat->stat) {
                StatEnum::HP => 'hp',
                StatEnum::ATTACK => 'atk',
                StatEnum::DEFENSE => 'def',
                StatEnum::SPECIAL_ATTACK => 'spAtk',
                StatEnum::SPECIAL_DEFENSE => 'spDef',
                StatEnum::SPEED => 'speed',
            };
            $base[$clave] = (int) $stat->base_stat;
        }

        $nivel = NivelHelper::nivelDesdeExperiencia($reclutado->exp->total());
        $stats = new BattleStats(
            stats: new StatsValue(
                hp: $base['hp'],
                attack: $base['atk'],
                defense: $base['def'],
                spAtk: $base['spAtk'],
                spDef: $base['spDef'],
                speed: $base['speed'],
            ),
            evs: new StatsValue(0, 0, 0, 0, 0, 0),
            nivel: $nivel,
        );

        return CalculadorCP::calcular($stats, 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tiposDe(Reclutado $reclutado): array
    {
        return $reclutado->pokemon?->types
            ->map(fn (PokemonType $tipo): array => $tipo->toArray() + ['tipo_nombre' => $tipo->tipo_nombre])
            ->values()
            ->all() ?? [];
    }
}
