<?php

namespace App\Support\MasterData;

use App\Enums\MasterData\MasterList;
use App\Models\MasterData\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The index of master data: the groups, in the order the config gives, each
 * with the lists the user may open, a one-sentence purpose and how many active
 * rows the company has. A list the user may not manage is not shown at all.
 */
class MasterDataCatalog
{
    /**
     * @return list<array{key: string, title: string, description: string, items: list<array<string, mixed>>}>
     */
    public function groups(User $user, ?Tenant $tenant): array
    {
        $groups = [];

        foreach (config()->array('master_data.groups') as $group) {
            $items = [];

            foreach ($group['lists'] as $key) {
                $item = $this->item($user, $tenant, (string) $key);

                if ($item !== null) {
                    $items[] = $item;
                }
            }

            if ($items !== []) {
                $groups[] = ['key' => $group['key'], 'title' => $group['title'], 'description' => $group['description'], 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * Whether the user may open at least one list (what the sidebar needs to show the menu).
     */
    public function canOpenAny(User $user): bool
    {
        foreach ([...MasterList::cases(), null] as $list) {
            if (Gate::forUser($user)->allows('viewAny', $list?->model() ?? Employee::class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The companies a super admin may pick from; null for everyone else.
     *
     * @return list<array{value: int, label: string}>|null
     */
    public function companies(User $user): ?array
    {
        return $user->isSuperAdmin()
            ? Tenant::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn (Tenant $company): array => ['value' => $company->id, 'label' => $company->name])->all()
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function item(User $user, ?Tenant $tenant, string $key): ?array
    {
        $list = MasterList::tryFrom($key);
        $model = $list?->model() ?? Employee::class;

        if (! Gate::forUser($user)->allows('viewAny', $model)) {
            return null;
        }

        return [
            'key' => $key,
            'label' => config()->string("master_data.lists.{$key}.label"),
            'description' => config()->string("master_data.lists.{$key}.description"),
            'href' => $list === null ? route('master-data.employees.index', absolute: false) : route('master-data.lists.index', ['list' => $list->value], absolute: false),
            'count' => $tenant === null ? null : $model::query()->where('tenant_id', $tenant->id)->where('is_active', true)->count(),
        ];
    }
}
