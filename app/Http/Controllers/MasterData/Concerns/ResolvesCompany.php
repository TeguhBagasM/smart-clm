<?php

namespace App\Http\Controllers\MasterData\Concerns;

use App\Models\Tenant;
use App\Support\MasterData\ActingCompany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The company a master data page is for: the user's own, or, for a super admin,
 * the one named by `tenant_id`. Toasts use the texts in config/master_data.php.
 */
trait ResolvesCompany
{
    protected function company(Request $request): ?Tenant
    {
        return app(ActingCompany::class)->find($request->user(), $request->query('tenant_id'));
    }

    /**
     * A friendly page for someone who may not open a master data page (status 403).
     */
    protected function forbidden(Request $request): SymfonyResponse
    {
        return Inertia::render('errors/Forbidden', [
            'title' => config()->string('master_data.messages.forbidden_title'),
            'message' => config()->string('master_data.messages.forbidden'),
        ])->toResponse($request)->setStatusCode(403);
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    protected function toast(string $key, array $replace = [], string $type = 'success'): void
    {
        $replacements = [];

        foreach ($replace as $name => $value) {
            $replacements[":{$name}"] = (string) $value;
        }

        Inertia::flash('toast', ['type' => $type, 'message' => strtr(config()->string("master_data.messages.{$key}"), $replacements)]);
    }
}
