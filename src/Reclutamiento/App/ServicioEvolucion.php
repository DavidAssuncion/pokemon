<?php

declare(strict_types=1);

namespace Src\Reclutamiento\App;

use App\Models\CarameloTipo;
use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Models\ReclutadoExpTipo;
use Illuminate\Support\Str;
use Src\Shared\Domain\NivelHelper;

/**
 * Lógica de evolución por caramelos de tipo.
 *
 * La siguiente evolución se resuelve con la tabla `pokemon_evolution`:
 * `evolves_from_species_id` es el species_id del pokémon actual y
 * `evolved_species_id` apunta directamente a `pokemon.id` de la siguiente
 * etapa. Las formas alternas (mega/gmax, id ≥ 10000) se excluyen.
 */
final class ServicioEvolucion
{
    /**
     * Umbral de exp de tipo para evolucionar: exp necesaria para subir del
     * nivel actual al siguiente (curva media ×10).
     */
    public static function umbralParaNivel(int $nivelActual): int
    {
        return NivelHelper::experienciaParaNivel($nivelActual + 1)
            - NivelHelper::experienciaParaNivel($nivelActual);
    }

    /**
     * Devuelve la siguiente evolución del pokémon (o null si no hay).
     */
    public static function siguienteEvolucion(Pokemon $pokemon): ?Pokemon
    {
        $evolucion = PokemonEvolution::query()
            ->where('evolves_from_species_id', $pokemon->species_id)
            // Excluye formas alternas (mega/gmax) que comparten species_id
            ->where('evolved_species_id', '<', 10000)
            ->orderBy('minimum_level')
            ->orderBy('evolved_species_id')
            ->first();

        return $evolucion !== null ? Pokemon::find($evolucion->evolved_species_id) : null;
    }

    /**
     * Nombres de los tipos requeridos para la siguiente evolución.
     *
     * @return list<string>
     */
    public static function tiposRequeridos(?Pokemon $siguiente): array
    {
        if ($siguiente === null) {
            return [];
        }

        return $siguiente->types
            ->map(fn (PokemonType $tipo): string => $tipo->tipo_nombre)
            ->values()
            ->all();
    }

    /**
     * Requisitos completos para la vista.
     *
     * @return list<array{tipo: string, slug: string, necesario: int, actual: int, caramelosDisponibles: int}>
     */
    public static function requisitos(Reclutado $reclutado): array
    {
        $siguiente = self::siguienteEvolucion($reclutado->pokemon);
        if ($siguiente === null) {
            return [];
        }

        $umbral = self::umbralParaNivel(self::nivelDe($reclutado));
        $actualPorTipo = $reclutado->expTipos->pluck('cantidad', 'tipo');

        $requisitos = [];
        foreach (self::tiposRequeridos($siguiente) as $tipo) {
            $requisitos[] = [
                'tipo' => $tipo,
                'slug' => strtolower(Str::ascii($tipo)),
                'necesario' => $umbral,
                'actual' => (int) ($actualPorTipo[$tipo] ?? 0),
                'caramelosDisponibles' => self::caramelosDisponibles($tipo),
            ];
        }

        return $requisitos;
    }

    /**
     * True si el reclutado cumple todos los requisitos de la evolución.
     */
    public static function puedeEvolucionar(Reclutado $reclutado): bool
    {
        $requisitos = self::requisitos($reclutado);
        if ($requisitos === []) {
            return false;
        }

        foreach ($requisitos as $requisito) {
            if ($requisito['actual'] < $requisito['necesario']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Nivel actual del reclutado a partir de su exp total.
     */
    public static function nivelDe(Reclutado $reclutado): int
    {
        return NivelHelper::nivelDesdeExperiencia((int) ($reclutado->exp['total'] ?? 0));
    }

    /**
     * Caramelos disponibles en el pool global para un tipo.
     */
    public static function caramelosDisponibles(string $tipo): int
    {
        return (int) (CarameloTipo::where('tipo', $tipo)->value('cantidad') ?? 0);
    }

    /**
     * Registra la exp de tipo consumida en `reclutados_exp_tipo` tras evolucionar.
     * Las filas que llegan a 0 o menos se eliminan.
     *
     * @param  list<string>  $tipos
     */
    public static function consumirExpTipo(Reclutado $reclutado, array $tipos, int $umbral): void
    {
        foreach ($tipos as $tipo) {
            $expTipo = ReclutadoExpTipo::where('reclutado_id', $reclutado->id)
                ->where('tipo', $tipo)
                ->first();

            if ($expTipo === null) {
                continue;
            }

            if ($expTipo->cantidad <= $umbral) {
                $expTipo->delete();
            } else {
                $expTipo->decrement('cantidad', $umbral);
            }
        }
    }
}
