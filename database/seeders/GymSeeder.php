<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Gimnasios\Domain\CatalogoGimnasios;
use Src\Gimnasios\Domain\Repositories\GymCatalogoRepositoryInterface;

/**
 * Migra los 18 gimnasios del catálogo en código (CatalogoGimnasios) a las
 * tablas gyms + gym_stages. Idempotente: reinserta/actualiza por slug.
 */
class GymSeeder extends Seeder
{
    public function run(): void
    {
        $catalogo = app(CatalogoGimnasios::class);
        $repositorio = app(GymCatalogoRepositoryInterface::class);

        foreach ($catalogo->todos() as $gimnasio) {
            $repositorio->insertar(
                slug: $gimnasio->slug,
                medalla: $gimnasio->medalla,
                tipo: $gimnasio->tipo,
                nivelMinimo: $gimnasio->nivelMinimo,
                equipos: $gimnasio->equipos,
            );
        }
    }
}
