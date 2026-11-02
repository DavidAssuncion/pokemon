<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Move;
use App\Models\Pokemon;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Genera el artefacto build-time de movesets de Pokémon Showdown.
 *
 * Fuentes (vendorizadas en showdown/, licencia MIT):
 * - data/random-battles/gen9/sets.json
 * - data/random-battles/gen9/bss-factory-sets.json
 * - data/random-battles/gen9/factory-sets.json
 * - data/moves.ts
 *
 * El artefacto solo contiene movimientos que existen en el catálogo del juego.
 * La cobertura se calcula sobre sets.json, la fuente principal de random
 * battles; el resto de fuentes enriquece los movepools de las especies.
 */
final class BuildShowdownMovesets extends Command
{
    protected $signature = 'showdown:build-movesets
        {--out=resources/data/showdown_movesets.json : Ruta del artefacto generado}
        {--check : Recalcula y compara el artefacto sin escribirlo}';

    protected $description = 'Genera el artefacto de movesets de Showdown usado por el selector de movimientos rival.';

    private const RUTA_SETS = 'showdown/data/random-battles/gen9/sets.json';

    private const RUTA_BSS = 'showdown/data/random-battles/gen9/bss-factory-sets.json';

    private const RUTA_FACTORY = 'showdown/data/random-battles/gen9/factory-sets.json';

    private const RUTA_MOVES = 'showdown/data/moves.ts';

    private const VERSION = 1;

