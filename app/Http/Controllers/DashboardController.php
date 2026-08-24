<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function cruds(): View
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

        return view('crud.dashboard', compact('modules'));
    }
}
