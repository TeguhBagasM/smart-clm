<?php

namespace App\Http\Middleware;

use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Models\Tenant;
use App\Support\Dcs\CurrentTenant;
use App\Support\Dcs\DcsException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant a DCS request works in and makes sure it has the default
 * configuration. Everyone works in their own tenant; a super admin, who has
 * none, picks one with ?tenantId= or a tenantId body field (the Node API's
 * convention, using the Node tenant id).
 */
class SetDcsTenant
{
    public function __construct(private CurrentTenant $currentTenant, private SeedDcsTenantDefaults $seedDcsTenantDefaults) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = $user->isSuperAdmin() ? $this->requestedTenant($request) : $user->tenant;

        $this->currentTenant->set($tenant);

        if (! $tenant->dcsNumberingRules()->whereNull('doc_type_id')->exists()) {
            $this->seedDcsTenantDefaults->handle($tenant);
        }

        return $next($request);
    }

    private function requestedTenant(Request $request): Tenant
    {
        $requested = (string) ($request->query('tenantId') ?? $request->input('tenantId'));

        if ($requested === '') {
            throw DcsException::badRequest('Pilih perusahaan (tenantId) terlebih dahulu.');
        }

        return Tenant::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('legacy_id', $requested)->orWhere('id', ctype_digit($requested) ? (int) $requested : 0))
            ->first() ?? throw DcsException::notFound('Perusahaan tidak ditemukan.');
    }
}
