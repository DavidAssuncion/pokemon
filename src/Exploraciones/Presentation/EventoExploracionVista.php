<?php

declare(strict_types=1);

namespace Src\Exploraciones\Presentation;

/**
 * Presentador puro de un evento de bitácora de exploración.
 *
 * Centraliza los textos, iconos y rutas que antes se calculaban dentro de
 * `_evento.blade.php`. La vista sólo decide qué bloque HTML pintar según
 * `tipo`, pero no compone narrativa ni deriva nombres/rutas.
 */
final class EventoExploracionVista
{
    /** @var array<int, array{nombre: string, slug: string}> */
    private const STATS = [
        1 => ['nombre' => 'PS', 'slug' => 'hp'],
        2 => ['nombre' => 'Ataque', 'slug' => 'atk'],
        3 => ['nombre' => 'Defensa', 'slug' => 'def'],
        4 => ['nombre' => 'Ataque Especial', 'slug' => 'spAtk'],
        5 => ['nombre' => 'Defensa Especial', 'slug' => 'spDef'],
        6 => ['nombre' => 'Velocidad', 'slug' => 'speed'],
    ];

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

        // El contrato histórico sólo completa stat desde el catálogo para el
        // evento raíz `caramelo_ev`; los hallazgos conservan el fallback JS.
        if ($completarStats && $tipo === 'caramelo_ev') {
            $statInfo = self::STATS[$stat] ?? null;
            $statNombre ??= $statInfo['nombre'] ?? null;
            $statSlug ??= $statInfo['slug'] ?? null;
        }
        $duracion = (int) ($evento['duration_loss'] ?? $evento['duracion'] ?? 0);
        $subtype = (string) ($evento['subtype'] ?? '');
        $resolucion = (string) ($evento['resolucion'] ?? '');

        $vista = array_merge($evento, [
            '_vista' => true,
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
            'emboscada' => array_merge($vista, self::emboscada($vista, $resolucion, $duracion)),
            'contratiempo' => array_merge($vista, [
                'texto' => self::textoContratiempo($subtype, $duracion),
            ]),
            'retirada' => array_merge($vista, [
                'texto' => 'El equipo se retira: '.($evento['reason'] ?? 'motivo desconocido'),
                'reason' => $evento['reason'] ?? 'motivo desconocido',
            ]),
            'grupo' => array_merge($vista, [
                'texto' => 'El equipo se encuentra con un grupo salvaje',
            ]),
            'hallazgo' => array_merge($vista, self::hallazgo($vista, $subtype, $stat, $statNombre, $statSlug, $cantidad)),
            'caramelo_familia' => array_merge($vista, self::carameloFamilia($vista)),
            'caramelo_ev' => array_merge($vista, self::carameloEv($vista, $stat, $statNombre, $statSlug)),
            'encuentro' => array_merge($vista, self::encuentro($vista, $subtype, $resolucion, $duracion)),
            'neutral' => array_merge($vista, [
                'texto' => 'Evento neutral',
            ]),
            'combate' => array_merge($vista, [
                'resolucion' => $resolucion,
                'hp_final' => (int) ($evento['hp_final'] ?? 0),
                'combate_log' => (string) ($evento['combate_log'] ?? ''),
            ]),
            'descanso' => array_merge($vista, [
                'duracion' => $duracion,
            ]),
            default => array_merge($vista, [
                'texto' => 'Evento: '.$tipo.($subtype !== '' ? " ({$subtype})" : ''),
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $vista
     * @return array<string, mixed>
     */
    private static function emboscada(array $vista, string $resolucion, int $duracion): array
    {
        $subtitulo = 'El equipo repele el ataque'.($duracion > 0 ? " (-{$duracion} min)" : '');

        if (
            str_contains($resolucion, 'huida')
            || str_contains($resolucion, 'escapa')
            || str_contains($resolucion, 'sin_combate')
        ) {
            $subtitulo = 'El equipo escapa perdiendo tiempo';
        } elseif (
            $resolucion !== ''
            && ! str_contains($resolucion, 'combate')
            && ! str_contains($resolucion, 'repele')
            && ! str_contains($resolucion, 'victoria')
        ) {
            $subtitulo = $resolucion;
        }

        return [
            'titulo' => '¡Emboscada!',
            'subtitulo' => $subtitulo,
        ];
    }

    private static function textoContratiempo(string $subtype, int $duracion): string
    {
        $sufijo = $duracion > 0 ? " -{$duracion} min" : '';

        return match ($subtype) {
            'desorientacion' => "El equipo pierde el rastro.{$sufijo}",
            'terreno' => "El terreno dificulta el avance.{$sufijo}",
            'clima' => "El clima empeora las condiciones.{$sufijo}",
            'bloqueo' => "Un obstáculo bloquea el camino.{$sufijo}",
            default => $subtype !== '' ? $subtype : 'El equipo sufre un contratiempo',
        };
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
            $slug = (string) ($vista['slug'] ?? '');
            $nombreTipo = (string) ($vista['tipo_nombre'] ?? $vista['tipo_label'] ?? ($slug !== '' ? ucfirst($slug) : 'Desconocido'));

            return [
                'candy_slug' => $slug,
                'candy_nombre' => $nombreTipo,
                'candy_src' => $slug !== '' ? "/images/candy_type/{$slug}.webp" : null,
                'candy_alt' => "Caramelo de tipo {$nombreTipo}",
                'cantidad' => $cantidad,
            ];
        }

        return ['texto' => 'Hallazgo registrado'];
    }

    /**
     * @param  array<string, mixed>  $vista
     * @return array<string, mixed>
     */
    private static function carameloFamilia(array $vista): array
    {
        $pokemonId = $vista['pokemon_id'] ?? null;
        $nombre = $vista['nombre'] ?? 'Pokémon';

        return [
            'candy_src' => $pokemonId !== null && $pokemonId !== 0 ? "/images/candy_pokemon/{$pokemonId}.webp" : null,
            'candy_alt' => "Caramelo de {$nombre}",
            'candy_nombre' => $vista['nombre'] ?? null,
            'cantidad' => (int) ($vista['cantidad'] ?? 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $vista
     * @return array<string, mixed>
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
     * @return array<string, mixed>
     */
    private static function encuentro(array $vista, string $subtype, string $resolucion, int $duracion): array
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

        return [
            'titulo' => $titulo,
            'resolucion_texto' => $textoResolucion,
            'duracion' => $duracion,
            'icono_pokemon' => $vista['icono_pokemon'] ?? null,
        ];
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
