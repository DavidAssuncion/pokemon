<?php

declare(strict_types=1);

namespace App\Crud\Base\Generators;

class RequestGenerator
{
    public function make(string $module, array $schema): string
    {
        $rules = '';

        foreach ($schema['columns'] as $col) {

            if (in_array($col->column_name, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }

            $rules .= "            '{$col->column_name}' => ['required'],\n";
        }

        return <<<PHP
<?php

namespace Src\Crud\\$module\Infra;

use Illuminate\Foundation\Http\FormRequest;

class {$module}Request extends FormRequest
{
    public function rules(): array
    {
        return [
$rules
        ];
    }
}
PHP;
    }
}
