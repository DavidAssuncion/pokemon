<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Move;
use App\Models\Pokemon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Battle\Domain\Catalog\NormalizadorNombre;

/**
 * Asigna a cada especie los movimientos importados de PokeAPI.
 *
 * El fichero `storage/data/pokemon_moves_pokeapi.json` se genera desde
 * `pokeapi/data/v2/csv/pokemon_moves.csv` y contiene, por Pokémon:
 *
 *   { "slug": ["level-up"|"machine"|"tutor"|"egg"|"event", nivel|null] }
 *
 * Para los movimientos level-up se usa la línea de preevoluciones completa y
 * se conserva el nivel mínimo de la familia. El resto de métodos (MT, tutor,
 * huevo, evento) se mantienen para el catálogo del jugador, pero los rivales
 * salvajes y entrenadores solo usan `level-up`.
 *
 * Las asignaciones manuales de `ASIGNACIONES_EXTRA` y del CSV
 * `poke habilidades.csv` se conservan como `custom`.
 */
class PokemonMoveSeeder extends Seeder
{
    /**
     * Movimientos del Bloque 5 para especies concretas. Se añaden después del
     * JSON para que el catálogo de interacción con objetos/habilidades sea
     * utilizable sin editar el fichero de datos original.
     *
     * @var array<int, list<string>>
     */
    private const ASIGNACIONES_EXTRA = [
        23 => ['jugo_gastrico'],
        24 => ['jugo_gastrico', 'embargo'],
        52 => ['ladron', 'desarme', 'reciclaje'],
        53 => ['ladron', 'desarme', 'truco'],
        64 => ['truco', 'intercambio', 'imitacion'],
        65 => ['truco', 'zona_magica', 'intercambio', 'imitacion'],
        69 => ['jugo_gastrico'],
        70 => ['jugo_gastrico'],
        71 => ['jugo_gastrico'],
        76 => ['explosion'],
        94 => ['sustituto', 'embargo'],
        101 => ['autodestruccion'],
        110 => ['explosion', 'autodestruccion'],
        120 => ['camuflaje'],
        121 => ['camuflaje', 'zona_magica'],
        122 => ['sustituto', 'zona_magica', 'imitacion'],
        137 => ['camuflaje', 'reciclaje'],
        197 => ['embargo'],
        198 => ['ladron', 'desarme'],
        205 => ['explosion'],
        215 => ['desarme', 'ladron'],
        344 => ['explosion'],
        376 => ['explosion'],
        426 => ['explosion'],
        435 => ['explosion'],
        437 => ['explosion'],
        462 => ['explosion'],
        476 => ['explosion'],
        623 => ['explosion'],
    ];

    /**
     * Movimientos de nivel añadidos a mano porque el learnset de PokeAPI no
     * los trae para esa especie. Abra aprende Confusión desde nivel 1.
     *
     * @var array<int, array<string, int>>
     */
    private const ASIGNACIONES_NIVEL_EXTRA = [
        63 => ['confusion' => 1],
    ];

    /** @var array<string, int> */
    private const PRIORIDAD_FUENTE = [
        'level-up' => 0,
        'machine' => 1,
        'tutor' => 2,
        'egg' => 3,
        'event' => 4,
        'custom' => 5,
    ];

