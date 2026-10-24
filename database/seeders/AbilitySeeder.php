<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ability;
use Illuminate\Database\Seeder;
use Src\Battle\Domain\Catalog\CatalogoHabilidades;
use Src\Battle\Domain\Catalog\NormalizadorNombre;

/**
 * Carga el catálogo de habilidades desde `poke skills - habilidades.csv` y
 * crea placeholders para cualquier habilidad asignada en `poke habilidades.csv`
 * que no aparezca en el catálogo de definiciones.
 */
class AbilitySeeder extends Seeder
{
    public function run(): void
    {
        $definiciones = $this->leerCsv(storage_path('data/poke skills - habilidades.csv'));
        $asignaciones = $this->leerCsv(storage_path('data/poke habilidades.csv'));

        $nombres = [];
        foreach ($definiciones as $fila) {
            $nombre = trim((string) ($fila['habilidad'] ?? ''));
            if ($nombre === '') {
                continue;
            }

            $nombres[$this->normalizar($nombre)] = [
                'name' => $nombre,
                'effect' => (string) ($fila['efecto'] ?? ''),
            ];
        }

        foreach ($asignaciones as $fila) {
            foreach ($this->nombresDeHabilidades((string) ($fila['habilidades'] ?? '')) as $nombre) {
                $clave = $this->normalizar($nombre);
                if (! isset($nombres[$clave])) {
                    $nombres[$clave] = ['name' => $nombre, 'effect' => ''];
                }
            }
        }

        foreach ($nombres as $datos) {
            $claves = CatalogoHabilidades::clavesDe($datos['name']);
            if ($claves === []) {
                $claves = [NormalizadorNombre::normalizar($datos['name'])];
            }

            Ability::updateOrCreate(
                ['name' => $datos['name']],
                [
                    'effect' => $datos['effect'],
                    'data' => ['effect_keys' => $claves],
                ],
            );
        }

        foreach (['abilities_lote1.json', 'abilities_lote2.json', 'abilities_lote3.json'] as $archivoLote) {
            $rutaLote = storage_path('data/'.$archivoLote);
            if (! is_file($rutaLote)) {
                continue;
            }
            $lote = json_decode((string) file_get_contents($rutaLote), true, 512, JSON_THROW_ON_ERROR);
            foreach ($lote['definitions'] ?? [] as $definicion) {
                $nombre = trim((string) ($definicion['name'] ?? ''));
                if ($nombre === '') {
                    continue;
                }

                Ability::updateOrCreate(
                    ['name' => $nombre],
                    [
                        'effect' => (string) ($definicion['effect'] ?? ''),
                        'data' => ['effect_keys' => array_values(array_filter(
                            (array) ($definicion['effect_keys'] ?? []),
                            'is_string',
                        ))],
                    ],
                );
            }
        }

        $this->command?->info(count($nombres).' habilidades registradas.');
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
