<?php

declare(strict_types=1);

namespace Src\Pokemon\App;

use App\Models\Pokemon;
use App\Models\Reclutado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Grupos de versiones/formas intercambiables.
 *
 * Solo los miembros de un mismo grupo pueden cambiar entre sí. La forma
 * "original" del reclutado siempre está disponible; las alternativas se
 * desbloquean una vez pagando la exp de tipo equivalente al nivel 55.
 */
final class ServicioVersiones
{
    public static function nivelDesbloqueo(): int
    {
        return (int) config('pokemon_versions.nivel_desbloqueo', 55);
    }

    /**
     * @return list<int>
     */
    public static function grupoDe(int $pokemonId): array
    {
        foreach (self::grupos() as $grupo) {
            if (in_array($pokemonId, $grupo, true)) {
                return $grupo;
            }
        }

        return [$pokemonId];
    }

    public static function esVersionable(int $pokemonId): bool
    {
        return count(self::grupoDe($pokemonId)) > 1;
    }

    public static function sonDelMismoGrupo(int $pokemonA, int $pokemonB): bool
    {
        return in_array($pokemonB, self::grupoDe($pokemonA), true);
    }

    /**
     * Forma original desde la que el reclutado entró en el grupo. Si aún no
     * está fijada (o quedó obsoleta tras una evolución), se fija a la actual.
     */
    public static function asegurarOriginal(Reclutado $reclutado): int
    {
        $grupo = self::grupoDe($reclutado->pokemon_id);
        $original = $reclutado->forma_original_id;

        if ($original === null || ! in_array($original, $grupo, true)) {
            $reclutado->forma_original_id = $reclutado->pokemon_id;
            $reclutado->save();

            return $reclutado->pokemon_id;
        }

        return $original;
    }

    /**
     * La forma actual y la original están siempre disponibles. El resto
     * requiere una fila en `reclutado_formas`.
     */
    public static function estaDesbloqueada(Reclutado $reclutado, int $pokemonId): bool
    {
        $original = self::asegurarOriginal($reclutado);

        if ($pokemonId === $reclutado->pokemon_id || $pokemonId === $original) {
            return true;
        }

        return DB::table('reclutado_formas')
            ->where('reclutado_id', $reclutado->id)
            ->where('pokemon_id', $pokemonId)
            ->exists();
    }

    /**
     * Registra una forma como desbloqueada. Se usa al entrar/salir de una
     * forma para que ninguna forma visitada se pierda al cambiar.
     */
    public static function registrarForma(Reclutado $reclutado, int $pokemonId): void
    {
        if (! self::esVersionable($pokemonId)) {
            return;
        }

        $ahora = now();

        DB::table('reclutado_formas')->insertOrIgnore([
            'reclutado_id' => $reclutado->id,
            'pokemon_id' => $pokemonId,
            'unlocked_at' => $ahora,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    public static function nombreVisible(Pokemon $pokemon): string
    {
        $nombre = trim(Str::headline($pokemon->name));

        return $nombre !== '' ? $nombre : $pokemon->name;
    }

    /**
     * @return list<list<int>>
     */
    private static function grupos(): array
    {
        /** @var array<string, array<int, int|string>> $configurados */
        $configurados = config('pokemon_versions.grupos', []);
        $grupos = [];

        foreach ($configurados as $grupo) {
            $ids = array_values(array_map(
                static fn (int|string $id): int => (int) $id,
                $grupo,
            ));

            if ($ids !== []) {
                $grupos[] = $ids;
            }
        }

        return $grupos;
    }
}
