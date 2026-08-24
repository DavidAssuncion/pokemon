<?php

declare(strict_types=1);

namespace App\Crud\Base\Generators;

use Illuminate\Support\Str;

class ModelGenerator
{
    public function make(string $module, array $schema): string
    {
        $class = Str::studly($module);

        $fillable = '';

        foreach ($schema['columns'] as $col) {
            if (in_array($col->column_name, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }
            $fillable .= "        '{$col->column_name}',\n";
        }

        $relations = '';

        foreach ($schema['relations'] as $rel) {
            $relMethod = Str::camel(Str::replaceLast('_id', '', $rel->column_name));
            $relatedClass = '\Src\Crud\\'.Str::studly($rel->foreign_table).'\Infra\\'.Str::studly($rel->foreign_table);

            $relations .= <<<PHP

    public function {$relMethod}()
    {
        return \$this->belongsTo({$relatedClass}::class, '{$rel->column_name}', '{$rel->foreign_column}');
    }

PHP;
        }

        return <<<PHP
<?php

namespace Src\Crud\\$module\Infra;

use Illuminate\Database\Eloquent\Model;

class $class extends Model
{
    protected \$table = '{$schema['table']}';

    protected \$fillable = [
$fillable    ];
$relations}
PHP;
    }
}
