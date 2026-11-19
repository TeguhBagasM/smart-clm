<?php

namespace App\Http\Requests\Dcs;

use App\Enums\Dcs\BodyStyle;
use App\Enums\Dcs\HeaderStyle;
use App\Enums\Dcs\LayoutStyle;
use App\Enums\Dcs\SignerFillMode;
use App\Models\Dcs\HeaderAsset;
use App\Support\Dcs\Header\HeaderLayout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared input rules of creating and updating a document type: its attributes
 * and its template (sections, signature columns, lanes, default clauses).
 */
abstract class DocTypeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function templateRules(): array
    {
        return [
            'description' => ['nullable', 'string'],
            'layoutStyle' => ['nullable', Rule::enum(LayoutStyle::class)],
            'headerStyle' => ['nullable', Rule::enum(HeaderStyle::class)],
            'bodyStyle' => ['nullable', Rule::enum(BodyStyle::class)],
            'letterhead' => ['nullable', 'boolean'],
            'documentLevel' => ['nullable', 'string', 'max:100'],
            'processOwner' => ['nullable', 'string', 'max:150'],
            ...HeaderLayout::rules('headerLayout'),
            'defaultSections' => ['nullable', 'array'],
            'defaultSections.*.heading' => ['required', 'string', 'max:200'],
            'defaultSections.*.content' => ['nullable', 'string'],
            'signatureGroups' => ['nullable', 'array'],
            'signatureGroups.*.heading' => ['nullable', 'string', 'max:100'],
            'signatureGroups.*.columns' => ['required', 'array', 'min:1'],
            'signatureGroups.*.columns.*.label' => ['required', 'string', 'max:100'],
            'signatureGroups.*.columns.*.role' => ['nullable', 'string', 'max:60'],
            'signatureGroups.*.columns.*.roleName' => ['nullable', 'string', 'max:100'],
            'signatureGroups.*.columns.*.fillMode' => ['nullable', Rule::enum(SignerFillMode::class)],
            'signatureGroups.*.columns.*.defaultUserId' => ['nullable', 'string', 'max:100'],
            'signatureGroups.*.columns.*.allowCreator' => ['nullable', 'boolean'],
            'signatureColumns' => ['nullable', 'array'],
            'signatureColumns.*.label' => ['required', 'string', 'max:100'],
            'signatureColumns.*.role' => ['nullable', 'string', 'max:60'],
            'signatureColumns.*.roleName' => ['nullable', 'string', 'max:100'],
            'defaultLanes' => ['nullable', 'array'],
            'defaultLanes.*' => ['string', 'max:100'],
            'defaultClauseIds' => ['nullable', 'array'],
            'defaultClauseIds.*' => ['uuid'],
        ];
    }

    /**
     * What a letterhead rule cannot say: placeholders and widths, and that a custom
     * logo is an image of the user's own company.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $layout = $this->input('headerLayout');

            if (! is_array($layout) || $validator->errors()->hasAny(['headerLayout', 'headerLayout.*'])) {
                return;
            }

            foreach (HeaderLayout::problems($layout) as $key => $message) {
                $validator->errors()->add("headerLayout.$key", $message);
            }

            $logo = (array) ($layout['logo'] ?? []);

            if (($logo['enabled'] ?? false) && ($logo['source'] ?? '') === 'custom') {
                $owned = filled($logo['assetId'] ?? null)
                    && HeaderAsset::query()->where('tenant_id', $this->user()->tenant_id)->where('uuid', $logo['assetId'])->exists();

                if (! $owned) {
                    $validator->errors()->add('headerLayout.logo.assetId', 'Unggah logo khusus, atau pilih logo perusahaan.');
                }
            }
        }];
    }

    /**
     * The attributes shared by both requests, with enums and booleans typed.
     *
     * @return array{
     *     description: string|null, layoutStyle: LayoutStyle|null, headerStyle: HeaderStyle|null,
     *     letterhead: bool|null, documentLevel: string|null, processOwner: string|null, headerLayout: array<string, mixed>|null, bodyStyle: BodyStyle|null, template: array<string, mixed>
     * }
     */
    protected function sharedData(): array
    {
        return [
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'layoutStyle' => $this->enum('layoutStyle', LayoutStyle::class),
            'headerStyle' => $this->enum('headerStyle', HeaderStyle::class),
            'letterhead' => $this->has('letterhead') && $this->input('letterhead') !== null ? $this->boolean('letterhead') : null,
            'documentLevel' => $this->filled('documentLevel') ? $this->string('documentLevel')->toString() : null,
            'processOwner' => $this->filled('processOwner') ? $this->string('processOwner')->toString() : null,
            'bodyStyle' => $this->enum('bodyStyle', BodyStyle::class),
            'headerLayout' => is_array($this->input('headerLayout')) ? HeaderLayout::normalize($this->input('headerLayout')) : null,
            'template' => $this->template(),
        ];
    }

    /**
     * @return array{sections: list<array{heading: string, content: string|null}>|null, groups: list<array<string, mixed>>|null, lanes: list<string>|null, defaultClauseIds: list<string>|null}
     */
    private function template(): array
    {
        return [
            'sections' => $this->has('defaultSections') && is_array($this->input('defaultSections'))
                ? array_map(fn (array $section): array => ['heading' => $section['heading'], 'content' => $section['content'] ?? ''], array_values($this->input('defaultSections')))
                : null,
            'groups' => $this->groups(),
            'lanes' => is_array($this->input('defaultLanes')) ? array_values(array_map('strval', $this->input('defaultLanes'))) : null,
            'defaultClauseIds' => is_array($this->input('defaultClauseIds')) ? array_values(array_map('strval', $this->input('defaultClauseIds'))) : null,
        ];
    }

    /**
     * Signature groups, or the legacy flat column list wrapped in one group.
     *
     * @return list<array<string, mixed>>|null
     */
    private function groups(): ?array
    {
        $normalize = fn (array $column): array => [
            'label' => $column['label'],
            'role' => $column['role'] ?? null,
            'roleName' => $column['roleName'] ?? null,
            'fillMode' => $column['fillMode'] ?? 'legacy',
            'defaultUserId' => isset($column['defaultUserId']) && $column['defaultUserId'] !== '' ? (string) $column['defaultUserId'] : null,
            'allowCreator' => (bool) ($column['allowCreator'] ?? false),
        ];

        if (is_array($this->input('signatureGroups'))) {
            return array_map(fn (array $group): array => [
                'heading' => $group['heading'] ?? null,
                'columns' => array_map($normalize, array_values($group['columns'])),
            ], array_values($this->input('signatureGroups')));
        }

        if (is_array($this->input('signatureColumns')) && $this->input('signatureColumns') !== []) {
            return [['heading' => null, 'columns' => array_map($normalize, array_values($this->input('signatureColumns')))]];
        }

        return null;
    }
}
