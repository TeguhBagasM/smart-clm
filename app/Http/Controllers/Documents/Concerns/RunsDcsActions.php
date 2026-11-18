<?php

namespace App\Http\Controllers\Documents\Concerns;

use App\Support\Dcs\DcsException;
use Closure;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Runs a DCS action for a page and goes back with a toast: the success message,
 * or the action's own explanation when it refuses (a stale page, a turn that
 * has passed, an engine that failed). Validation errors are left to Laravel.
 */
trait RunsDcsActions
{
    protected function perform(Closure $work, string $success): RedirectResponse
    {
        try {
            $work();
            Inertia::flash('toast', ['type' => 'success', 'message' => $success]);
        } catch (DcsException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
