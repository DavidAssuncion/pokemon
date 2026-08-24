<?php

declare(strict_types=1);

namespace App\Crud\Base;

use App\Crud\Base\Generators\ControllerGenerator;
use App\Crud\Base\Generators\DtoGenerator;
use App\Crud\Base\Generators\ModelGenerator;
use App\Crud\Base\Generators\RequestGenerator;
use App\Crud\Base\Generators\RoutesGenerator;
use App\Crud\Base\Generators\ViewGenerator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CrudGenerator
{
    public function generate(array $schema): void
    {
        $module = Str::studly($schema['table']);

        $basePath = base_path("src/Crud/{$module}");

        $this->makeStructure($basePath, $module, $schema);

        $this->generateFiles($basePath, $module, $schema);
    }

    private function makeStructure(string $basePath, string $module, array $schema)
    {
        $dirs = [
            "{$basePath}/Domain",
            "{$basePath}/Infra",
            'resources/views/crud/'.Str::kebab($module),
        ];

        foreach ($dirs as $dir) {
            File::makeDirectory($dir, 0755, true, true);
        }
    }

    private function generateFiles(string $basePath, string $module, array $schema)
    {
        File::put(
            "{$basePath}/Domain/{$module}DTO.php",
            app(DtoGenerator::class)->make($module, $schema)
        );

        File::put(
            "{$basePath}/Infra/{$module}Controller.php",
            app(ControllerGenerator::class)->make($module, $schema)
        );

        File::put(
            "{$basePath}/Infra/{$module}Request.php",
            app(RequestGenerator::class)->make($module, $schema)
        );

        File::put(
            "{$basePath}/Infra/{$module}.php",
            app(ModelGenerator::class)->make($module, $schema)
        );

        app(RoutesGenerator::class)->append($module);

        $viewPath = 'resources/views/crud/'.Str::kebab($module);

        $views = app(ViewGenerator::class)->make($module, $schema);

        File::put("{$viewPath}/index.blade.php", $views['index']);
        File::put("{$viewPath}/form.blade.php", $views['form']);
        File::put("{$viewPath}/show.blade.php", $views['show']);

        $this->generateDashboard();
    }

    private function generateDashboard(): void
    {
        $modules = [];

        foreach (glob(resource_path('views/crud/*'), GLOB_ONLYDIR) as $dir) {
            $name = basename($dir);
            if ($name === 'dashboard') {
                continue;
            }
            $modules[] = $name;
        }

        sort($modules);

        $links = '';

        foreach ($modules as $name) {
            $label = Str::title(str_replace('-', ' ', $name));
            $links .= "    <li><a href=\"{{ route('{$name}.index') }}\">{$label}</a></li>\n";
        }

        $dashboard = <<<BLADE
<h1>CRUD Dashboard</h1>

<ul>
{$links}</ul>
BLADE;

        File::put(
            resource_path('views/crud/dashboard.blade.php'),
            $dashboard
        );
    }
}
