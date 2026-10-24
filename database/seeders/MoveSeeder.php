<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Move;
use Illuminate\Database\Seeder;

/**
 * Carga el catálogo de movimientos desde storage/data/moves.json.
 *
 * El fichero es opcional: si no existe, el seeder no hace nada. Cada entrada
 * usa las mismas claves que la tabla `moves`; `slug` es la clave de upsert.
 */
class MoveSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = storage_path('data/moves.json');
        if (! is_file($ruta)) {
            return;
        }

        /** @var list<array<string, mixed>> $filas */
        $filas = json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);

        foreach ($filas as $fila) {
            if (! isset($fila['slug'])) {
                continue;
            }

            Move::updateOrCreate(
                ['slug' => (string) $fila['slug']],
                $fila,
            );
        }

        // El Limit Break se retiró del juego; limpia catálogos antiguos.
        Move::where('slug', 'estallido_limite')->delete();
    }
}
