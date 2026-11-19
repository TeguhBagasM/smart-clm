<?php

namespace App\Http\Controllers;

use App\Support\Navigation\NavigationMenu;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Sedang dikembangkan" page of a menu whose module is not migrated yet.
 */
class ModulePlaceholderController extends Controller
{
    public function __invoke(NavigationMenu $navigationMenu, string $module, string $page): Response
    {
        $placeholder = $navigationMenu->placeholder($module, $page) ?? abort(404);

        return Inertia::render('modules/InDevelopment', $placeholder);
    }
}
