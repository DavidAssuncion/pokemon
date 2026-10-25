<?php

declare(strict_types=1);

namespace Src\Exploraciones\Presentation;

/**
 * Presentador puro del resumen de una exploración terminada.
 *
 * Calcula etiquetas, clases, agrupaciones y rutas de recompensas que antes se
 * resolvían con bloques `@php` en `exploraciones/index.blade.php`.
 */
final class ResultadoExploracionVista
{
    /**
     * @param  array<string, mixed>  $terminada
     * @return array<string, mixed>
     */
    public static function toView(array $terminada): array
    {
        $resultado = is_array($terminada['resultado'] ?? null) ? $terminada['resultado'] : [];
        $capturados = self::capturados($resultado['capturados'] ?? []);
        $caramelosFamilia = self::caramelosFamilia($resultado['caramelos_familia'] ?? []);
        $caramelosEv = self::caramelosEv($resultado['caramelos_ev'] ?? []);
        $caramelosTipo = self::caramelosTipo($resultado['caramelos_tipo'] ?? []);
        $derrotados = self::derrotados($terminada['derrotados'] ?? []);
        $incidentes = is_array($resultado['incidentes'] ?? null) ? $resultado['incidentes'] : [];
        $durationReal = (int) ($resultado['duration_real'] ?? 0);
        $tiempoPerdido = (int) ($resultado['tiempo_perdido'] ?? 0);
        $resultadoCat = $resultado['resultado'] ?? null;

        $tieneDerrotados = $derrotados !== [];
        $tieneCapturados = $capturados !== [];
        $tieneRecompensas = $caramelosFamilia !== [] || $caramelosEv !== [] || $caramelosTipo !== [];
        $tieneResumen = self::labelResultado($resultadoCat) !== null
            || $incidentes !== []
            || $durationReal > 0
            || $tiempoPerdido > 0;

        [$spanDerrotados, $spanCapturados, $spanRecompensas] = self::spans(
            $tieneDerrotados,
            $tieneCapturados,
            $tieneRecompensas,
        );

        $vista = [
            'capturados' => $capturados,
            'caramelos_familia' => $caramelosFamilia,
            'caramelos_ev' => $caramelosEv,
            'caramelos_tipo' => $caramelosTipo,
            'objetos_perdidos' => self::objetosPerdidos($resultado['objetos_perdidos'] ?? []),
            'incidentes' => $incidentes,
            'duration_real' => $durationReal,
            'tiempo_perdido' => $tiempoPerdido,
            'efectivos' => max(0, $durationReal - $tiempoPerdido),
            'exp' => (int) ($resultado['exp'] ?? 0),
            'label' => self::labelResultado($resultadoCat),
            'clase' => self::claseResultado($resultadoCat),
            'tiene_resumen' => $tieneResumen,
            'tiene_resultado' => $resultado !== [],
            'tiene_derrotados' => $tieneDerrotados,
            'tiene_capturados' => $tieneCapturados,
            'tiene_recompensas' => $tieneRecompensas,
            'span_derrotados' => $spanDerrotados,
            'span_capturados' => $spanCapturados,
            'span_recompensas' => $spanRecompensas,
        ];

        $terminada['vista'] = $vista;
        $terminada['capturados'] = $capturados;
        $terminada['derrotadosAgrupados'] = $derrotados;
        $terminada['tieneDerrotados'] = $tieneDerrotados;
        $terminada['tieneCapturados'] = $tieneCapturados;
        $terminada['tieneRecompensas'] = $tieneRecompensas;
        $terminada['spanDerrotados'] = $spanDerrotados;
        $terminada['spanCapturados'] = $spanCapturados;
        $terminada['spanRecompensas'] = $spanRecompensas;
        $terminada['_vista'] = true;

        return $terminada;
    }

    private static function labelResultado(mixed $resultado): ?string
    {
        return match ($resultado) {
            'exito_excepcional' => 'Éxito excepcional',
            'exito' => 'Éxito',
            'exito_parcial' => 'Éxito parcial',
            'fracaso' => 'Fracaso',
            'retirada' => 'Retirada',
            default => null,
        };
    }

    private static function claseResultado(mixed $resultado): ?string
    {
        return match ($resultado) {
            'exito_excepcional' => 'bg-emerald-400 text-emerald-950 dark:bg-emerald-500 dark:text-emerald-950',
            'exito' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
            'exito_parcial' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
            'fracaso' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
            'retirada' => 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300',
            default => null,
        };
    }

