<?php

declare(strict_types=1);

namespace Src\Exploraciones\Presentation;

use App\Enums\StatEnum;

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
        $expReclutado = (int) ($terminada['exp_reclutado'] ?? $resultado['exp_reclutado'] ?? 0);
        $resultadoCat = $resultado['resultado'] ?? null;

        $objetosPerdidos = self::objetosPerdidos(
            $terminada['objetos_perdidos'] ?? $resultado['objetos_perdidos'] ?? [],
        );

        $tieneDerrotados = $derrotados !== [];
        $tieneCapturados = $capturados !== [];
        $tieneRecompensas = $caramelosFamilia !== [] || $caramelosEv !== [] || $caramelosTipo !== [];
        $tienePerdidas = $objetosPerdidos !== [];
        $tieneObtenidas = $tieneCapturados || $tieneRecompensas;
        $tieneResumen = self::labelResultado($resultadoCat) !== null
            || $incidentes !== []
            || $durationReal > 0
            || $tiempoPerdido > 0;

        [$spanDerrotados, $spanPerdidas, $spanObtenidas] = self::spansTresColumnas(
            $tieneDerrotados,
            $tienePerdidas,
            $tieneObtenidas,
        );
        $spanCapturados = '';
        $spanRecompensas = $spanObtenidas;

        $vista = [
            'capturados' => $capturados,
            'caramelos_familia' => $caramelosFamilia,
            'caramelos_ev' => $caramelosEv,
            'caramelos_tipo' => $caramelosTipo,
            'objetos_perdidos' => $objetosPerdidos,
            'motivo_perdida' => $terminada['motivo_perdida'] ?? null,
            'incidentes' => $incidentes,
            'duration_real' => $durationReal,
            'tiempo_perdido' => $tiempoPerdido,
            'efectivos' => max(0, $durationReal - $tiempoPerdido),
            'exp' => (int) ($resultado['exp'] ?? 0),
            'exp_reclutado' => $expReclutado,
            'hitos' => HitoExploracion::desde($resultado),
            'label' => self::labelResultado($resultadoCat),
            'clase' => self::claseResultado($resultadoCat),
            'tiene_resumen' => $tieneResumen,
            'tiene_resultado' => $resultado !== [],
            'tiene_derrotados' => $tieneDerrotados,
            'tiene_capturados' => $tieneCapturados,
            'tiene_recompensas' => $tieneRecompensas,
            'tiene_perdidas' => $tienePerdidas,
            'tiene_obtenidas' => $tieneObtenidas,
            'span_derrotados' => $spanDerrotados,
            'span_capturados' => $spanCapturados,
            'span_recompensas' => $spanRecompensas,
            'span_perdidas' => $spanPerdidas,
            'span_obtenidas' => $spanObtenidas,
        ];

        $terminada['vista'] = $vista;
        $terminada['exp_reclutado'] = $vista['exp_reclutado'];
        $terminada['hitos'] = $vista['hitos'];
        $terminada['capturados'] = $capturados;
        $terminada['derrotadosAgrupados'] = $derrotados;
        $terminada['tieneDerrotados'] = $tieneDerrotados;
        $terminada['tieneCapturados'] = $tieneCapturados;
        $terminada['tieneRecompensas'] = $tieneRecompensas;
        $terminada['tiene_perdidas'] = $tienePerdidas;
        $terminada['tiene_obtenidas'] = $tieneObtenidas;
        $terminada['spanDerrotados'] = $spanDerrotados;
        $terminada['spanCapturados'] = $spanCapturados;
        $terminada['spanRecompensas'] = $spanRecompensas;
        $terminada['span_derrotados'] = $spanDerrotados;
        $terminada['span_perdidas'] = $spanPerdidas;
        $terminada['span_obtenidas'] = $spanObtenidas;
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
     * Reparto de columnas del grid lg:grid-cols-3: derrotados, pérdidas y
     * recompensas obtenidas.
     *
     * @return array{string, string, string}
     */
    private static function spansTresColumnas(bool $derrotados, bool $perdidas, bool $obtenidas): array
    {
        if ($obtenidas) {
            return [
                $derrotados ? 'lg:col-span-1' : '',
                $perdidas ? 'lg:col-span-1' : '',
                'lg:col-span-'.(3 - (int) $derrotados - (int) $perdidas),
            ];
        }

        if ($derrotados && $perdidas) {
            return ['lg:col-span-2', 'lg:col-span-1', ''];
        }

        if ($derrotados) {
            return ['lg:col-span-3', '', ''];
        }

        if ($perdidas) {
            return ['', 'lg:col-span-3', ''];
        }

        return ['', '', ''];
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
     * Conserva los campos enriquecidos por `PresentadorExploraciones` y, en
     * llamadas directas con datos antiguos, deriva `tipo_label` e `imagen`
     * cuando no requieren consultar la base de datos.
     *
     * @param  list<array<string, mixed>>  $objetos
     * @return list<array{tipo: string, tipo_label: string, id: int|string, label: string|null, imagen: string|null, cantidad_perdida: int}>
     */
    private static function objetosPerdidos(array $objetos): array
    {
        return array_map(static function (array $objeto): array {
            $tipo = (string) ($objeto['tipo'] ?? '');
            $id = $objeto['id'] ?? '';
            $imagen = $objeto['imagen'] ?? null;

            if (! is_string($imagen) || $imagen === '') {
                $imagen = self::imagenDerivada($tipo, $id);
            }

            return [
                'tipo' => $tipo,
                'tipo_label' => self::tipoLabelDerivado($tipo, $objeto['tipo_label'] ?? null),
                'id' => $id,
                'label' => self::labelPerdida($objeto['label'] ?? null),
                'imagen' => $imagen,
                'cantidad_perdida' => (int) ($objeto['cantidad_perdida'] ?? 0),
            ];
        }, $objetos);
    }

    private static function tipoLabelDerivado(string $tipo, mixed $tipoLabel): string
    {
        if (is_string($tipoLabel) && trim($tipoLabel) !== '') {
            return $tipoLabel;
        }

        return match ($tipo) {
            'familia' => 'Familia',
            'ev' => 'EV',
            'tipo' => 'Tipo',
            'captura' => 'Captura',
            default => ucfirst($tipo),
        };
    }

    private static function imagenDerivada(string $tipo, mixed $id): ?string
    {
        if ($tipo === 'ev') {
            $stat = StatEnum::fromId((int) $id);

            return $stat !== null ? "/images/candy_ev/{$stat->slug()}.webp" : null;
        }

        if ($tipo === 'tipo') {
            $slug = (string) $id;

            return $slug !== '' ? "/images/candy_type/{$slug}.webp" : null;
        }

        return null;
    }

    private static function labelPerdida(mixed $label): ?string
    {
        if (! is_scalar($label)) {
            return null;
        }

        $texto = trim((string) $label);

        return $texto === '' || is_numeric($texto) ? null : $texto;
    }
}