    public function run(): void
    {
        $ruta = storage_path('data/pokemon_moves_pokeapi.json');
        if (! is_file($ruta)) {
            throw new RuntimeException(
                'Falta storage/data/pokemon_moves_pokeapi.json. '
                .'Genera el fichero desde PokeAPI antes de ejecutar el seeder.',
            );
        }

        /** @var array<int, array<string, array{source: string, level: int|null}>> $asignaciones */
        $asignaciones = json_decode(
            (string) file_get_contents($ruta),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $movimientos = Move::query()->get(['id', 'slug', 'name'])->keyBy('slug');
        $pokemons = Pokemon::query()->pluck('id')->all();
        $pokemonsExistentes = array_fill_keys(array_map('intval', $pokemons), true);

        $asignaciones = $this->normalizarAsignaciones($asignaciones, $movimientos, $pokemonsExistentes);
        $this->mergeNivelExtra($asignaciones, $movimientos, $pokemonsExistentes);
        $this->mergeAsignacionesManuales($asignaciones, $movimientos, $pokemonsExistentes);

        DB::transaction(function () use ($asignaciones, $movimientos): void {
            DB::table('pokemon_moves')->delete();

            $filas = [];
            foreach ($asignaciones as $pokemonId => $movimientosPokemon) {
                $ordenados = $this->ordenarMovimientos($movimientosPokemon, $movimientos);

                foreach ($ordenados as $slot => $slug) {
                    $datos = $movimientosPokemon[$slug];
                    $movimiento = $movimientos->get($slug);
                    if ($movimiento === null) {
                        continue;
                    }

                    $filas[] = [
                        'pokemon_id' => $pokemonId,
                        'move_id' => $movimiento->id,
                        'slot' => $slot + 1,
                        'source' => $datos['source'],
                        'level' => $datos['level'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            foreach (array_chunk($filas, 1000) as $lote) {
                DB::table('pokemon_moves')->insert($lote);
            }
        });

        $total = array_sum(array_map('count', $asignaciones));
        $this->command?->info(
            count($asignaciones).' Pokémon con movimientos asignados; '.$total.' filas.',
        );
    }

    /**
     * Descarta IDs de Pokémon/movimientos que ya no existen y normaliza los
     * niveles. El JSON se genera contra el catálogo actual, pero el seeder no
     * debe romper si se elimina una especie o un movimiento.
     *
     * @param  array<int, array<string, array{source: string, level: int|null}>>  $asignaciones
     * @param  \Illuminate\Support\Collection<string, Move>  $movimientos
     * @param  array<int, true>  $pokemonsExistentes
     * @return array<int, array<string, array{source: string, level: int|null}>>
     */
    private function normalizarAsignaciones(
        array $asignaciones,
        $movimientos,
        array $pokemonsExistentes,
    ): array {
        $normalizadas = [];

        foreach ($asignaciones as $pokemonId => $movimientosPokemon) {
            $pokemonId = (int) $pokemonId;
            if (! isset($pokemonsExistentes[$pokemonId]) || ! is_array($movimientosPokemon)) {
                continue;
            }

            foreach ($movimientosPokemon as $slug => $datos) {
                $slug = (string) $slug;
                if (! $movimientos->has($slug) || ! is_array($datos)) {
                    continue;
                }

                $source = (string) ($datos[0] ?? 'custom');
                $level = $datos[1] ?? null;
                if (! array_key_exists($source, self::PRIORIDAD_FUENTE)) {
                    $source = 'custom';
                }

                $normalizadas[$pokemonId][$slug] = [
                    'source' => $source,
                    'level' => $source === 'level-up' && $level !== null
                        ? max(1, (int) $level)
                        : null,
                ];
            }
        }

        return $normalizadas;
    }

    /**
     * @param  array<int, array<string, array{source: string, level: int|null}>>  $asignaciones
     * @param  \Illuminate\Support\Collection<string, Move>  $movimientos
     * @param  array<int, true>  $pokemonsExistentes
     */
    private function mergeNivelExtra(
        array &$asignaciones,
        $movimientos,
        array $pokemonsExistentes,
    ): void {
        $mapa = $this->mapaMovimientos($movimientos);

        foreach (self::ASIGNACIONES_NIVEL_EXTRA as $pokemonId => $movimientosNivel) {
            if (! isset($pokemonsExistentes[$pokemonId])) {
                continue;
            }

            foreach ($movimientosNivel as $slug => $level) {
                $slugResuelto = $this->resolverMoveSlug($slug, $mapa);
                if ($slugResuelto === null) {
                    continue;
                }

                $asignaciones[$pokemonId][$slugResuelto] = [
                    'source' => 'level-up',
                    'level' => max(1, (int) $level),
                ];
            }
        }
    }

    /**
     * Conserva las asignaciones manuales que no vengan ya de PokeAPI.
     *
     * @param  array<int, array<string, array{source: string, level: int|null}>>  $asignaciones
     * @param  \Illuminate\Support\Collection<string, Move>  $movimientos
     * @param  array<int, true>  $pokemonsExistentes
     */
    private function mergeAsignacionesManuales(
        array &$asignaciones,
        $movimientos,
        array $pokemonsExistentes,
    ): void {
        $mapa = $this->mapaMovimientos($movimientos);

        foreach ($this->asignacionesExtra() as $pokemonId => $slugs) {
            if (! isset($pokemonsExistentes[$pokemonId])) {
                continue;
            }

            foreach ($slugs as $slug) {
                $slugResuelto = $this->resolverMoveSlug($slug, $mapa);
                if ($slugResuelto === null) {
                    continue;
                }

                $asignaciones[$pokemonId][$slugResuelto] ??= [
                    'source' => 'custom',
                    'level' => null,
                ];
            }
        }

        $rutaCsv = storage_path('data/poke habilidades.csv');
        if (! is_file($rutaCsv)) {
            return;
        }

        foreach ($this->leerCsv($rutaCsv) as $fila) {
            $pokemonId = (int) ($fila['id'] ?? 0);
            if (! isset($pokemonsExistentes[$pokemonId])) {
                continue;
            }

            foreach ($this->nombresDeMovimientos((string) ($fila['movimientos'] ?? '')) as $nombre) {
                $slugResuelto = $this->resolverMoveSlug($nombre, $mapa);
                if ($slugResuelto === null) {
                    continue;
                }

                $asignaciones[$pokemonId][$slugResuelto] ??= [
                    'source' => 'custom',
                    'level' => null,
                ];
            }
        }
    }

    /**
     * Ordena los movimientos de un Pokémon: primero level-up por nivel, luego
     * MT/tutor/huevo/evento/custom y, dentro de cada grupo, por nombre.
     *
     * @param  array<string, array{source: string, level: int|null}>  $movimientosPokemon
     * @param  \Illuminate\Support\Collection<string, Move>  $movimientos
     * @return list<string>
     */
    private function ordenarMovimientos(array $movimientosPokemon, $movimientos): array
    {
        $slugs = array_keys($movimientosPokemon);
        usort($slugs, function (string $a, string $b) use ($movimientosPokemon, $movimientos): int {
            $da = $movimientosPokemon[$a];
            $db = $movimientosPokemon[$b];

            $prioridadA = self::PRIORIDAD_FUENTE[$da['source']] ?? 99;
            $prioridadB = self::PRIORIDAD_FUENTE[$db['source']] ?? 99;

            return [$prioridadA, $da['level'] ?? PHP_INT_MAX, $movimientos->get($a)?->name ?? '', $a]
                <=> [$prioridadB, $db['level'] ?? PHP_INT_MAX, $movimientos->get($b)?->name ?? '', $b];
        });

        return $slugs;
    }

    /**
     * Combina las asignaciones manuales con el lote de PokeAPI.
     *
     * @return array<int, list<string>>
     */
    private function asignacionesExtra(): array
    {
        return self::ASIGNACIONES_EXTRA;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Move>  $movimientos
     * @return array<string, string> nombre normalizado => slug
     */
    private function mapaMovimientos($movimientos): array
    {
        $mapa = [];
        foreach ($movimientos as $move) {
            $mapa[NormalizadorNombre::normalizar($move->name)] = (string) $move->slug;
            $mapa[NormalizadorNombre::normalizar(str_replace('_', ' ', (string) $move->slug))] = (string) $move->slug;
        }

        return $mapa;
    }

    /**
     * @param  array<string, string>  $mapa
     */
    private function resolverMoveSlug(string $nombre, array $mapa): ?string
    {
        $clave = NormalizadorNombre::normalizar($nombre);
        $clave = $this->alias($clave);

        return $mapa[$clave] ?? null;
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function leerCsv(string $ruta): array
    {
        $gestor = fopen($ruta, 'rb');
        if ($gestor === false) {
            return [];
        }

        $cabecera = fgetcsv($gestor);
        if ($cabecera === false) {
            fclose($gestor);

            return [];
        }

        $cabecera[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cabecera[0]);
        $filas = [];

        while (($valores = fgetcsv($gestor)) !== false) {
            $fila = [];
            foreach ($cabecera as $indice => $columna) {
                $fila[(string) $columna] = $valores[$indice] ?? null;
            }
            $filas[] = $fila;
        }

        fclose($gestor);

        return $filas;
    }

    /**
     * @return list<string>
     */
    private function nombresDeMovimientos(string $valor): array
    {
        $nombres = [];
        foreach (explode(',', $valor) as $nombre) {
            $nombre = trim($nombre);
            if ($nombre !== '') {
                $nombres[] = $nombre;
            }
        }

        return array_values(array_unique($nombres));
    }

    private function alias(string $clave): string
    {
        return match ($clave) {
            'patada salto alto' => 'patada salto alta',
            default => $clave,
        };
    }
}
