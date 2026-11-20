<?php

namespace App\Support\Templates;

use App\Models\Templates\TemplateVersion;

/**
 * A version as a reader would see it, filled with sample values (never real
 * people or money). Read only. Samples: a field's default value, else one per
 * type; the contract module's system tokens have their own samples (config).
 */
class TemplatePreview
{
    /**
     * @return array<string, mixed>
     */
    public static function for(TemplateVersion $version): array
    {
        $version->loadMissing(['clauses', 'parties', 'variables']);
        $values = self::sampleValues($version);

        $texts = [$version->opening_paragraph, $version->closing_paragraph, $version->addendum_recital, ...$version->clauses->pluck('body')->all()];
        $used = [];

        foreach ($texts as $text) {
            $used = [...$used, ...Placeholders::codes((string) $text)];
        }

        return [
            'version_no' => $version->version_no,
            'opening' => self::paragraph($version->opening_paragraph, $values),
            'parties' => $version->parties->pluck('label')->values()->all(),
            'clauses' => $version->clauses->map(fn ($clause): array => [
                'title' => $clause->title, 'mandatory' => (bool) $clause->is_mandatory,
                'segments' => PlaceholderRenderer::segments($clause->body, $values),
            ])->values()->all(),
            'closing' => self::paragraph($version->closing_paragraph, $values),
            'addendum' => self::paragraph($version->addendum_recital, $values),
            'fields' => $version->variables->map(fn ($variable): array => [
                'code' => $variable->code, 'name' => $variable->name,
                'required' => (bool) $variable->pivot?->getAttribute('is_required'), 'sample' => $values[$variable->code] ?? '',
            ])->values()->all(),
            'missing' => array_values(array_unique(array_diff($used, array_keys($values)))),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function sampleValues(TemplateVersion $version): array
    {
        $values = config()->array('contract_templates.system_tokens');

        foreach ($version->variables as $variable) {
            $values[$variable->code] = $variable->default_value ?? config()->string('contract_templates.samples.'.$variable->value_type->value);
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     * @return list<array{text: string}|array{code: string, value: string, missing: bool}>|null
     */
    private static function paragraph(?string $text, array $values): ?array
    {
        return $text === null || $text === '' ? null : PlaceholderRenderer::segments($text, $values);
    }
}
