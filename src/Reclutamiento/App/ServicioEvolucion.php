<?php

declare(strict_types=1);

namespace Src\Reclutamiento\App;

use App\Models\PlayerInventory;
use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Support\ItemCatalogo;
use Src\Shared\Domain\NivelHelper;
use Src\Shared\Domain\SlugTipo;

/**
 * Lógica de evolución por caramelos de tipo.
 *
 * La siguiente evolución se resuelve con la tabla `pokemon_evolution`:
 * `evolves_from_species_id` es el species_id del pokémon actual y
 * `evolved_species_id` apunta directamente a `pokemon.id` de la siguiente
 * etapa. Las formas alternas (mega/gmax, id ≥ 10000) se excluyen.
 *
 * La exp de tipo vive en el JSON `reclutados.exp` (cast ExpReclutado) y los
 * caramelos en `player_inventory` del jugador (item_key `tipo:{slug}`).
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
     * Requisitos completos para la vista, con la exp de tipo del JSON y los
     * caramelos del inventario del jugador.
     *
     * @return list<array{tipo: string, slug: string, necesario: int, actual: int, caramelosDisponibles: int}>
     */
    public static function requisitos(Reclutado $reclutado, int $userId): array
    {
        $siguiente = self::siguienteEvolucion($reclutado->pokemon);
        if ($siguiente === null) {
            return [];
        }

        $umbral = self::umbralParaNivel(self::nivelDe($reclutado));

        return array_map(
            fn (string $tipo): array => self::requisitoTipo($tipo, $umbral, $reclutado, $userId),
            self::tiposRequeridos($siguiente),
        );
    }

    /**
     * Requisito de UN tipo: umbral compartido, exp de tipo del JSON y caramelos
     * del inventario del jugador.
     *
     * @return array{tipo: string, slug: string, necesario: int, actual: int, caramelosDisponibles: int}
     */
    private static function requisitoTipo(string $tipo, int $umbral, Reclutado $reclutado, int $userId): array
    {
        return [
            'tipo' => $tipo,
            'slug' => SlugTipo::de($tipo),
            'necesario' => $umbral,
            'actual' => $reclutado->exp->expTipo($tipo),
            'caramelosDisponibles' => self::caramelosDisponibles($tipo, $userId),
        ];
    }

    /**
     * True si el reclutado cumple todos los requisitos de la evolución.
     */
    public static function puedeEvolucionar(Reclutado $reclutado, int $userId): bool
    {
        $requisitos = self::requisitos($reclutado, $userId);
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
     * Nivel actual del reclutado a partir de su exp total (cast ExpReclutado).
     */
    public static function nivelDe(Reclutado $reclutado): int
    {
        return NivelHelper::nivelDesdeExperiencia($reclutado->exp->total());
    }

    /**
     * Caramelos disponibles en el inventario del jugador para un tipo.
     */
    public static function caramelosDisponibles(string $tipo, int $userId): int
    {
        return (int) (PlayerInventory::where('user_id', $userId)
            ->where('item_key', ItemCatalogo::keyTipo($tipo))
            ->value('cantidad') ?? 0);
    }

    /**
     * Consume la exp de tipo del JSON `reclutados.exp` tras evolucionar
     * (cast ExpReclutado::consumirTipos: resta el umbral y elimina ≤ 0).
     *
     * @param  list<string>  $tipos
     */
    public static function consumirExpTipo(Reclutado $reclutado, array $tipos, int $umbral): void
    {
        $reclutado->exp = $reclutado->exp->consumirTipos($tipos, $umbral);
        $reclutado->save();
    }
}
