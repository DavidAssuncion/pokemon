<?php

declare(strict_types=1);

namespace App\Crud\Base;

use Illuminate\Support\Facades\DB;

class PostgreSqlSchemaReader
{
    public function getTables(): array
    {
        $ignored = config('crud.ignore_tables');

        return collect(DB::select("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
    "))
            ->pluck('table_name')
            ->filter(fn ($table) => ! in_array($table, $ignored, true))
            ->values()
            ->all();
    }

    public function getSchema(string $table): array
    {
        if (in_array($table, config('crud.ignore_tables'), true)) {
            throw new \Exception("Table $table is ignored by CRUD generator");
        }

        return [
            'table' => $table,
            'columns' => $this->getColumns($table),
            'relations' => $this->getRelations($table),
        ];
    }

    private function getColumns(string $table): array
    {
        return DB::select('
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_name = ?
            ORDER BY ordinal_position
        ', [$table]);
    }

    private function getRelations(string $table): array
    {
        return DB::select("
            SELECT
                kcu.column_name,
                ccu.table_name AS foreign_table,
                ccu.column_name AS foreign_column
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
            JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY'
            AND tc.table_name = ?
        ", [$table]);
    }
}
