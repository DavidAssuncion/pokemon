<?php

declare(strict_types=1);

namespace Src\Shared\UI;

use Src\Shared\Tipos\TipoPokemon;

/**
 * Fuente única de presentación de los 18 tipos de Pokémon.
 *
 * Expone etiqueta, slug ASCII, clases Tailwind literales e icono para badges
 * server-side (Blade) y client-side (Alpine/JS). No contiene lógica de negocio.
 */
final class TipoBadges
{
    /**
     * Clases Tailwind literales por slug.
     *
     * Paleta canónica: `tipoClase` de `resources/views/equipos/index.blade.php`,
     * la misma del badge de `equipos/partials/movimiento-card.blade.php`.
     *
     * @var array<string, string>
     */
    private const CLASES = [
        'normal' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'lucha' => 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
        'volador' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300',
        'veneno' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300',
        'tierra' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300',
        'roca' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300',
        'bicho' => 'bg-lime-100 text-lime-700 dark:bg-lime-900/50 dark:text-lime-300',
        'fantasma' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300',
        'acero' => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
        'fuego' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-300',
        'agua' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
        'planta' => 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300',
        'electrico' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300',
        'psiquico' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/50 dark:text-pink-300',
        'hielo' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/50 dark:text-cyan-300',
        'dragon' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/50 dark:text-violet-300',
        'siniestro' => 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        'hada' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300',
    ];

    /**
     * Presentación de un tipo desconocido.
     *
     * `id` 0, `slug` e `icono` vacíos: no existe asset genérico, por lo que el
     * consumidor decide si oculta la imagen. Se usa como fallback de todos los
     * métodos de resolución.
     *
     * @var array{id:int,label:string,slug:string,clases:string,icono:string}
     */
    public const DEFAULT = [
        'id' => 0,
        'label' => 'Desconocido',
        'slug' => '',
        'clases' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'icono' => '',
    ];

    /**
     * Badge de un tipo por id de `TipoPokemon` (1-18).
     *
     * @return array{id:int,label:string,slug:string,clases:string,icono:string}
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
     * @return array{id:int,label:string,slug:string,clases:string,icono:string}
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
     * @return array{id:int,label:string,slug:string,clases:string,icono:string}
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
     * @return array{id:int,label:string,slug:string,clases:string,icono:string}
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
     * Clases Tailwind del tipo; las de `DEFAULT` si no se reconoce.
     */
    public static function clases(int|string|null $tipo): string
    {
        return self::resolver($tipo)['clases'];
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
     * @return array<string, array{label:string,clases:string,icono:string}>
     */
    public static function paraJs(): array
    {
        $datos = [];

        foreach (TipoPokemon::cases() as $tipo) {
            $badge = self::para($tipo);
            $datos[$badge['slug']] = [
                'label' => $badge['label'],
                'clases' => $badge['clases'],
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
     * @return array{id:int,label:string,slug:string,clases:string,icono:string}
     */
    private static function para(TipoPokemon $tipo): array
    {
        $slug = $tipo->slug();

        return [
            'id' => $tipo->value,
            'label' => $tipo->label(),
            'slug' => $slug,
            'clases' => self::CLASES[$slug] ?? self::DEFAULT['clases'],
            'icono' => '/images/type/'.$slug.'.webp',
        ];
    }
}
