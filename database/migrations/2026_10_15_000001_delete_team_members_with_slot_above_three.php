<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Los equipos estándar pasan a ser de 3 miembros (combates 3v3 y mazmorra
     * 3v1): elimina los team_members con slot fuera del top-3 (slots 1-based).
     */
    public function up(): void
    {
        DB::table('team_members')->where('slot', '>', 3)->delete();
    }

    /**
     * No-op: los miembros eliminados no son reconstruibles.
     */
    public function down(): void
    {
        //
    }
};