    /**
     * Reparto de columnas del grid lg:grid-cols-4.
     *
     * @return array{string, string, string}
     */
    private static function spans(bool $derrotados, bool $capturados, bool $recompensas): array
    {
        if ($recompensas) {
            $spanDerrotados = $derrotados ? 'lg:col-span-1' : '';
            $spanCapturados = $capturados ? 'lg:col-span-1' : '';

            if ($derrotados && $capturados) {
                $spanRecompensas = 'lg:col-span-2';
            } elseif ($derrotados || $capturados) {
                $spanRecompensas = 'lg:col-span-3';
            } else {
                $spanRecompensas = 'lg:col-span-4';
            }

            return [$spanDerrotados, $spanCapturados, $spanRecompensas];
        }

        if ($derrotados && $capturados) {
            return ['lg:col-span-2', 'lg:col-span-2', ''];
        }

        if ($derrotados) {
            return ['lg:col-span-4', '', ''];
        }

        return ['', 'lg:col-span-4', ''];
    }

    /**
     * @param  list<array<string, mixed>>  $capturados
     * @return list<array<string, mixed>>
     */
    private static function capturados(array $capturados): array
    {
        return array_map(static function (array $capturado): array {
            $id = (int) ($capturado['pokemon_id'] ?? 0);
            $nombre = $capturado['nombre'] ?? null;

            return array_merge($capturado, [
                'pokemon_id' => $id,
                'nombre' => $nombre,
                'cantidad' => (int) ($capturado['cantidad'] ?? 0),
                'icono' => "/images/iconos_webp/{$id}.webp",
            ]);
        }, $capturados);
    }

    /**
     * @param  list<array<string, mixed>>  $caramelos
     * @return list<array<string, mixed>>
     */
    private static function caramelosFamilia(array $caramelos): array
    {
        return array_map(static function (array $caramelo): array {
            $pokemonId = $caramelo['pokemon_id'] ?? null;
            $nombre = $caramelo['nombre'] ?? null;

            return array_merge($caramelo, [
                'src' => $pokemonId !== null && $pokemonId !== 0
                    ? "/images/candy_pokemon/{$pokemonId}.webp"
                    : '/images/candy_pokemon/0.webp',
                'alt' => 'Caramelo de '.($nombre ?? 'Pokémon'),
                'nombre' => $nombre,
                'cantidad' => (int) ($caramelo['cantidad'] ?? 0),
            ]);
        }, $caramelos);
    }

    /**
     * @param  list<array<string, mixed>>  $caramelos
     * @return list<array<string, mixed>>
     */
    private static function caramelosEv(array $caramelos): array
    {
        return array_map(static function (array $caramelo): array {
            $slug = $caramelo['stat_slug'] ?? null;
            $nombre = $caramelo['stat_nombre'] ?? null;
            $stat = (int) ($caramelo['stat'] ?? 0);

            return array_merge($caramelo, [
                'src' => $slug !== null && $slug !== ''
                    ? "/images/candy_ev/{$slug}.webp"
                    : '/images/candy_ev/.webp',
                'alt' => 'Caramelo EV '.($nombre ?? ''),
                'nombre' => $nombre,
                'nombre_js' => $nombre === null || $nombre === '' ? 'statName('.$stat.')' : null,
                'cantidad' => (int) ($caramelo['cantidad'] ?? 0),
            ]);
        }, $caramelos);
    }

    /**
     * @param  list<array<string, mixed>>  $caramelos
     * @return list<array<string, mixed>>
     */
    private static function caramelosTipo(array $caramelos): array
    {
        return array_map(static function (array $caramelo): array {
            $tipo = $caramelo['tipo'] ?? null;
            $slug = $caramelo['slug'] ?? '';

            return array_merge($caramelo, [
                'src' => '/images/candy_type/'.$slug.'.webp',
                'alt' => $tipo ?? 'Caramelo de tipo',
                'nombre' => $tipo,
                'cantidad' => (int) ($caramelo['cantidad'] ?? 0),
            ]);
        }, $caramelos);
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{pokemon_id: int, cantidad: int, icono: string}>
     */
    private static function derrotados(array $ids): array
    {
        $conteo = [];
        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }
            $conteo[$id] = ($conteo[$id] ?? 0) + 1;
        }

        $derrotados = [];
        foreach ($conteo as $id => $cantidad) {
            $derrotados[] = [
                'pokemon_id' => $id,
                'cantidad' => $cantidad,
                'icono' => "/images/iconos_webp/{$id}.webp",
            ];
        }

        return $derrotados;
    }

    /**
     * @param  list<array<string, mixed>>  $objetos
     * @return list<array<string, mixed>>
     */
    private static function objetosPerdidos(array $objetos): array
    {
        return array_map(static function (array $objeto): array {
            $tipo = $objeto['tipo'] ?? 'Objeto';
            $detalle = $objeto['label'] ?? $objeto['id'] ?? '';

            return array_merge($objeto, [
                'tipo' => $tipo,
                'label' => $detalle,
                'cantidad_perdida' => (int) ($objeto['cantidad_perdida'] ?? 0),
            ]);
        }, $objetos);
    }
}
