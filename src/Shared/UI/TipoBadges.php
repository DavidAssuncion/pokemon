<?php

declare(strict_types=1);

namespace Src\Shared\UI;

use Src\Shared\Tipos\TipoPokemon;

/**
 * Fuente única de presentación de los 18 tipos de Pokémon.
 *
 * Expone etiqueta, slug ASCII e icono para badges server-side (Blade) y
 * client-side (Alpine/JS). No contiene lógica de negocio.
 */
final class TipoBadges
{
    /**
     * Presentación de un tipo desconocido.
     *
     * `id` 0, `slug` e `icono` vacíos: no existe asset genérico, por lo que el
     * consumidor decide si oculta la imagen. Se usa como fallback de todos los
     * métodos de resolución.
     *
     * @var array{id:int,label:string,slug:string,icono:string}
     */
    public const DEFAULT = [
        'id' => 0,
        'label' => 'Desconocido',
        'slug' => '',
        'icono' => '',
    ];

    /**
     * Badge de un tipo por id de `TipoPokemon` (1-18).
     *
     * @return array{id:int,label:string,slug:string,icono:string}
     */
    public static function porId(int $id): array
    {
        $tipo = TipoPokemon::tryFrom($id);

        return $tipo === null ? self::DEFAULT : self::para($tipo);
    }

    /**
     * Badge de un tipo por slug ASCII (`electrico`, `psiquico`, `dragon`).
     *
     * La comparación ignora mayúsculas y espacios sobrantes.
     *
     * @return array{id:int,label:string,slug:string,icono:string}
     */
    public static function porSlug(string $slug): array
    {
        $slug = strtolower(trim($slug));

        foreach (TipoPokemon::cases() as $tipo) {
            if ($tipo->slug() === $slug) {
                return self::para($tipo);
            }
        }

        return self::DEFAULT;
    }

    /**
     * Badge de un tipo por etiqueta en español (`Eléctrico`, `Psíquico`).
     *
     * La comparación ignora mayúsculas y espacios sobrantes.
     *
     * @return array{id:int,label:string,slug:string,icono:string}
     */
    public static function porLabel(string $label): array
    {
        $label = mb_strtolower(trim($label));

        foreach (TipoPokemon::cases() as $tipo) {
            if (mb_strtolower($tipo->label()) === $label) {
                return self::para($tipo);
            }
        }

        return self::DEFAULT;
    }

    /**
     * Resuelve un badge aceptando id, slug o etiqueta.
     *
     * Prioridad: id (int o cadena numérica), slug y etiqueta. Cualquier valor
     * nulo, vacío o desconocido devuelve `DEFAULT`.
     *
     * @return array{id:int,label:string,slug:string,icono:string}
     */
    public static function resolver(int|string|null $tipo): array
    {
        if ($tipo === null) {
            return self::DEFAULT;
        }

        if (is_int($tipo)) {
            return self::porId($tipo);
        }

        $tipo = trim($tipo);

        if ($tipo === '') {
            return self::DEFAULT;
        }

        if (ctype_digit($tipo)) {
            return self::porId((int) $tipo);
        }

        $slug = strtolower($tipo);

        foreach (TipoPokemon::cases() as $caso) {
            if ($caso->slug() === $slug) {
                return self::para($caso);
            }
        }

        return self::porLabel($tipo);
    }

    /**
     * Ruta del icono del tipo (`/images/type/{slug}.webp`).
     *
     * Devuelve cadena vacía si el tipo no se reconoce, porque no hay icono
     * genérico y el consumidor debe decidir cómo representarlo.
     */
    public static function icono(int|string|null $tipo): string
    {
        return self::resolver($tipo)['icono'];
    }

    /**
     * Etiquetas por id de tipo (id → label), para vistas que solo necesitan texto.
     *
     * @return array<int, string>
     */
    public static function etiquetasPorId(): array
    {
        $etiquetas = [];

        foreach (TipoPokemon::cases() as $tipo) {
            $etiquetas[$tipo->value] = $tipo->label();
        }

        return $etiquetas;
    }

    /**
     * Datos de badge por slug, listos para serializar a JavaScript.
     *
     * @return array<string, array{label:string,icono:string}>
     */
    public static function paraJs(): array
    {
        $datos = [];

        foreach (TipoPokemon::cases() as $tipo) {
            $badge = self::para($tipo);
            $datos[$badge['slug']] = [
                'label' => $badge['label'],
                'icono' => $badge['icono'],
            ];
        }

        return $datos;
    }

    /**
     * Correspondencia id → slug, para resolver ids desde JavaScript.
     *
     * @return array<int, string>
     */
    public static function idsParaJs(): array
    {
        $ids = [];

        foreach (TipoPokemon::cases() as $tipo) {
            $ids[$tipo->value] = $tipo->slug();
        }

        return $ids;
    }

    /**
     * Construye el badge de un tipo conocido.
     *
     * @return array{id:int,label:string,slug:string,icono:string}
     */
    private static function para(TipoPokemon $tipo): array
    {
        $slug = $tipo->slug();

        return [
            'id' => $tipo->value,
            'label' => $tipo->label(),
            'slug' => $slug,
            'icono' => '/images/type/'.$slug.'.webp',
        ];
    }
}
