<?php

namespace App\Crud\Base\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RoutesGenerator
{
    public function append(string $module): void
    {
        $path = base_path('src/Crud/routes.php');

        if (!file_exists($path) || filesize($path) === 0) {
            File::put($path, "<?php\n\n");
        }

        $route = Str::kebab($module);
        $controller = "Src\\Crud\\{$module}\\Infra\\{$module}Controller";

        $content = <<<PHP
Route::prefix('crud/{$route}')->name('{$route}.')->group(function () {
    Route::get('/', [{$controller}::class, 'index'])->name('index');
    Route::get('/create', [{$controller}::class, 'create'])->name('create');
    Route::post('/store', [{$controller}::class, 'store'])->name('store');
    Route::get('/{id}', [{$controller}::class, 'show'])->name('show');
    Route::get('/{id}/edit', [{$controller}::class, 'edit'])->name('edit');
    Route::put('/{id}', [{$controller}::class, 'update'])->name('update');
    Route::delete('/{id}', [{$controller}::class, 'destroy'])->name('destroy');
});

PHP;

        File::append($path, $content);
    }
}
