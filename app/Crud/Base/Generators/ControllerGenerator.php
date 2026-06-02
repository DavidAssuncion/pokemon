<?php

namespace App\Crud\Base\Generators;

use Illuminate\Support\Str;

class ControllerGenerator
{
    public function make(string $module, array $schema): string
    {
        $model = Str::studly($module);
        $request = Str::studly($module) . 'Request';
        $route = Str::kebab($module);

        $relationLoads = '';
        $viewParams = '';

        foreach ($schema['relations'] as $i => $rel) {
            $var = strtolower($rel->foreign_table);
            $class = '\Src\Crud\\' . Str::studly($rel->foreign_table) . '\Infra\\' . Str::studly($rel->foreign_table);
            $relationLoads .= "        \${$var} = {$class}::pluck('name', 'id');\n";
            $viewParams .= ($i > 0 ? ', ' : '') . "'{$var}'";
        }

        if ($viewParams) {
            $createView = "return view('crud.{$route}.form', compact({$viewParams}));\n";
            $editView = "return view('crud.{$route}.form', compact('item', {$viewParams}));\n";
        } else {
            $createView = "return view('crud.{$route}.form');\n";
            $editView = "return view('crud.{$route}.form', compact('item'));\n";
        }

        return <<<PHP
<?php

namespace Src\Crud\\{$module}\Infra;

use Src\Crud\\{$module}\Infra\\{$model};
use Src\Crud\\{$module}\Infra\\{$request};
use Illuminate\Http\Request;

class {$model}Controller
{
    public function index(Request \$request)
    {
        \$items = {$model}::paginate(config('crud.pagination', 50));
        return view('crud.{$route}.index', compact('items'));
    }

    public function show(int \$id)
    {
        \$item = {$model}::findOrFail(\$id);
        return view('crud.{$route}.show', compact('item'));
    }

    public function create()
    {
{$relationLoads}        {$createView}    }

    public function store({$request} \$request)
    {
        {$model}::create(\$request->validated());
        return redirect()->route('{$route}.index');
    }

    public function edit(int \$id)
    {
        \$item = {$model}::findOrFail(\$id);
{$relationLoads}        {$editView}    }

    public function update({$request} \$request, int \$id)
    {
        \$item = {$model}::findOrFail(\$id);
        \$item->update(\$request->validated());
        return redirect()->route('{$route}.index');
    }

    public function destroy(int \$id)
    {
        {$model}::destroy(\$id);
        return redirect()->route('{$route}.index');
    }
}
PHP;
    }
}
