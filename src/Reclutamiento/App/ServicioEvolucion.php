<?php

declare(strict_types=1);

namespace Src\Reclutamiento\App;

use App\Models\PlayerInventory;
use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Support\ItemCatalogo;
use Src\Reclutamiento\App\Collections\PokemonCollection;
use Src\Reclutamiento\Domain\Collections\OpcionEvolucionCollection;
use Src\Reclutamiento\Domain\Collections\RequisitoEvolucionCollection;
use Src\Reclutamiento\Domain\DataTransferObjects\OpcionEvolucion;
use Src\Reclutamiento\Domain\DataTransferObjects\RequisitoEvolucion;
use Src\Reclutamiento\Domain\ValueObjects\InventarioCaramelos;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\NivelHelper;
use Src\Shared\Domain\SlugTipo;

/**
 * Lógica de evolución por caramelos de tipo.
 *
 * La siguiente evolución se resuelve con la tabla `pokemon_evolution`:
 * `evolves_from_species_id` es el species_id del pokémon actual y
 * `evolved_species_id` apunta directamente a `pokemon.id` de la siguiente
 * etapa. El seeder omite las formas alternas (mega/gmax), por lo que las
 * evoluciones regionales (Alola/Galar/Hisui/Paldea) sí se incluyen.
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
     * Devuelve TODAS las evoluciones válidas del pokémon (caso Eevee: varias),
     * con orden determinístico por minimum_level y evolved_species_id.
     * El seeder omite las formas alternas (mega/gmax), por lo que no es
     * necesario excluirlas por id.
     */
    public static function opcionesEvolucion(Pokemon $pokemon): PokemonCollection
    {
        $ids = self::idsEvolucion($pokemon);
        if ($ids->isEmpty()) {
            return new PokemonCollection();
        }

        $pokemons = Pokemon::query()
            ->whereIn('id', $ids)
            ->with('types')
            ->get()
            ->keyBy('id');

        return new PokemonCollection(
            $ids
                ->map(fn (int $id): Pokemon => $pokemons->get($id))
                ->filter()
                ->values()
                ->all()
        );
    }

    /**
     * IDs de las evoluciones válidas del pokémon en orden determinístico.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private static function idsEvolucion(Pokemon $pokemon)
    {
        return PokemonEvolution::query()
            ->where('evolves_from_species_id', $pokemon->species_id)
            ->orderBy('minimum_level')
            ->orderBy('evolved_species_id')
            ->pluck('evolved_species_id');
    }

    /**
     * Devuelve la siguiente evolución del pokémon (o null si no hay).
     */
    public static function siguienteEvolucion(Pokemon $pokemon): ?Pokemon
    {
        return self::opcionesEvolucion($pokemon)->primero();
    }

    /**
     * Nombres de los tipos requeridos para la siguiente evolución.
     */
    public static function tiposRequeridos(?Pokemon $siguiente): StringCollection
    {
        if ($siguiente === null) {
            return new StringCollection();
        }

        return new StringCollection(
            $siguiente->types
                ->map(fn (PokemonType $tipo): string => $tipo->tipo_nombre)
                ->values()
                ->all()
        );
    }

    /**
     * Requisitos completos para la vista (primera opción), con la exp de tipo
     * del JSON y los caramelos del inventario del jugador.
     */
    public static function requisitos(Reclutado $reclutado, int $userId): RequisitoEvolucionCollection
    {
        $siguiente = self::siguienteEvolucion($reclutado->pokemon);

        return $siguiente !== null
            ? self::requisitosPara($reclutado, $siguiente, $userId)
            : new RequisitoEvolucionCollection();
    }

    /**
     * Requisitos para UN destino concreto: umbral del nivel actual y, por cada
     * tipo requerido del destino, la exp de tipo del JSON y los caramelos del
     * inventario del jugador.
     */
    public static function requisitosPara(Reclutado $reclutado, Pokemon $destino, int $userId): RequisitoEvolucionCollection
    {
        $umbral = self::umbralParaNivel(self::nivelDe($reclutado));
        $requisitos = new RequisitoEvolucionCollection();

        foreach (self::tiposRequeridos($destino) as $tipo) {
            $requisitos->add(self::requisitoTipo($tipo, $umbral, $reclutado, $userId));
        }

        return $requisitos;
    }

    /**
     * Requisitos de TODAS las opciones de evolución del pokémon. Consulta el
     * inventario del usuario en bloque (una sola query de PlayerInventory, no
     * una por tipo/opción) para evitar N+1.
     */
    public static function requisitosDeOpciones(Reclutado $reclutado, int $userId): OpcionEvolucionCollection
    {
        $opciones = self::opcionesEvolucion($reclutado->pokemon);

        $resultado = new OpcionEvolucionCollection();

        if ($opciones->isEmpty()) {
            return $resultado;
        }

        $inventario = self::caramelosDeOpciones($opciones, $userId);
        $umbral = self::umbralParaNivel(self::nivelDe($reclutado));

        foreach ($opciones as $opcion) {
            $resultado->add(self::opcionRequisitos($opcion, $umbral, $reclutado, $inventario));
        }

        return $resultado;
    }

    private static function caramelosDeOpciones(PokemonCollection $opciones, int $userId): InventarioCaramelos
    {
        $slugs = [];
        foreach ($opciones as $opcion) {
            foreach (self::tiposRequeridos($opcion) as $tipo) {
                $slugs[ItemCatalogo::keyTipo($tipo)] = true;
            }
        }

        if ($slugs === []) {
            return InventarioCaramelos::vacio();
        }

        return new InventarioCaramelos(
            PlayerInventory::query()
                ->where('user_id', $userId)
                ->whereIn('item_key', array_keys($slugs))
                ->pluck('cantidad', 'item_key')
                ->all()
        );
    }

    private static function opcionRequisitos(Pokemon $opcion, int $umbral, Reclutado $reclutado, InventarioCaramelos $inventario): OpcionEvolucion
    {
        $requisitos = new RequisitoEvolucionCollection();

        foreach (self::tiposRequeridos($opcion) as $tipo) {
            $requisitos->add(self::requisitoTipoConInventario($tipo, $umbral, $reclutado, $inventario));
        }

        return new OpcionEvolucion(
            pokemonId: $opcion->id,
            nombre: $opcion->name,
            imagen: "/images/iconos_webp/{$opcion->id}.webp",
            requisitos: $requisitos,
            puedeEvolucionar: $requisitos->cumple(),
        );
    }

    /**
     * Requisito de UN tipo: umbral compartido, exp de tipo del JSON y caramelos
     * del inventario del jugador.
     */
    private static function requisitoTipo(string $tipo, int $umbral, Reclutado $reclutado, int $userId): RequisitoEvolucion
    {
        return self::requisitoTipoConInventario(
            $tipo,
            $umbral,
            $reclutado,
            new InventarioCaramelos([ItemCatalogo::keyTipo($tipo) => self::caramelosDisponibles($tipo, $userId)]),
        );
    }

    private static function requisitoTipoConInventario(string $tipo, int $umbral, Reclutado $reclutado, InventarioCaramelos $inventario): RequisitoEvolucion
    {
        return new RequisitoEvolucion(
            tipo: $tipo,
            slug: SlugTipo::de($tipo),
            necesario: $umbral,
            actual: $reclutado->exp->expTipo($tipo),
            caramelosDisponibles: $inventario->cantidad(ItemCatalogo::keyTipo($tipo)),
        );
    }

    /**
     * True si el reclutado cumple todos los requisitos de la evolución (primera
     * opción).
     */
    public static function puedeEvolucionar(Reclutado $reclutado, int $userId): bool
    {
        return self::puedeEvolucionarPara($reclutado, $userId, self::siguienteEvolucion($reclutado->pokemon));
    }

    /**
     * True si el reclutado cumple todos los requisitos de UN destino concreto.
     */
    public static function puedeEvolucionarPara(Reclutado $reclutado, int $userId, ?Pokemon $destino): bool
    {
        if ($destino === null) {
            return false;
        }

        return self::requisitosPara($reclutado, $destino, $userId)->cumple();
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
     */
    public static function consumirExpTipo(Reclutado $reclutado, StringCollection $tipos, int $umbral): void
    {
        $reclutado->exp = $reclutado->exp->consumirTipos($tipos->toList(), $umbral);
        $reclutado->save();
    }
}
