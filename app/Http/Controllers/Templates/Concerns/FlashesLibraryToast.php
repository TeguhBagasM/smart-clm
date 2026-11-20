<?php

namespace App\Http\Controllers\Templates\Concerns;

use Inertia\Inertia;

/**
 * Sends the result of a library action as a toast; the texts live in config/contract_templates.php.
 */
trait FlashesLibraryToast
{
    /**
     * @param  array<string, string>  $replace  placeholders of the text, e.g. [':title' => ...]
     */
    protected function flashLibrary(string $key, array $replace = [], string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => strtr(config()->string("contract_templates.toast.{$key}"), $replace)]);
    }
}
