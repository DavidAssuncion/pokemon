<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Pokemon;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Src\Shared\Domain\Collections\ItemCarameloCollection;
use Src\Shared\Domain\DataTransferObjects\ItemCaramelo;
use Src\Shared\Domain\DataTransferObjects\ItemResuelto;
use Src\Shared\Domain\SlugTipo;

/**
 * Catálogo de items del inventario (caramelos) resuelto por código, sin tabla.
 *
 * item_key canónico (Fase C): `familia:{evolution_chain_id}`, `ev:{stat}` (1-6)
 * y `tipo:{slug}` (slug ASCII: 'Eléctrico' → 'electrico', mismo criterio que la
 * migración 000008 con SlugTipo). El catálogo de imágenes replica el contrato
 * de la vista de exploraciones: candy_pokemon/{id}.webp, candy_ev/{slug}.webp,
 * candy_type/{slug}.webp y fallback único candy_pokemon/0.webp.
 */
final class ItemCatalogo
{
    public static function keyFamilia(int $chainId): string
    {
        return 'familia:'.$chainId;
    }

    public static function keyEv(int $stat): string
    {
        return 'ev:'.$stat;
    }

    public static function keyTipo(string $tipo): string
    {
        return 'tipo:'.SlugTipo::de($tipo);
    }

    /**
     * Convierte un ResultadoRecompensas en una ItemCarameloCollection listo
     * para el modal de victoria (familia vía ItemCatalogo, EV vía ItemCatalogo,
     * tipo vía slug directo).
     */
    public static function caramelosDeRecompensas(ResultadoRecompensas $recompensas): ItemCarameloCollection
    {
        $caramelos = new ItemCarameloCollection();

        foreach ($recompensas->caramelosFamilia as $recompensa) {
            $resuelto = self::resolve(self::keyFamilia($recompensa->evolutionChainId));
            $caramelos->add(new ItemCaramelo(
                nombre: $resuelto->nombre,
                imagen: $resuelto->imagen,
                cantidad: $recompensa->cantidad,
            ));
        }

        foreach ($recompensas->caramelosEv as $recompensa) {
            $resuelto = self::resolve(self::keyEv($recompensa->stat));
            $caramelos->add(new ItemCaramelo(
                nombre: $resuelto->nombre,
                imagen: $resuelto->imagen,
                cantidad: $recompensa->cantidad,
            ));
        }

        foreach ($recompensas->caramelosTipo as $recompensa) {
            $caramelos->add(new ItemCaramelo(
                nombre: $recompensa->tipo,
                imagen: '/images/candy_type/'.$recompensa->slug().'.webp',
                cantidad: $recompensa->cantidad,
            ));
        }

        return $caramelos;
    }

    /**
     * Resuelve un item_key a su presentación. Nunca lanza: cualquier clave
     * desconocida cae a 'Desconocido' + placeholder + categoria 'desconocida'.
     */
    public static function resolve(string $itemKey): ItemResuelto
    {
        if (str_starts_with($itemKey, 'familia:')) {
            $chainId = substr($itemKey, 8);

            return is_numeric($chainId)
                ? self::resolverFamilia((int) $chainId)
                : self::desconocido();
        }

        if (str_starts_with($itemKey, 'ev:')) {
            $stat = substr($itemKey, 3);

            return is_numeric($stat)
                ? self::resolverEv((int) $stat)
                : self::desconocido();
        }

        if (str_starts_with($itemKey, 'tipo:')) {
            return self::resolverTipo(substr($itemKey, 5));
        }

        return self::desconocido();
    }

    /**
     * Caramelo de familia: primer integrante = menor species_id (desempate id),
     * misma regla que TransformadorResultadoExploracion::pokemonBaseDeCadena.
     */
    private static function resolverFamilia(int $chainId): ItemResuelto
    {
        if ($chainId < 1) {
            return self::desconocido();
        }

        $base = Pokemon::query()
            ->where('evolution_chain_id', $chainId)
            ->orderBy('species_id')
            ->orderBy('id')
            ->first();

        if ($base === null) {
            return self::desconocido('familia');
        }

        return new ItemResuelto(
            nombre: $base->name,
            imagen: "/images/candy_pokemon/{$base->id}.webp",
            categoria: 'familia',
        );
    }

    private static function resolverEv(int $stat): ItemResuelto
    {
        $slug = StatEnum::fromId($stat)?->slug();
        $nombre = StatEnum::fromId($stat)?->label();

        if ($slug === null || $nombre === null) {
            // Stat fuera de rango 1-6: es una clave EV válida pero irresoluble.
            return self::desconocido('ev');
        }

        return new ItemResuelto(
            nombre: $nombre,
            imagen: "/images/candy_ev/{$slug}.webp",
            categoria: 'ev',
        );
    }

    private static function resolverTipo(string $slug): ItemResuelto
    {
        $nombre = null;
        foreach (TipoEnum::cases() as $caso) {
            if (SlugTipo::de($caso->label()) === $slug) {
                $nombre = $caso->label();
                break;
            }
        }

        if ($nombre === null) {
            return self::desconocido('tipo');
        }

        return new ItemResuelto(
            nombre: $nombre,
            imagen: "/images/candy_type/{$slug}.webp",
            categoria: 'tipo',
        );
    }

    private static function desconocido(string $categoria = 'desconocida'): ItemResuelto
    {
        return new ItemResuelto(
            nombre: 'Desconocido',
            imagen: '/images/candy_pokemon/0.webp',
            categoria: $categoria,
        );
    }
}
