<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * D11/RF-12: `exploraciones_activas.eventos` json → jsonb.
     *
     * PostgreSQL: jsonb nativo (mejores queries/índices).
     * SQLite: no tiene jsonb; el tipo json/text existente es suficiente, no-op.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE exploraciones_activas ALTER COLUMN eventos TYPE jsonb USING eventos::jsonb');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE exploraciones_activas ALTER COLUMN eventos TYPE json USING eventos::json');
        }
    }
};
