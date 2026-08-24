<?php

declare(strict_types=1);

namespace App\Crud\Base\Generators;

use Illuminate\Support\Str;

class DtoGenerator
{
    public function make(string $module, array $schema): string
    {
        $class = Str::studly($module).'DTO';

        $properties = '';

        foreach ($schema['columns'] as $col) {

            if (in_array($col->column_name, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }

            $type = $this->mapType($col->data_type);

            $properties .= "    public ?{$type} \${$col->column_name} = null;\n\n";
        }

        return <<<PHP
<?php

namespace Src\Crud\\$module\Domain;

use App\Crud\Base\BaseDTO;

class $class extends BaseDTO
{
$properties
}
PHP;
    }

    private function mapType(string $pgType): string
    {
        return match ($pgType) {
            'integer', 'bigint' => 'int',
            'boolean' => 'bool',
            'json', 'jsonb' => 'array',
            default => 'string',
        };
    }
}
