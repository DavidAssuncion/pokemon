<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Habitat;
use App\Models\Province;
use Illuminate\Database\Seeder;

class BonguriHabitatSeeder extends Seeder
{
    /**
     * Bonguri (clave de color) asignado a cada habitat segun su provincia.
     *
     * Los nombres son los exactos de `database/seeders/ProvinceSeeder.php`.
     * La provincia "Vacio" no tiene color y queda en NULL; el dominio aplica
     * el fallback (`ClaveBonguri::porDefecto()` = blanco).
     *
     * Debe ejecutarse despues de `ProvinceSeeder` y `HabitatSeeder`: en una
     * instalacion nueva la migracion corre con las tablas vacias, por lo que
     * el backfill de la migracion no tiene filas que actualizar.
     *
     * @var array<string, string>
     */
    private const BONGURI_POR_PROVINCIA = [
        'Galicia' => 'blanco',
        'Euskadi' => 'amarillo',
        'Barcelona' => 'negro',
        'Extremadura' => 'verde',
        'Nova Alacant' => 'rosa',
        'Mutxamel' => 'azul',
        'Andalucia' => 'rojo',
    ];

    /**
     * Run the database seeds.
     *
     * Idempotente: reasigna siempre el mismo color provincial, de modo que
     * reejecutarlo no altera el resultado. Las provincias inexistentes se
     * omiten y "Vacio" queda sin tocar (NULL).
     */
    public function run(): void
    {
        $provinceIds = Province::query()->pluck('id', 'name');

        foreach (self::BONGURI_POR_PROVINCIA as $provinceName => $bonguri) {
            $provinceId = $provinceIds->get($provinceName);

            if ($provinceId === null) {
                continue;
            }

            Habitat::query()
                ->where('province_id', $provinceId)
                ->update(['bonguri' => $bonguri]);
        }
    }
}
