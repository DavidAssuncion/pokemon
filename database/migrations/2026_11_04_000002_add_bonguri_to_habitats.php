<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Bonguri (clave de color) asignado a cada habitat segun su provincia.
     *
     * Los nombres son los exactos de `database/seeders/ProvinceSeeder.php`.
     * La provincia "Vacio" no tiene color y queda en NULL; el dominio aplica
     * el fallback (`ClaveBonguri::porDefecto()` = blanco).
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
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('habitats', function (Blueprint $table) {
            $table->string('bonguri', 16)->nullable()->after('name');
        });

        $this->backfillBonguri();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('habitats', function (Blueprint $table) {
            $table->dropColumn('bonguri');
        });
    }

    /**
     * Backfill determinista e idempotente: solo rellena habitats sin color, de
     * modo que no sobrescribe asignaciones manuales ni repite trabajo. Tolera
     * una base sin seeder (sin provincias) sin fallar.
     */
    private function backfillBonguri(): void
    {
        $provinceIds = DB::table('provinces')
            ->whereIn('name', array_keys(self::BONGURI_POR_PROVINCIA))
            ->pluck('id', 'name');

        if ($provinceIds->isEmpty()) {
            return;
        }

        foreach (self::BONGURI_POR_PROVINCIA as $provinceName => $bonguri) {
            $provinceId = $provinceIds->get($provinceName);

            if ($provinceId === null) {
                continue;
            }

            DB::table('habitats')
                ->where('province_id', $provinceId)
                ->whereNull('bonguri')
                ->update(['bonguri' => $bonguri]);
        }
    }
};
