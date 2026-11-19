<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ability;
use Illuminate\Database\Seeder;
use RuntimeException;
use Src\Battle\Domain\Catalog\CatalogoHabilidades;
use Src\Battle\Domain\Catalog\NormalizadorNombre;

/**
 * Carga el catálogo de habilidades en una sola pasada.
 *
 * Fusiona antes de escribir: textos del CSV, placeholders de las habilidades
 * asignadas, claves de los lotes JSON y el mapa de descripciones por
 * `effect_key` (`storage/data/abilities_descripciones.json`). Así los lotes
 * solo aportan `effect_keys` y nunca se escribe un `effect` vacío sobre un
 * texto existente. La segunda ejecución no modifica datos.
 */
class AbilitySeeder extends Seeder
{
    /** @var list<string> */
    private const LOTES = [
        'abilities_lote1.json',
        'abilities_lote2.json',
        'abilities_lote3.json',
    ];

    private const DESCRIPCIONES = 'abilities_descripciones.json';

    public function run(): void
    {
        $textos = $this->descripciones();
        $actuales = $this->efectosActuales();
        $fusion = $this->fusionar($textos, $actuales);

        $actualizadas = 0;
        $conTexto = 0;
        $clavesSinTexto = [];

        foreach ($fusion as $datos) {
            $anterior = $actuales[$this->normalizar($datos['name'])] ?? null;
            if (
                $anterior === null
                || $anterior['effect'] !== $datos['effect']
                || $anterior['effect_keys'] !== $datos['effect_keys']
            ) {
                $actualizadas++;
            }

            Ability::updateOrCreate(
                ['name' => $datos['name']],
                [
                    'effect' => $datos['effect'],
                    'data' => ['effect_keys' => $datos['effect_keys']],
                ],
            );

            if ($datos['effect'] !== '') {
                $conTexto++;
            }

            $claves = $datos['effect_keys'];
            if (count($claves) === 1 && $datos['effect'] === '') {
                $clavesSinTexto[$claves[0]] = true;
            }
        }

        $this->command?->info(sprintf(
            '%d habilidades · %d con texto · %d sin texto · %d actualizadas.',
            count($fusion),
            $conTexto,
            count($fusion) - $conTexto,
            $actualizadas,
        ));

        if ($clavesSinTexto !== []) {
            ksort($clavesSinTexto);

            $this->command?->warn(sprintf(
                '%d claves de una sola habilidad sin texto ni descripción en %s: %s',
                count($clavesSinTexto),
                self::DESCRIPCIONES,
                implode(', ', array_keys($clavesSinTexto)),
            ));
        }
    }

    /**
     * Construye el mapa `nombre normalizado => datos` sin tocar la base de datos.
     *
     * Precedencia de `effect_keys`: lote JSON, `CatalogoHabilidades`, nombre
     * normalizado. Precedencia de `effect`: descripción por clave (solo si la
     * habilidad tiene una clave), texto del CSV, `effect` actual de la base de
     * datos y cadena vacía.
     *
     * @param  array<string, string>  $textos
     * @param  array<string, array{effect: string, effect_keys: list<string>}>  $actuales
     * @return list<array{name: string, effect: string, effect_keys: list<string>}>
     */
    private function fusionar(array $textos, array $actuales): array
    {
        /** @var array<string, array{name: string, effect: string, effect_keys: list<string>}> $fusion */
        $fusion = [];

        foreach ($this->leerCsv(storage_path('data/poke skills - habilidades.csv')) as $fila) {
            $nombre = trim((string) ($fila['habilidad'] ?? ''));
            if ($nombre === '') {
                continue;
            }

            $efecto = (string) ($fila['efecto'] ?? '');
            $fusion[$this->normalizar($nombre)] = [
                'name' => $nombre,
                'effect' => trim($efecto) === '' ? '' : $efecto,
                'effect_keys' => [],
            ];
        }

        foreach ($this->leerCsv(storage_path('data/poke habilidades.csv')) as $fila) {
            foreach ($this->nombresDeHabilidades((string) ($fila['habilidades'] ?? '')) as $nombre) {
                $fusion[$this->normalizar($nombre)] ??= [
                    'name' => $nombre,
                    'effect' => '',
                    'effect_keys' => [],
                ];
            }
        }

        foreach ($this->definicionesDeLotes() as $nombre => $claves) {
            $indice = $this->normalizar($nombre);
            $fusion[$indice] ??= [
                'name' => $nombre,
                'effect' => '',
                'effect_keys' => [],
            ];

            if ($claves !== []) {
                $fusion[$indice]['effect_keys'] = $claves;
            }
        }

        foreach ($fusion as $indice => $datos) {
            $claves = $datos['effect_keys'];
            if ($claves === []) {
                $claves = CatalogoHabilidades::clavesDe($datos['name'])->toList();
            }
            if ($claves === []) {
                $claves = [NormalizadorNombre::normalizar($datos['name'])];
            }

            $fusion[$indice]['effect_keys'] = $claves;
            $fusion[$indice]['effect'] = $this->resolverEfecto(
                $claves,
                $datos['effect'],
                $actuales[$indice]['effect'] ?? '',
                $textos,
            );
        }

        return array_values($fusion);
    }

