<?php

namespace App\Support\Templates;

use App\Enums\Templates\VariableType;
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
     * What a list page shows around its table: title, texts, columns, search placeholder,
     * the hide and show questions, and the usage notice.
     *
     * @return array<string, mixed>
     */
    public static function pageInfo(string $page, string $searchKey): array
    {
        $pages = config()->array('contract_templates.pages');
        $editor = config()->array('contract_templates.editor');

        return [
            ...$pages[$page],
            'search' => config()->string($searchKey),
            'columns' => config()->array("contract_templates.columns.{$page}"),
            'texts' => self::impact()['texts'] + ['save_failed' => config()->string('contract_templates.messages.save_failed')],
            'hints' => config()->array('contract_templates.hints'),
            'assist' => config()->array('contract_templates.assist'),
            'usage_notice' => null,
            'system_tokens' => array_keys(config()->array('contract_templates.system_tokens')),
            'editor' => $editor,
            'page' => $page,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function sources(): array
    {
        $rows = [];

        foreach (config()->array('contract_templates.source_fields') as $key => $label) {
            $rows[] = ['value' => (string) $key, 'label' => (string) $label];
        }

        return $rows;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function types(): array
    {
        $rows = [];

        foreach (VariableType::cases() as $type) {
            $rows[] = ['value' => $type->value, 'label' => $type->label()];
        }

        return $rows;
    }

    /**
     * The texts of the hide and show questions, with the usage notice.
     *
     * @return array{texts: array<string, string>, usage_notice: string|null}
     */
    public static function impact(): array
    {
        $messages = config()->array('contract_templates.messages');

        return [
            'texts' => array_intersect_key($messages, array_flip(['impact_hide_title', 'impact_used', 'impact_unused', 'impact_show_title', 'impact_show'])),
            'usage_notice' => null,
        ];
    }

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
     * @return list<array{value: int, label: string, revision: int, category: string|null, body: string}>
     */
    public static function clauses(Tenant $tenant): array
    {
        $rows = [];

        foreach (ContractClause::query()->where('tenant_id', $tenant->id)->where('is_active', true)->with('category:id,name')->orderBy('title')->get() as $clause) {
            $rows[] = ['value' => (int) $clause->id, 'label' => $clause->title, 'revision' => (int) $clause->revision, 'category' => $clause->category?->name, 'body' => $clause->body];
        }

        return $rows;
    }
}