    public function handle(): int
    {
        try {
            $artefacto = $this->generar();
        } catch (Throwable $e) {
            $this->error('No se pudo generar el artefacto: '.$e->getMessage());

            return self::FAILURE;
        }

        $ruta = $this->rutaSalida();
        $cobertura = $artefacto['coverage'];

        if ((bool) $this->option('check')) {
            return $this->comprobar($ruta, $artefacto, $cobertura);
        }

        try {
            $this->escribirAtomico($ruta, $this->codificar($artefacto));
        } catch (Throwable $e) {
            $this->error('No se pudo escribir el artefacto: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Artefacto generado en '.$ruta);
        $this->line(sprintf(
            'Cobertura: especies %d/%d, movimientos %d/%d',
            $cobertura['species_mapped'],
            $cobertura['species_total'],
            $cobertura['moves_mapped'],
            $cobertura['moves_total'],
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     version: int,
     *     generated_at: string,
     *     source: array{repo: string, license: string},
     *     coverage: array{species_total: int, species_mapped: int, moves_total: int, moves_mapped: int},
     *     moves: array<string, array{num: ?int, showdown_name: string, game_name: string}>,
     *     species: array<string, array{species_id: int, showdown_name: string, roles: list<string>, movepool: list<string>, moves_unmapped: list<string>}>
     * }
     */
    private function generar(): array
    {
        $sets = $this->leerJson(self::RUTA_SETS);
        $bss = $this->leerJson(self::RUTA_BSS);
        $factory = $this->leerJson(self::RUTA_FACTORY);
        $movesTs = $this->leerMovesTs(self::RUTA_MOVES);

        [$movimientosPorNumero, $movimientosPorSlug, $pokemonJuego] = $this->cargarCatalogoJuego();

        $brutos = [];

        foreach ($sets as $clave => $info) {
            $nombre = $this->nombreVisible((string) $clave);
            $roles = [];
            $nombres = [];

            foreach ($info['sets'] ?? [] as $set) {
                if (isset($set['role']) && is_string($set['role'])) {
                    $roles[] = $set['role'];
                }

                foreach ($set['movepool'] ?? [] as $movimiento) {
                    if (is_string($movimiento) && $movimiento !== '') {
                        $nombres[] = $movimiento;
                    }
                }
            }

            $this->agregarEspecie($brutos, (string) $clave, $nombre, $roles, $nombres);
        }

        foreach ($bss as $clave => $info) {
            $this->agregarEspecieDesdeSetsCerrados($brutos, (string) $clave, $info);
        }

        foreach ($factory as $tier) {
            if (! is_array($tier)) {
                continue;
            }

            foreach ($tier as $clave => $info) {
                $this->agregarEspecieDesdeSetsCerrados($brutos, (string) $clave, $info);
            }
        }

        $resueltos = [];
        $movimientos = [];
        $especies = [];

        foreach ($brutos as $clave => $especie) {
            $pokemon = $this->buscarPokemon($pokemonJuego, $clave);
            $movepool = [];
            $sinMapear = [];

            foreach (array_keys($especie['nombres']) as $nombreMovimiento) {
                $resuelto = $this->resolverMovimiento(
                    $nombreMovimiento,
                    $movesTs,
                    $movimientosPorNumero,
                    $resueltos,
                    $movimientosPorSlug,
                );

                if ($resuelto === null) {
                    $sinMapear[$nombreMovimiento] = true;

                    continue;
                }

                $movepool[$resuelto['slug']] = true;
                $movimientos[$resuelto['slug']] = [
                    'num' => $resuelto['num'],
                    'showdown_name' => $resuelto['showdown_name'],
                    'game_name' => $resuelto['game_name'],
                ];
            }

            if ($pokemon === null) {
                continue;
            }

            $especies[$clave] = [
                'species_id' => $pokemon['species_id'],
                'showdown_name' => $especie['showdown_name'],
                'roles' => $this->clavesOrdenadas($especie['roles']),
                'movepool' => $this->clavesOrdenadas($movepool),
                'moves_unmapped' => $this->clavesOrdenadas($sinMapear),
            ];
        }

        ksort($movimientos, SORT_STRING);
        ksort($especies, SORT_STRING);

        $cobertura = $this->calcularCobertura(
            $sets,
            $movesTs,
            $movimientosPorNumero,
            $movimientosPorSlug,
            $pokemonJuego,
        );

        return [
            'version' => self::VERSION,
            'generated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->format(\DateTimeInterface::ATOM),
            'source' => [
                'repo' => 'smogon/pokemon-showdown',
                'license' => 'MIT',
            ],
            'coverage' => $cobertura,
            'moves' => $movimientos,
            'species' => $especies,
        ];
    }

    /**
     * @param  array<string, array{showdown_name: string, roles: array<string, bool>, nombres: array<string, bool>}>  $brutos
     * @param  list<string>  $roles
     * @param  list<string>  $nombres
     */
    private function agregarEspecie(
        array &$brutos,
        string $clave,
        string $nombreVisible,
        array $roles,
        array $nombres,
    ): void {
        $normalizada = $this->normalizar($clave);

        if ($normalizada === '') {
            return;
        }

        $brutos[$normalizada] ??= [
            'showdown_name' => $nombreVisible,
            'roles' => [],
            'nombres' => [],
        ];

        if ($nombreVisible !== '') {
            $brutos[$normalizada]['showdown_name'] = $nombreVisible;
        }

        foreach ($roles as $rol) {
            if ($rol !== '') {
                $brutos[$normalizada]['roles'][$rol] = true;
            }
        }

        foreach ($nombres as $nombre) {
            if ($nombre !== '') {
                $brutos[$normalizada]['nombres'][$nombre] = true;
            }
        }
    }

    /**
     * @param  array<string, array{showdown_name: string, roles: array<string, bool>, nombres: array<string, bool>}>  $brutos
     * @param  array<string, mixed>  $info
     */
    private function agregarEspecieDesdeSetsCerrados(array &$brutos, string $clave, array $info): void
    {
        $nombreVisible = '';
        $nombres = [];

        foreach ($info['sets'] ?? [] as $set) {
            if ($nombreVisible === '' && isset($set['species']) && is_string($set['species'])) {
                $nombreVisible = $set['species'];
            }

            foreach ($set['moves'] ?? [] as $grupo) {
                if (! is_array($grupo)) {
                    continue;
                }

                foreach ($grupo as $movimiento) {
                    if (is_string($movimiento) && $movimiento !== '') {
                        $nombres[] = $movimiento;
                    }
                }
            }
        }

        $this->agregarEspecie(
            $brutos,
            $clave,
            $nombreVisible !== '' ? $nombreVisible : $this->nombreVisible($clave),
            [],
            $nombres,
        );
    }

    /**
     * @param  array<string, array{name: string, num: ?int}>  $movesTs
     * @param  array<int, array{slug: string, num: ?int, name: string}>  $porNumero
     * @param  array<string, array{slug: string, num: ?int, name: string}>  $porSlug
     * @param  array<string, array{slug: string, num: ?int, showdown_name: string, game_name: string}|null>  $cache
     * @return array{slug: string, num: ?int, showdown_name: string, game_name: string}|null
     */
    private function resolverMovimiento(
        string $nombre,
        array $movesTs,
        array $porNumero,
        array &$cache,
        array $porSlug,
    ): ?array {
        $normalizado = $this->normalizar($nombre);

        if (array_key_exists($normalizado, $cache)) {
            return $cache[$normalizado];
        }

        $entrada = $movesTs[$normalizado] ?? null;
        $juego = null;

        if ($entrada !== null && $entrada['num'] !== null) {
            $juego = $porNumero[$entrada['num']] ?? null;
        }

        if ($juego === null) {
            $juego = $porSlug[$normalizado] ?? null;
        }

        if ($juego === null) {
            $cache[$normalizado] = null;

            return null;
        }

        $cache[$normalizado] = [
            'slug' => $juego['slug'],
            'num' => $juego['num'] ?? $entrada['num'] ?? null,
            'showdown_name' => $entrada['name'] ?? $nombre,
            'game_name' => $juego['name'],
        ];

        return $cache[$normalizado];
    }

    /**
     * @param  array<string, mixed>  $sets
     * @param  array<string, array{name: string, num: ?int}>  $movesTs
     * @param  array<int, array{slug: string, num: ?int, name: string}>  $porNumero
     * @param  array<string, array{slug: string, num: ?int, name: string}>  $porSlug
     * @param  array<string, array{species_id: int}>  $pokemonJuego
     * @return array{species_total: int, species_mapped: int, moves_total: int, moves_mapped: int}
     */
    private function calcularCobertura(
        array $sets,
        array $movesTs,
        array $porNumero,
        array $porSlug,
        array $pokemonJuego,
    ): array {
        $especiesMapeadas = 0;
        foreach (array_keys($sets) as $clave) {
            if ($this->buscarPokemon($pokemonJuego, (string) $clave) !== null) {
                $especiesMapeadas++;
            }
        }

        $nombresMovimientos = [];
        foreach ($sets as $info) {
            foreach ($info['sets'] ?? [] as $set) {
                foreach ($set['movepool'] ?? [] as $movimiento) {
                    if (is_string($movimiento) && $movimiento !== '') {
                        $nombresMovimientos[$movimiento] = true;
                    }
                }
            }
        }

        $movimientosMapeados = 0;
        $cache = [];
        foreach (array_keys($nombresMovimientos) as $nombre) {
            if ($this->resolverMovimiento($nombre, $movesTs, $porNumero, $cache, $porSlug) !== null) {
                $movimientosMapeados++;
            }
        }

        return [
            'species_total' => count($sets),
            'species_mapped' => $especiesMapeadas,
            'moves_total' => count($nombresMovimientos),
            'moves_mapped' => $movimientosMapeados,
        ];
    }

    /**
     * @return array<string, array{name: string, num: ?int}>
     */
    private function leerMovesTs(string $ruta): array
    {
        $archivo = $this->rutaProyecto($ruta);
        $lineas = file($archivo, FILE_IGNORE_NEW_LINES);
        if ($lineas === false) {
            throw new RuntimeException('No se pudo leer '.$archivo);
        }

        $entradas = [];
        $enBloque = false;
        $nombre = null;
        $num = null;

        foreach ($lineas as $linea) {
            $linea = rtrim($linea, "\r\n");

            if (preg_match('/^\t(?:"([^"]+)"|([A-Za-z0-9]+)): \{$/', $linea) === 1) {
                $enBloque = true;
                $nombre = null;
                $num = null;

                continue;
            }

            if (! $enBloque) {
                continue;
            }

            if (preg_match('/^\t\tname: "((?:[^"\\\\]|\\\\.)*)",$/', $linea, $coincidencia) === 1) {
                $nombre = stripcslashes($coincidencia[1]);

                continue;
            }

            if (preg_match('/^\t\tnum: (-?\d+),$/', $linea, $coincidencia) === 1) {
                $num = (int) $coincidencia[1];

                continue;
            }

            if ($linea === "\t},") {
                if ($nombre !== null) {
                    $entradas[$this->normalizar($nombre)] = [
                        'name' => $nombre,
                        'num' => $num !== null && $num > 0 ? $num : null,
                    ];
                }

                $enBloque = false;
            }
        }

        return $entradas;
    }

    /**
     * @return array{
     *     0: array<int, array{slug: string, num: ?int, name: string}>,
     *     1: array<string, array{slug: string, num: ?int, name: string}>,
     *     2: array<string, array{species_id: int}>
     * }
     */
    private function cargarCatalogoJuego(): array
    {
        return [
            $this->cargarMovimientosJuegoPorNumero(),
            $this->cargarMovimientosJuegoPorSlug(),
            $this->cargarPokemonJuego(),
        ];
    }

    /**
     * @return array<int, array{slug: string, num: ?int, name: string}>
     */
    private function cargarMovimientosJuegoPorNumero(): array
    {
        $porNumero = [];

        foreach (Move::query()->get(['num', 'slug', 'name']) as $move) {
            if ($move->num === null) {
                continue;
            }

            $porNumero[(int) $move->num] = [
                'slug' => (string) $move->slug,
                'num' => (int) $move->num,
                'name' => (string) $move->name,
            ];
        }

        return $porNumero;
    }

    /**
     * @return array<string, array{slug: string, num: ?int, name: string}>
     */
    private function cargarMovimientosJuegoPorSlug(): array
    {
        $porSlug = [];

        foreach (Move::query()->get(['num', 'slug', 'name']) as $move) {
            $clave = $this->normalizar((string) $move->slug);
            if ($clave === '') {
                continue;
            }

            $porSlug[$clave] = [
                'slug' => (string) $move->slug,
                'num' => $move->num !== null ? (int) $move->num : null,
                'name' => (string) $move->name,
            ];
        }

        return $porSlug;
    }

    /**
     * @return array<string, array{species_id: int}>
     */
    private function cargarPokemonJuego(): array
    {
        $porNombre = [];

        foreach (Pokemon::query()->get(['id', 'name', 'species_id']) as $pokemon) {
            $clave = $this->normalizar((string) $pokemon->name);
            if ($clave === '') {
                continue;
            }

            $porNombre[$clave] = [
                'species_id' => (int) $pokemon->species_id,
            ];
        }

        $ruta = $this->rutaProyecto('storage/data/pokemon.csv');
        if (! is_file($ruta)) {
            return $porNombre;
        }

        $archivo = fopen($ruta, 'rb');
        if ($archivo === false) {
            return $porNombre;
        }

        $cabecera = fgetcsv($archivo);
        if ($cabecera === false) {
            fclose($archivo);

            return $porNombre;
        }

        $indices = array_flip($cabecera);

        while (($fila = fgetcsv($archivo)) !== false) {
            $nombre = $fila[$indices['name'] ?? -1] ?? null;
            $speciesId = $fila[$indices['species_id'] ?? -1] ?? null;

            if (! is_string($nombre) || $nombre === '' || ! is_numeric($speciesId)) {
                continue;
            }

            $clave = $this->normalizar($nombre);
            if ($clave === '' || isset($porNombre[$clave])) {
                continue;
            }

            $porNombre[$clave] = [
                'species_id' => (int) $speciesId,
            ];
        }

        fclose($archivo);

        return $porNombre;
    }

    /**
     * @param  array<string, array{species_id: int}>  $pokemonJuego
     * @return array{species_id: int}|null
     */
    private function buscarPokemon(array $pokemonJuego, string $clave): ?array
    {
        return $pokemonJuego[$this->normalizar($clave)] ?? null;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function codificar(array $datos): string
    {
        try {
            $json = json_encode(
                $datos,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $e) {
            throw new RuntimeException('No se pudo codificar el artefacto: '.$e->getMessage(), 0, $e);
        }

        return $json."\n";
    }

    /**
     * @param  array<string, mixed>  $artefacto
     * @param  array{species_total: int, species_mapped: int, moves_total: int, moves_mapped: int}  $cobertura
     */
    private function comprobar(string $ruta, array $artefacto, array $cobertura): int
    {
        if (! is_file($ruta)) {
            $this->error('No existe el artefacto: '.$ruta);

            return self::FAILURE;
        }

        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            $this->error('No se pudo leer el artefacto: '.$ruta);

            return self::FAILURE;
        }

        $existente = json_decode($contenido, true);
        if (! is_array($existente)) {
            $this->error('El artefacto existente no es JSON válido: '.$ruta);

            return self::FAILURE;
        }

        unset($artefacto['generated_at'], $existente['generated_at']);

        $hashNuevo = hash('sha256', $this->codificar($artefacto));
        $hashExistente = hash('sha256', $this->codificar($existente));

        if (! hash_equals($hashExistente, $hashNuevo)) {
            $this->error(sprintf(
                'El artefacto está desactualizado (sha256 %s != %s).',
                $hashExistente,
                $hashNuevo,
            ));
            $this->line(sprintf(
                'Cobertura calculada: especies %d/%d, movimientos %d/%d',
                $cobertura['species_mapped'],
                $cobertura['species_total'],
                $cobertura['moves_mapped'],
                $cobertura['moves_total'],
            ));

            return self::FAILURE;
        }

        $this->info('El artefacto está actualizado: '.$ruta);
        $this->line(sprintf(
            'Cobertura: especies %d/%d, movimientos %d/%d',
            $cobertura['species_mapped'],
            $cobertura['species_total'],
            $cobertura['moves_mapped'],
            $cobertura['moves_total'],
        ));

        return self::SUCCESS;
    }

    private function escribirAtomico(string $ruta, string $contenido): void
    {
        $directorio = dirname($ruta);
        if (! is_dir($directorio) && ! mkdir($directorio, 0o755, true) && ! is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear el directorio '.$directorio);
        }

        $temporal = $directorio.'/.'.basename($ruta).'.'.bin2hex(random_bytes(8)).'.tmp';

        if (file_put_contents($temporal, $contenido, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir el archivo temporal '.$temporal);
        }

        if (! @rename($temporal, $ruta)) {
            @unlink($temporal);

            throw new RuntimeException('No se pudo mover el archivo temporal a '.$ruta);
        }
    }

    private function rutaSalida(): string
    {
        $ruta = (string) $this->option('out');

        if ($ruta === '') {
            $ruta = 'resources/data/showdown_movesets.json';
        }

        return str_starts_with($ruta, '/') ? $ruta : $this->rutaProyecto($ruta);
    }

    private function rutaProyecto(string $ruta): string
    {
        return base_path($ruta);
    }

    /**
     * @return array<string, mixed>
     */
    private function leerJson(string $ruta): array
    {
        $archivo = $this->rutaProyecto($ruta);
        $contenido = file_get_contents($archivo);

        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer '.$archivo);
        }

        $datos = json_decode($contenido, true);

        if (! is_array($datos)) {
            throw new RuntimeException('JSON inválido en '.$archivo);
        }

        return $datos;
    }

    private function nombreVisible(string $clave): string
    {
        $nombre = ucwords(str_replace(['-', '_'], ' ', $clave));

        return $nombre === '' ? $clave : $nombre;
    }

    /**
     * @param  array<string, true>  $claves
     * @return list<string>
     */
    private function clavesOrdenadas(array $claves): array
    {
        $lista = array_keys($claves);
        sort($lista, SORT_STRING);

        return array_values($lista);
    }

    private function normalizar(string $valor): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($valor))) ?? '';
    }
}
