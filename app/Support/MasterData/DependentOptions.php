<?php

namespace App\Support\MasterData;

use App\Enums\MasterData\DocumentModule;
use App\Models\MasterData\Partner;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The choices a contract form offers, for one company: only active rows, in
 * the company's own order, and only those that fit the choice made before.
 * The rules mirror Node: partners depend on the platform (a group that names
 * the platform wins; otherwise groups that apply to every platform), and
 * document types depend on the module and, when given, the category. There is
 * no "other, type it in" choice: a value that is not here must be added to the
 * list first. Every user of the company may read these options.
 *
 * @phpstan-type Option array{value: int, label: string, code?: string}
 */
class DependentOptions
{
    /** @return list<Option> */
    public function platforms(Tenant $tenant): array
    {
        return $this->map($tenant->platforms()->options()->get(), true);
    }

    /**
     * Partners offered for a platform of the company. An unknown, foreign or
     * hidden platform has none.
     *
     * @return list<Option>
     */
    public function partners(Tenant $tenant, int $platformId): array
    {
        if (! $tenant->platforms()->whereKey($platformId)->where('is_active', true)->exists()) {
            return [];
        }

        $groups = fn (): Builder => $tenant->partnerGroups()->getQuery()->where('partner_groups.is_active', true);
        $groupIds = $groups()->whereHas('platforms', fn (Builder $platforms) => $platforms->whereKey($platformId))->pluck('partner_groups.id');

        if ($groupIds->isEmpty()) {
            $groupIds = $groups()->whereDoesntHave('platforms')->pluck('partner_groups.id');
        }

        $partners = Partner::query()
            ->join('partner_groups', 'partner_groups.id', '=', 'partners.partner_group_id')
            ->where('partners.tenant_id', $tenant->id)->where('partners.is_active', true)
            ->whereIn('partners.partner_group_id', $groupIds)
            ->orderBy('partner_groups.sort_order')->orderBy('partners.sort_order')->orderBy('partners.name')
            ->get(['partners.id', 'partners.name']);

        return $this->map($partners->unique(fn (Partner $partner): string => mb_strtolower($partner->name))->values(), false);
    }

    /**
     * Categories of a module: external documents use every category except the
     * employee one; employee documents use only that one.
     *
     * @return list<Option>
     */
    public function categories(Tenant $tenant, DocumentModule $module): array
    {
        $employee = $module === DocumentModule::Employee;

        return $this->map($tenant->contractCategories()->options()->where('is_employee_key', $employee)->get(), true);
    }

    /**
     * Document types of a module, narrowed to a category when one is chosen. A
     * type with no categories fits every category; external documents never
     * show a type that belongs only to the employee category.
     *
     * @return list<Option>
     */
    public function documentTypes(Tenant $tenant, DocumentModule $module, ?int $categoryId = null): array
    {
        $modules = array_map(fn (DocumentModule $case): string => $case->value, array_filter(DocumentModule::cases(), fn (DocumentModule $case): bool => $case->offeredIn($module)));
        $query = $tenant->contractDocumentTypes()->options()->whereIn('module', $modules);

        if ($categoryId !== null) {
            $query->where(fn (Builder $types) => $types->whereDoesntHave('categories')
                ->orWhereHas('categories', fn (Builder $categories) => $categories->whereKey($categoryId)));
        } elseif ($module === DocumentModule::External) {
            $query->where(fn (Builder $types) => $types->whereDoesntHave('categories')
                ->orWhereHas('categories', fn (Builder $categories) => $categories->where('is_employee_key', false)));
        }

        return $this->map($query->get(), true);
    }

    /** @return list<Option> */
    public function employmentTypes(Tenant $tenant): array
    {
        return $this->map($tenant->employmentTypes()->options()->get(), true);
    }

    /** @return list<Option> */
    public function partyTypes(Tenant $tenant): array
    {
        return $this->map($tenant->partyTypes()->options()->get(), true);
    }

    /** @return list<Option> */
    public function currencies(Tenant $tenant): array
    {
        return $this->map($tenant->currencies()->options()->get(), true);
    }

    /**
     * Active vendors by name only: their contact, tax and bank details are not options.
     *
     * @return list<Option>
     */
    public function vendors(Tenant $tenant): array
    {
        return $this->map($tenant->vendors()->where('is_active', true)->orderByRaw('lower(name)')->get(['id', 'name']), false);
    }

    /**
     * Departments are the DCS ones, only read here, by name.
     *
     * @return list<Option>
     */
    public function departments(Tenant $tenant): array
    {
        return $this->map($tenant->dcsDepartments()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']), true);
    }

    /**
     * @param  Collection<int, covariant Model>  $rows
     * @return list<Option>
     */
    private function map(Collection $rows, bool $withCode): array
    {
        return $rows->map(fn (Model $row): array => [
            'value' => (int) $row->getKey(),
            'label' => (string) $row->getAttribute('name'),
            ...($withCode ? ['code' => (string) $row->getAttribute('code')] : []),
        ])->values()->all();
    }
}
