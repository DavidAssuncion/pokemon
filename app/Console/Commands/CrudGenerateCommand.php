<?php

namespace App\Console\Commands;

use App\Crud\Base\CrudGenerator;
use App\Crud\Base\PostgreSqlSchemaReader;
use Illuminate\Console\Command;

class CrudGenerateCommand extends Command
{
    protected $signature = 'crud:generate {table?}';
    protected $description = 'Generate CRUD modules from PostgreSQL schema';

    public function handle(
        PostgreSqlSchemaReader $reader,
        CrudGenerator $generator
    ) {
        $table = $this->argument('table');

        $tables = $table
            ? [$table]
            : $reader->getTables();

        foreach ($tables as $t) {
            $schema = $reader->getSchema($t->table_name ?? $t);

            $generator->generate($schema);

            $this->info("Generated CRUD for: " . ($t->table_name ?? $t));
        }

        return self::SUCCESS;
    }
}