    /**
     * Resuelve el texto final sin permitir que un vacío pise un texto previo.
     * Las habilidades compuestas (≥2 claves) conservan el texto del CSV.
     *
     * @param  list<string>  $claves
     * @param  array<string, string>  $textos
     */
    private function resolverEfecto(array $claves, string $efectoCsv, string $efectoActual, array $textos): string
    {
        if (count($claves) === 1) {
            $texto = $textos[$claves[0]] ?? null;
            if ($texto !== null) {
                return $texto;
            }
        }

        if (trim($efectoCsv) !== '') {
            return $efectoCsv;
        }

        if (trim($efectoActual) !== '') {
            return $efectoActual;
        }

        return '';
    }

    /**
     * Mapa `nombre => effect_keys` de los lotes de importación. Los lotes
     * actuales no traen texto: solo aportan claves.
     *
     * @return array<string, list<string>>
     */
    private function definicionesDeLotes(): array
    {
        $definiciones = [];

        foreach (self::LOTES as $archivo) {
            $ruta = storage_path('data/'.$archivo);
            if (! is_file($ruta)) {
                continue;
            }

            $lote = json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($lote)) {
                throw new RuntimeException('El lote '.$archivo.' no contiene un objeto JSON válido.');
            }

            foreach ($lote['definitions'] ?? [] as $definicion) {
                $nombre = trim((string) ($definicion['name'] ?? ''));
                if ($nombre === '') {
                    continue;
                }

                $claves = [];
                foreach ((array) ($definicion['effect_keys'] ?? []) as $clave) {
                    if (! is_string($clave)) {
                        continue;
                    }

                    $clave = trim($clave);
                    if ($clave !== '') {
                        $claves[] = $clave;
                    }
                }

                $definiciones[$nombre] = array_values(array_unique($claves));
            }
        }

        return $definiciones;
    }

    /**
     * Descripciones por `effect_key`. La ausencia del archivo es válida
     * mientras las descripciones se redactan por lotes; un archivo corrupto
     * detiene el seeder antes de escribir.
     *
     * @return array<string, string>
     */
    private function descripciones(): array
    {
        $ruta = storage_path('data/'.self::DESCRIPCIONES);
        if (! is_file($ruta)) {
            $this->command?->warn(
                'No existe storage/data/'.self::DESCRIPCIONES.'. Se conservan los textos del CSV y de la base de datos.',
            );

            return [];
        }

        $datos = json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($datos) || ! is_array($datos['descripciones'] ?? null)) {
            throw new RuntimeException('El archivo '.self::DESCRIPCIONES.' no contiene un mapa `descripciones` válido.');
        }

        $textos = [];
        foreach ($datos['descripciones'] as $clave => $texto) {
            if (! is_string($texto)) {
                throw new RuntimeException('La descripción de la clave '.$clave.' no es una cadena.');
            }

            $texto = trim($texto);
            if ($texto !== '') {
                $textos[(string) $clave] = $texto;
            }
        }

        return $textos;
    }

    /**
     * Estado actual de la base de datos, indexado como el mapa de fusión.
     *
     * @return array<string, array{effect: string, effect_keys: list<string>}>
     */
    private function efectosActuales(): array
    {
        $actuales = [];

        foreach (Ability::query()->get(['id', 'name', 'effect', 'data']) as $habilidad) {
            $datos = is_array($habilidad->data) ? $habilidad->data : [];

            $claves = [];
            foreach ((array) ($datos['effect_keys'] ?? []) as $clave) {
                if (is_string($clave)) {
                    $claves[] = $clave;
                }
            }

            $actuales[$this->normalizar((string) $habilidad->name)] = [
                'effect' => (string) $habilidad->effect,
                'effect_keys' => $claves,
            ];
        }

        return $actuales;
    }

    private function normalizar(string $nombre): string
    {
        return NormalizadorNombre::normalizar(str_replace(['(', ')'], '', $nombre));
    }

    /**
     * @return list<string>
     */
    private function nombresDeHabilidades(string $valor): array
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

    /**
     * @return list<array<string, string|null>>
     */
    private function leerCsv(string $ruta): array
    {
        if (! is_file($ruta)) {
            return [];
        }

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
}
