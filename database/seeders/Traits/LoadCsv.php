<?php

declare(strict_types=1);

namespace Database\Seeders\Traits;

trait LoadCsv
{
    /**
     * Carga un CSV con cabecera en la primera línea y devuelve un array de
     * filas asociativas (columna => valor). Devuelve un array vacío si el
     * fichero no existe o no puede abrirse.
     *
     * @return list<array<string, string>>
     */
    private function loadCsv(string $path): array
    {
        $data = [];

        if (! file_exists($path) || ($handle = fopen($path, 'r')) === false) {
            return $data;
        }

        $headers = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (empty($headers)) {
                $headers = $row;

                continue;
            }

            $data[] = array_combine($headers, $row);
        }

        fclose($handle);

        return $data;
    }
}
