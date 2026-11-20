<?php

namespace App\Support\MasterData;

use App\Enums\MasterData\DocumentModule;
use App\Enums\MasterData\MasterList;
use App\Http\Resources\MasterData\CategoryResource;
use App\Http\Resources\MasterData\DocumentTypeResource;
use App\Http\Resources\MasterData\MasterDataResource;
use App\Http\Resources\MasterData\PartnerGroupResource;
use App\Http\Resources\MasterData\PartnerResource;
use App\Http\Resources\MasterData\PartyTypeResource;
use App\Models\Tenant;
use App\Support\Listing\DocumentTypeListSpec;
use App\Support\Listing\ListSpec;
use App\Support\Listing\LookupListSpec;
use App\Support\Listing\PartnerListSpec;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Everything a master data list page needs, for one company: the page of rows
 * (a Resource each, with `usage_count`), the list meta (search, filters,
 * sorts), the text and columns from config, and the options its form needs.
 * Which spec, query and Resource a list uses is decided here, not in Vue.
 */
class ListPage
{
    public function __construct(private Usage $usage, private DependentOptions $options) {}

    /**
     * @return array<string, mixed>
     */
    public function props(MasterList $list, Tenant $tenant, Request $request): array
    {
        $spec = $this->spec($list, $tenant);
        $parsed = $spec->parse($request);
        $resource = $this->resource($list);

        return [
            'items' => $resource::collection($spec->paginate($this->usage->withCount($this->query($list, $tenant)), $parsed)),
            'list' => $spec->meta($parsed),
            'info' => $this->info($list->value),
            'options' => $this->formOptions($list, $tenant),
            'reorder' => $this->reorder($list, $tenant, $request),
            'order_rows' => Inertia::optional(fn (): array => $this->orderRows($list, $tenant, $request)),
        ];
    }

    /**
     * Text and columns of a list from config, with whether usage counts documents yet.
     *
     * @return array<string, mixed>
     */
    public function info(string $key): array
    {
        $covers = config()->boolean('master_data.usage_covers_documents');

        return [
            'key' => $key,
            ...config()->array("master_data.lists.{$key}"),
            'usage_covers_documents' => $covers,
            'usage_notice' => $covers ? null : config()->string('master_data.messages.usage_notice'),
            'texts' => Arr::only(config()->array('master_data.messages'), ['impact_hide_title', 'impact_used', 'impact_unused', 'impact_show_title', 'impact_show', 'reorder_failed', 'save_failed']),
        ];
    }

    /**
     * Whether the order of this list can be set now, and why not when it cannot. Partners are
     * ordered one group at a time (the group filter); a long list is too much to send at once.
     *
     * @return array{available: bool, hint: string|null}
     */
    private function reorder(MasterList $list, Tenant $tenant, Request $request): array
    {
        if (! Gate::forUser($request->user())->allows('reorder', $list->model())) {
            return ['available' => false, 'hint' => null];
        }

        if ($list === MasterList::Partner && $this->groupFilter($request) === null) {
            return ['available' => false, 'hint' => config()->string('master_data.messages.reorder_pick_group')];
        }

        if ($this->orderQuery($list, $tenant, $request)->count() > config()->integer('master_data.reorder_max')) {
            return ['available' => false, 'hint' => config()->string('master_data.messages.reorder_too_many')];
        }

        return ['available' => true, 'hint' => null];
    }

    /**
     * Every row to be ordered (hidden ones too), in the company's current order.
     *
     * @return list<array{id: int, name: string, code: string|null, is_active: bool}>
     */
    private function orderRows(MasterList $list, Tenant $tenant, Request $request): array
    {
        return $this->orderQuery($list, $tenant, $request)->limit(config()->integer('master_data.reorder_max') + 1)->get()
            ->map(fn (Model $row): array => ['id' => (int) $row->getKey(), 'name' => (string) $row->getAttribute('name'), 'code' => $row->getAttribute('code'), 'is_active' => (bool) $row->getAttribute('is_active')])->all();
    }

    /**
     * @return Builder<Model>
     */
    private function orderQuery(MasterList $list, Tenant $tenant, Request $request): Builder
    {
        $query = $this->query($list, $tenant)->setEagerLoads([])->orderBy('sort_order')->orderBy('id');
        $group = $this->groupFilter($request);

        return $list === MasterList::Partner && $group !== null ? $query->where('partner_group_id', $group) : $query;
    }

    private function groupFilter(Request $request): ?int
    {
        $filter = $request->query('filter');
        $group = is_array($filter) ? ($filter['group'] ?? null) : null;

        return is_numeric($group) ? (int) $group : null;
    }

    private function spec(MasterList $list, Tenant $tenant): ListSpec
    {
        return match ($list) {
            MasterList::DocumentType => new DocumentTypeListSpec,
            MasterList::Partner => new PartnerListSpec($this->stringOptions($tenant->partnerGroups()->options()->get(['id', 'name'])->map(fn (Model $group): array => ['value' => (int) $group->getKey(), 'label' => (string) $group->getAttribute('name')])->all())),
            default => new LookupListSpec($list),
        };
    }

    /**
     * @return Builder<Model>
     */
    private function query(MasterList $list, Tenant $tenant): Builder
    {
        return match ($list) {
            MasterList::Platform => $tenant->platforms()->getQuery(),
            MasterList::Category => $tenant->contractCategories()->getQuery(),
            MasterList::DocumentType => $tenant->contractDocumentTypes()->with(['categories', 'extraCodes'])->getQuery(),
            MasterList::EmploymentType => $tenant->employmentTypes()->getQuery(),
            MasterList::PartyType => $tenant->partyTypes()->getQuery(),
            MasterList::Currency => $tenant->currencies()->getQuery(),
            MasterList::PartnerGroup => $tenant->partnerGroups()->with('platforms')->getQuery(),
            MasterList::Partner => $tenant->partners()->with('group')->getQuery(),
        };
    }

    /**
     * @return class-string<MasterDataResource>
     */
    private function resource(MasterList $list): string
    {
        return match ($list) {
            MasterList::Category => CategoryResource::class,
            MasterList::DocumentType => DocumentTypeResource::class,
            MasterList::PartyType => PartyTypeResource::class,
            MasterList::PartnerGroup => PartnerGroupResource::class,
            MasterList::Partner => PartnerResource::class,
            default => MasterDataResource::class,
        };
    }

    /**
     * What the add/edit form of a list lets the user pick from.
     *
     * @return array<string, mixed>
     */
    private function formOptions(MasterList $list, Tenant $tenant): array
    {
        return match ($list) {
            MasterList::DocumentType => [
                'modules' => array_map(fn (DocumentModule $module): array => ['value' => $module->value, 'label' => $module->label()], DocumentModule::cases()),
                'categories' => $tenant->contractCategories()->options()->get(['id', 'name'])->map(fn (Model $category): array => ['value' => (int) $category->getKey(), 'label' => (string) $category->getAttribute('name')])->all(),
            ],
            MasterList::PartnerGroup => ['platforms' => $this->options->platforms($tenant)],
            MasterList::Partner => ['groups' => $tenant->partnerGroups()->options()->get(['id', 'name'])->map(fn (Model $group): array => ['value' => (int) $group->getKey(), 'label' => (string) $group->getAttribute('name')])->all()],
            default => [],
        };
    }

    /**
     * @param  list<array{value: int, label: string}>  $options
     * @return list<array{value: string, label: string}>
     */
    public function stringOptions(array $options): array
    {
        return array_map(fn (array $option): array => ['value' => (string) $option['value'], 'label' => $option['label']], $options);
    }
}
