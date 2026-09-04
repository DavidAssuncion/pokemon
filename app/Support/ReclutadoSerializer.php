<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Reclutado;
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
        $datos['stats'] = self::statsDe($reclutado);

        // Fallback de nombre cuando es null (column nullable): usa el nombre del
        // pokémon para no romper/obtener "null" en el frontend.
        if (($datos['nombre'] ?? null) === null) {
            $datos['nombre'] = $reclutado->pokemon?->name ?? 'Desconocido';
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
