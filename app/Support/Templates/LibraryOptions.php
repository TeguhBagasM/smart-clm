<?php

namespace App\Support\Templates;

use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractVariable;
use App\Models\Tenant;

/**
 * The choices the library screens offer, for one company: active categories,
 * active fields and active clauses. Only names and codes go out, never more.
 */
class LibraryOptions
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function categories(Tenant $tenant): array
    {
        $rows = [];

        foreach ($tenant->contractCategories()->options()->get(['id', 'name']) as $category) {
            $rows[] = ['value' => (string) $category->id, 'label' => (string) $category->name];
        }

        return $rows;
    }

    /**
     * @return list<array{value: int, label: string, code: string, type: string}>
     */
    public static function fields(Tenant $tenant): array
    {
        $rows = [];

        foreach (ContractVariable::query()->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('sort_order')->get() as $variable) {
            $rows[] = ['value' => (int) $variable->id, 'label' => $variable->name, 'code' => $variable->code, 'type' => $variable->value_type->label()];
        }

        return $rows;
    }

    /**
     * @return list<array{value: int, label: string, revision: int, category: string|null}>
     */
    public static function clauses(Tenant $tenant): array
    {
        $rows = [];

        foreach (ContractClause::query()->where('tenant_id', $tenant->id)->where('is_active', true)->with('category:id,name')->orderBy('title')->get() as $clause) {
            $rows[] = ['value' => (int) $clause->id, 'label' => $clause->title, 'revision' => (int) $clause->revision, 'category' => $clause->category?->name];
        }

        return $rows;
    }
}
