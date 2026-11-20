<?php

namespace App\Http\Controllers\MasterData;

use App\Actions\MasterData\SaveVendor;
use App\Actions\MasterData\SetVendorActive;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MasterData\Concerns\ResolvesCompany;
use App\Http\Requests\MasterData\SaveVendorRequest;
use App\Http\Resources\MasterData\VendorResource;
use App\Models\MasterData\Vendor;
use App\Support\Listing\VendorListSpec;
use App\Support\MasterData\MasterDataCatalog;
use App\Support\MasterData\Usage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Data Vendor: list, add, change, hide and show again. Only vendors.manage
 * holders reach it (other users get vendor names as form options only).
 * Texts come from config/vendors.php.
 */
class VendorController extends Controller
{
    use ResolvesCompany;

    public function index(Request $request, Usage $usage, MasterDataCatalog $catalog): Response|RedirectResponse|SymfonyResponse
    {
        if (! Gate::forUser($request->user())->allows('viewAny', Vendor::class)) {
            return $this->forbidden($request);
        }

        $tenant = $this->company($request);

        if ($tenant === null) {
            $this->toast('choose_company', type: 'error');

            return redirect()->route('master-data.index');
        }

        $spec = new VendorListSpec;
        $parsed = $spec->parse($request);

        return Inertia::render('master-data/Vendors', [
            'items' => VendorResource::collection($spec->paginate($usage->withCount($tenant->vendors()->getQuery()), $parsed)),
            'list' => $spec->meta($parsed),
            'info' => $this->info(),
            'company' => ['id' => $tenant->id, 'name' => $tenant->name],
            'companies' => $catalog->companies($request->user()),
        ]);
    }

    public function store(SaveVendorRequest $request, SaveVendor $save): RedirectResponse
    {
        $vendor = $save->handle($request->user(), $request->tenant(), $request->vendorData());
        $this->notify('created', $vendor);

        return back();
    }

    public function update(SaveVendorRequest $request, Vendor $record, SaveVendor $save): RedirectResponse
    {
        $vendor = $save->handle($request->user(), $request->tenant(), $request->vendorData(), $record);
        $this->notify('updated', $vendor);

        return back();
    }

    public function deactivate(Request $request, Vendor $record, SetVendorActive $setActive): RedirectResponse
    {
        Gate::authorize('deactivate', $record);
        $setActive->handle($request->user(), $record, false);
        $this->notify('deactivated', $record);

        return back();
    }

    public function reactivate(Request $request, Vendor $record, SetVendorActive $setActive): RedirectResponse
    {
        Gate::authorize('reactivate', $record);
        $setActive->handle($request->user(), $record, true);
        $this->notify('reactivated', $record);

        return back();
    }

    /**
     * The page's texts: labels, columns, form sections and fields, and the impact texts.
     *
     * @return array<string, mixed>
     */
    private function info(): array
    {
        $covers = config()->boolean('vendors.usage_covers_contracts');
        $messages = config()->array('vendors.messages');

        return [
            ...Arr::only(config()->array('vendors'), ['label', 'title', 'description', 'search_placeholder', 'empty', 'add', 'columns', 'sections', 'fields', 'blank', 'bank_mask']),
            'usage_notice' => $covers ? null : $messages['usage_notice'],
            'texts' => Arr::only($messages, ['impact_hide_title', 'impact_used', 'impact_unused', 'impact_show_title', 'impact_show', 'save_failed']),
        ];
    }

    private function notify(string $key, Vendor $vendor): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => strtr(config()->string("vendors.messages.{$key}"), [':name' => $vendor->name])]);
    }
}
