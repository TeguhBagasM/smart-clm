<?php

namespace App\Http\Controllers\Documents\Concerns;

use App\Support\Dcs\DcsException;
use App\Support\Dcs\DraftNotReady;
use Closure;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Runs a DCS action for a page and goes back with a toast: the success message,
 * or the action's own explanation when it refuses (a stale page, a turn that
 * has passed, an engine that failed). Validation errors are left to Laravel.
 * Afterwards it goes back, or to `$to` when given (after creating something); a
 * closure gets what the work returned, to build the address from it.
 */
trait RunsDcsActions
{
    protected function perform(Closure $work, string $success, string|Closure|null $to = null): RedirectResponse
    {
        try {
            $result = $work();
            Inertia::flash('toast', ['type' => 'success', 'message' => $success]);
        } catch (DraftNotReady $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Dokumen belum bisa disusun. Lihat daftar yang perlu diperbaiki.']);

            return back()->withErrors(collect($e->problems)->mapWithKeys(fn (string $problem, int $index): array => ["compose.{$index}" => $problem])->all());
        } catch (DcsException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return match (true) {
            $to === null || ! isset($result) && $to instanceof Closure => back(),
            $to instanceof Closure => redirect($to($result)),
            default => redirect($to),
        };
    }
}
