<?php

declare(strict_types=1);

namespace Src\Exploraciones\Presentation;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use Src\Shared\Domain\SlugTipo;

/**
 * Presentador puro de un evento de bitácora de exploración.
 *
 * Centraliza los textos, iconos y rutas que antes se calculaban dentro de
 * `_evento.blade.php`. La vista sólo decide qué bloque HTML pintar según
 * `tipo`, pero no compone narrativa ni deriva nombres/rutas.
 *
 * `oculto` forma parte de la base del contrato: sólo `caramelo_tipo` lo marca
 * a `true` cuando no puede resolver el tipo, y las colecciones lo filtran.
 */
final class EventoExploracionVista
{
    /**
     * @param  array<string, mixed>  $evento
     * @param  array<array-key, string>  $nombres
     * @return array<string, mixed>
     */
    public static function toView(array $evento, array $nombres = [], bool $completarStats = false): array
    {
        if (($evento['_vista'] ?? false) === true) {
            return $evento;
        }

        $tipo = (string) ($evento['tipo'] ?? 'desconocido');
        $nombre = self::nombre($evento, $nombres);
        $pokemonId = isset($evento['pokemon_id']) ? (int) $evento['pokemon_id'] : null;
        $pokemonIds = self::ids($evento);
        $cantidad = (int) ($evento['cantidad'] ?? 1);
        $stat = (int) ($evento['stat'] ?? 0);
        $statNombre = $evento['stat_nombre'] ?? null;
        $statSlug = $evento['stat_slug'] ?? null;
        $statNombre = is_string($statNombre) ? $statNombre : null;
        $statSlug = is_string($statSlug) ? $statSlug : null;

        // El contrato histórico sólo completaba stat para el evento raíz
        // `caramelo_ev`; los hallazgos ya no dependen del fallback JS.
        $esCarameloEv = $tipo === 'caramelo_ev'
            || ($tipo === 'hallazgo' && ($evento['subtype'] ?? '') === 'caramelo_ev');

        if ($completarStats && $esCarameloEv) {
            $statEnum = StatEnum::fromId($stat);
            $statNombre ??= $statEnum?->label();
            $statSlug ??= $statEnum?->slug();
        }
        $duracion = (int) ($evento['duration_loss'] ?? $evento['duracion_minutos'] ?? $evento['duracion'] ?? 0);
        $sujeto = self::sujeto($evento);
        $subtype = (string) ($evento['subtype'] ?? '');
        $resolucion = (string) ($evento['resolucion'] ?? '');

        $vista = array_merge($evento, [
            '_vista' => true,
            'oculto' => false,
            'tipo' => $tipo,
            'nombre' => $nombre,
            'pokemon_id' => $pokemonId,
            'pokemon_ids' => $pokemonIds,
            'icono_pokemon' => $pokemonId !== null ? "/images/iconos_webp/{$pokemonId}.webp" : null,
            'iconos_pokemon' => array_map(
                static fn (int $id): string => "/images/iconos_webp/{$id}.webp",
                $pokemonIds,
            ),
            'cantidad' => $cantidad,
            'stat' => $stat,
            'stat_nombre' => $statNombre,
            'stat_slug' => $statSlug,
            'duracion' => $duracion,
            'subtype' => $subtype,
            'resolucion' => $resolucion,
        ]);

        return match ($tipo) {
            'pokemon' => array_merge($vista, [
                'texto' => "Encontraste un {$nombre}",
            ]),
            'huida' => array_merge($vista, [
                'texto' => "Un {$nombre} salvaje huye antes de que comience el combate.",
            ]),
            'emboscada' => array_merge($vista, self::emboscada($vista, $sujeto, $resolucion, $duracion, $nombres)),
            'contratiempo' => array_merge($vista, [
                'texto' => self::textoContratiempo($sujeto, $subtype, $duracion),
            ]),
            'retirada' => array_merge($vista, [
                'texto' => "{$sujeto} se retira: ".($evento['reason'] ?? 'motivo desconocido'),
                'reason' => $evento['reason'] ?? 'motivo desconocido',
            ]),
            'grupo' => array_merge($vista, [
                'texto' => "{$sujeto} se encuentra con un grupo salvaje",
            ]),
            'hallazgo' => array_merge($vista, self::hallazgo($vista, $subtype, $stat, $statNombre, $statSlug, $cantidad)),
            'caramelo_familia' => array_merge($vista, self::carameloFamilia($vista)),
            'caramelo_ev' => array_merge($vista, self::carameloEv($vista, $stat, $statNombre, $statSlug)),
            'encuentro' => array_merge($vista, self::encuentro($vista, $subtype, $resolucion, $duracion, $nombres)),
            'neutral' => array_merge($vista, [
                'texto' => 'Sin incidencias',
            ]),
            'combate' => array_merge($vista, [
                'resolucion' => $resolucion,
                'hp_final' => (int) ($evento['hp_final'] ?? 0),
                'combate_log' => (string) ($evento['combate_log'] ?? ''),
            ]),
            'descanso' => array_merge($vista, self::descanso($sujeto, $duracion, $evento)),
            'derrota' => array_merge($vista, self::derrota($evento, $sujeto)),
            'cancelada' => array_merge($vista, [
                'texto' => 'Expedición cancelada: '.($evento['motivo'] ?? 'motivo desconocido'),
                'motivo' => $evento['motivo'] ?? 'motivo desconocido',
            ]),
            default => array_merge($vista, [
                'texto' => 'Evento: '.$tipo.($subtype !== '' ? " ({$subtype})" : ''),
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $vista
     * @param  array<array-key, string>  $nombres
     * @return array{titulo: string, subtitulo: string, rondas: int|null, rival_nombre: string|null, detalle_derrota: string|null}
     */
    private static function emboscada(array $vista, string $sujeto, string $resolucion, int $duracion, array $nombres = []): array
    {
        $subtitulo = "{$sujeto} repele el ataque".($duracion > 0 ? " (-{$duracion} min)" : '');

        if (
            str_contains($resolucion, 'huida')
            || str_contains($resolucion, 'escapa')
            || str_contains($resolucion, 'sin_combate')
        ) {
            $subtitulo = "{$sujeto} escapa perdiendo tiempo";
        } elseif (
            $resolucion !== ''
            && ! in_array($resolucion, ['evitada', 'superada', 'superada_con_cost', 'superada_con_coste'], true)
            && ! str_contains($resolucion, 'combate')
            && ! str_contains($resolucion, 'repele')
            && ! str_contains($resolucion, 'victoria')
        ) {
            $subtitulo = $resolucion;
        }

        $rondas = self::rondas($vista['log'] ?? null);

        return [
            'titulo' => '¡Emboscada!',
            'subtitulo' => $subtitulo,
            'rondas' => $rondas,
            'rival_nombre' => self::rivalNombre($vista, $nombres),
            'detalle_derrota' => self::detalleDerrota($vista, $rondas, $nombres),
        ];
    }

    private static function textoContratiempo(string $sujeto, string $subtype, int $duracion): string
    {
        $sufijo = $duracion > 0 ? " -{$duracion} min" : '';

        return match ($subtype) {
            'desorientacion' => "{$sujeto} pierde el rastro.{$sufijo}",
            'terreno' => "El terreno dificulta el avance.{$sufijo}",
            'clima' => "El clima empeora las condiciones.{$sufijo}",
            'bloqueo' => "Un obstáculo bloquea el camino.{$sufijo}",
            default => $subtype !== '' ? $subtype : "{$sujeto} sufre un contratiempo",
        };
    }

    /**
     * @param  array<string, mixed>  $evento
     * @return array{texto: string, duracion: int, hp_recuperado: float}
     */
    private static function descanso(string $sujeto, int $duracion, array $evento): array
    {
        $hpRecuperado = (float) ($evento['hp_recuperado'] ?? 0);
        $texto = $hpRecuperado > 0
            ? "{$sujeto} descansa y recupera ".self::numero($hpRecuperado).' HP'
            : "{$sujeto} descansa";

        return [
            'texto' => $texto,
            'duracion' => $duracion,
            'hp_recuperado' => $hpRecuperado,
        ];
    }

    /**
     * @param  array<string, mixed>  $evento
     */
    private static function sujeto(array $evento): string
    {
        $nombre = $evento['explorador_nombre'] ?? null;

        return is_string($nombre) && trim($nombre) !== '' ? $nombre : 'El explorador';
    }

    private static function numero(float $valor): string
    {
        return $valor === (float) (int) $valor
            ? (string) (int) $valor
            : rtrim(rtrim(number_format($valor, 1, '.', ''), '0'), '.');
    }

    /**
     * @param  array<string, mixed>  $vista
     * @return array<string, mixed>
     */
    private static function hallazgo(
        array $vista,
        string $subtype,
        int $stat,
        ?string $statNombre,
        ?string $statSlug,
        int $cantidad,
    ): array {
        if ($subtype === 'caramelo_familia') {
            return self::carameloFamilia($vista);
        }

        if ($subtype === 'caramelo_ev') {
            return self::carameloEv($vista, $stat, $statNombre, $statSlug);
        }

        if ($subtype === 'caramelo_tipo') {
            return self::carameloTipo($vista, $cantidad);
        }

        return ['texto' => 'Hallazgo registrado'];
    }

    /**
     * @param  array<string, mixed>  $vista
     * @return array{oculto: bool, candy_slug: string, candy_nombre: string|null, candy_src: string|null, candy_alt: string, cantidad: int}
     */
    private static function carameloTipo(array $vista, int $cantidad): array
    {
        $tipoId = isset($vista['tipo_id']) ? (int) $vista['tipo_id'] : 0;
        $tipo = $tipoId > 0 ? TipoEnum::fromId($tipoId) : null;

        $nombre = $tipo?->label();
        $slug = $tipo !== null ? SlugTipo::de($tipo->label()) : '';

        if ($nombre === null) {
            $slugLegacy = $vista['slug'] ?? null;
            $slugLegacy = is_string($slugLegacy) ? trim($slugLegacy) : '';

            $nombreLegacy = $vista['tipo_nombre'] ?? $vista['tipo_label'] ?? null;
            $nombreLegacy = is_string($nombreLegacy) ? trim($nombreLegacy) : '';

            if ($slugLegacy !== '') {
                $slug = $slugLegacy;
                $nombre = $nombreLegacy !== '' ? $nombreLegacy : ucfirst($slugLegacy);
            } elseif ($nombreLegacy !== '') {
                $nombre = $nombreLegacy;
                $slug = SlugTipo::de($nombreLegacy);
            }
        }

        $visible = $nombre !== null && $nombre !== '' && $slug !== '';

        return [
            'oculto' => ! $visible,
            'candy_slug' => $slug,
            'candy_nombre' => $visible ? $nombre : null,
            'candy_src' => $visible ? "/images/candy_type/{$slug}.webp" : null,
            'candy_alt' => $visible ? "Caramelo de tipo {$nombre}" : 'Caramelo de tipo',
            'cantidad' => $cantidad,
        ];
    }

    /**
     * @param  array<string, mixed>  $vista
     * @return array{candy_src: string|null, candy_imagen: string|null, candy_alt: string, candy_nombre: string|null, cantidad: int}
     */
    private static function carameloFamilia(array $vista): array
    {
        $pokemonId = $vista['pokemon_id'] ?? null;
        $nombre = $vista['nombre'] ?? 'Pokémon';
        $tienePokemon = is_int($pokemonId) && $pokemonId > 0;
        $candySrc = $tienePokemon ? "/images/candy_pokemon/{$pokemonId}.webp" : null;
        $candyImagen = $tienePokemon
            ? ($vista['icono_pokemon'] ?? $candySrc)
            : ($vista['candy_src'] ?? $candySrc);

        return [
            'candy_src' => $candySrc,
            'candy_imagen' => $candyImagen,
            'candy_alt' => "Caramelo de {$nombre}",
            'candy_nombre' => $vista['nombre'] ?? null,
            'cantidad' => (int) ($vista['cantidad'] ?? 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $vista
     * @return array{candy_src: string|null, candy_alt: string, candy_nombre: string|null, candy_nombre_js: string|null, cantidad: int}
     */
    private static function carameloEv(array $vista, int $stat, ?string $statNombre, ?string $statSlug): array
    {
        return [
            'candy_src' => $statSlug !== null && $statSlug !== '' ? "/images/candy_ev/{$statSlug}.webp" : null,
            'candy_alt' => 'Caramelo EV '.($statNombre ?? ''),
            'candy_nombre' => $statNombre,
            'candy_nombre_js' => $statNombre === null || $statNombre === '' ? 'statName('.$stat.')' : null,
            'cantidad' => (int) ($vista['cantidad'] ?? 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $vista
     * @param  array<array-key, string>  $nombres
     * @return array{titulo: string, resolucion_texto: string, duracion: int, icono_pokemon: string|null, rondas: int|null, rival_nombre: string|null, detalle_derrota: string|null}
     */
    private static function encuentro(array $vista, string $subtype, string $resolucion, int $duracion, array $nombres = []): array
    {
        $titulo = match ($subtype) {
            'grupo' => 'Grupo salvaje',
            'excepcional' => '¡Encuentro excepcional!',
            default => 'Encuentro',
        };

        $textoResolucion = match ($resolucion) {
            'victoria' => 'Victoria',
            'victoria_con_coste' => 'Victoria con coste',
            'derrota' => 'Derrota',
            'huida' => 'El salvaje huye',
            'retirada' => 'Retirada',
            default => '',
        };

        $rondas = self::rondas($vista['log'] ?? null);

        return [
            'titulo' => $titulo,
            'resolucion_texto' => $textoResolucion,
            'duracion' => $duracion,
            'icono_pokemon' => $vista['icono_pokemon'] ?? null,
            'rondas' => $rondas,
            'rival_nombre' => self::rivalNombre($vista, $nombres),
            'detalle_derrota' => self::detalleDerrota($vista, $rondas, $nombres),
        ];
    }

    /**
     * @param  array<string, mixed>  $evento
     * @return array{texto: string, reason: string, reason_texto: string}
     */
    private static function derrota(array $evento, string $sujeto): array
    {
        $reason = $evento['reason'] ?? null;
        $reason = is_string($reason) && trim($reason) !== '' ? $reason : null;

        $reasonTexto = match ($reason) {
            null => 'motivo desconocido',
            'explorador_debilitado' => 'se quedó sin PS',
            default => ucfirst(str_replace('_', ' ', $reason)),
        };

        return [
            'texto' => "{$sujeto} cae debilitado",
            'reason' => $reason ?? 'motivo desconocido',
            'reason_texto' => $reasonTexto,
        ];
    }

    private static function rondas(mixed $log): ?int
    {
        if (! is_array($log) || $log === []) {
            return null;
        }

        $total = 0;
        foreach ($log as $linea) {
            if (is_string($linea) && preg_match('/^--- Ronda \d+ ---/', trim($linea)) === 1) {
                $total++;
            }
        }

        return $total;
    }

    /**
     * Nombre del rival. En emboscadas multi-Pokémon, los PV pertenecen al
     * sub-combate decisivo (el último), así que el nombre se resuelve desde
     * ese `pokemon_id`; si no hay mapa se conserva el nombre actual.
     *
     * @param  array<string, mixed>  $vista
     * @param  array<array-key, string>  $nombres
     */
    private static function rivalNombre(array $vista, array $nombres = []): ?string
    {
        $subCombates = $vista['sub_combates'] ?? null;
        if (is_array($subCombates) && $subCombates !== []) {
            $ultimo = $subCombates[array_key_last($subCombates)];
            $pokemonId = is_array($ultimo) ? (int) ($ultimo['pokemon_id'] ?? 0) : 0;
            $nombreSubCombate = $pokemonId > 0 ? ($nombres[$pokemonId] ?? null) : null;

            if (is_string($nombreSubCombate) && trim($nombreSubCombate) !== '') {
                return $nombreSubCombate;
            }
        }

        $nombre = $vista['nombre'] ?? null;

        return is_string($nombre) && trim($nombre) !== '' ? $nombre : null;
    }

    /**
     * @param  array<string, mixed>  $vista
     * @param  array<array-key, string>  $nombres
     */
    private static function detalleDerrota(array $vista, ?int $rondas, array $nombres = []): ?string
    {
        if (($vista['resolucion'] ?? null) !== 'derrota' || ! array_key_exists('hp_inicial', $vista)) {
            return null;
        }

        $hpMax = (float) ($vista['hp_max'] ?? 0);
        $hpInicial = (float) ($vista['hp_inicial'] ?? 0);
        $sufijoRondas = $rondas !== null && $rondas > 0
            ? ' tras '.$rondas.($rondas === 1 ? ' ronda' : ' rondas')
            : '';

        $rivalNombre = self::rivalNombre($vista, $nombres);
        $tieneDatosRival = $rivalNombre !== null
            && array_key_exists('rival_hp_final', $vista)
            && array_key_exists('rival_hp_max', $vista);

        if ($tieneDatosRival) {
            $rivalFinal = (float) $vista['rival_hp_final'];
            $rivalMax = (float) $vista['rival_hp_max'];

            return 'Entró con '.self::numero($hpInicial).'/'.self::numero($hpMax)
                ." PS{$sufijoRondas}; a {$rivalNombre} le quedaban "
                .self::numero($rivalFinal).'/'.self::numero($rivalMax).' PS.';
        }

        return 'Entró con '.self::numero($hpInicial).'/'.self::numero($hpMax)
            .' PS y cayó'.$sufijoRondas.'.';
    }

    /**
     * @param  array<string, mixed>  $evento
     * @param  array<array-key, string>  $nombres
     */
    private static function nombre(array $evento, array $nombres): ?string
    {
        if (isset($evento['nombre']) && $evento['nombre'] !== '') {
            return (string) $evento['nombre'];
        }

        if (isset($evento['pokemon_id'])) {
            return $nombres[(int) $evento['pokemon_id']] ?? null;
        }

        $ids = self::ids($evento);

        return $ids !== [] ? ($nombres[$ids[0]] ?? null) : null;
    }

    /**
     * @param  array<string, mixed>  $evento
     * @return list<int>
     */
    private static function ids(array $evento): array
    {
        if (isset($evento['pokemon_ids']) && is_array($evento['pokemon_ids'])) {
            return array_values(array_filter(
                array_map('intval', $evento['pokemon_ids']),
                static fn (int $id): bool => $id > 0,
            ));
        }

        if (isset($evento['pokemon_id'])) {
            return [(int) $evento['pokemon_id']];
        }

        return [];
    }
}
