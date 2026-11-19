<?php

namespace App\Http\Requests\Dcs\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The shape of a draft's content, shared by the compose request (multipart, JSON
 * parts as strings) and the wizard's draft request (plain JSON).
 */
trait DraftContent
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function contentRules(): array
    {
        return [
            'purpose' => ['nullable', 'string'],
            'scope' => ['nullable', 'string'],
            'languageMode' => ['nullable', 'in:single,multi'],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['array'],
            'sections.*.heading' => ['nullable', 'string', 'max:500'],
            'sections.*.content' => ['nullable', 'string'],
            'sections.*.table' => ['nullable', 'array'],
            'sections.*.table.columns' => ['required_with:sections.*.table', 'array'],
            'sections.*.table.columns.*' => ['nullable', 'string'],
            'sections.*.table.rows' => ['required_with:sections.*.table', 'array'],
            'sections.*.table.rows.*' => ['array'],
            'sections.*.table.rows.*.*' => ['nullable', 'string'],
            'flowMode' => ['nullable', 'in:none,builder,image'],
            'flowDisplayStyle' => ['nullable', 'in:diagram,table'],
            'flowLanes' => ['nullable', 'array'],
            'flowLanes.*' => ['string', 'max:100'],
            'flowSteps' => ['nullable', 'array'],
            'flowSteps.*' => ['array'],
            'flowSteps.*.order' => ['nullable', 'integer'],
            'flowSteps.*.type' => ['required', 'in:start,process,decision,end,document,data,predefined,connector'],
            'flowSteps.*.text' => ['nullable', 'string'],
            'flowSteps.*.id' => ['nullable', 'string', 'max:64'],
            'flowSteps.*.actor' => ['nullable', 'string', 'max:100'],
            'flowSteps.*.nextId' => ['nullable', 'string', 'max:64'],
            'flowSteps.*.yesId' => ['nullable', 'string', 'max:64'],
            'flowSteps.*.noId' => ['nullable', 'string', 'max:64'],
            'flowSteps.*.row' => ['nullable', 'integer', 'min:0', 'max:199'],
            'flowSteps.*.ports' => ['nullable', 'array:next,yes,no'],
            'flowSteps.*.ports.*' => ['nullable', 'array:exit,entry'],
            'flowSteps.*.ports.*.exit' => ['nullable', 'in:top,right,bottom,left'],
            'flowSteps.*.ports.*.entry' => ['nullable', 'in:top,right,bottom,left'],
            'appendix' => ['nullable', 'array'],
            'appendix.*.label' => ['required', 'string', 'max:300'],
            'appendix.*.url' => ['required', 'string'],
            'clauseIds' => ['nullable', 'array'],
            'clauseIds.*' => ['uuid'],
            'signatureAssignments' => ['nullable', 'array'],
            'signatureAssignments.*' => ['nullable', 'string', 'max:64'],
            'extraReviewers' => ['nullable', 'array'],
            'extraReviewers.*.label' => ['nullable', 'string', 'max:100'],
            'extraReviewers.*.role' => ['nullable', 'string', 'max:60'],
            'extraReviewers.*.assignedUserId' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * The content parts present in the request, normalised for SaveDraftContent.
     * Absent parts are absent from the result (they are left alone).
     *
     * @return array<string, mixed>
     */
    protected function contentData(): array
    {
        $data = [];

        foreach (['purpose', 'scope'] as $key) {
            if ($this->has($key)) {
                $data[$key] = $this->input($key) === null ? null : (string) $this->input($key);
            }
        }

        foreach (['languageMode', 'flowMode', 'flowDisplayStyle'] as $key) {
            if ($this->filled($key)) {
                $data[$key] = (string) $this->input($key);
            }
        }

        if (is_array($this->input('sections'))) {
            $data['sections'] = array_map(fn (array $section): array => [
                'heading' => (string) ($section['heading'] ?? ''),
                'content' => (string) ($section['content'] ?? ''),
                ...(isset($section['table']['columns']) && $section['table']['columns'] !== [] ? ['table' => [
                    'columns' => array_map('strval', array_values($section['table']['columns'])),
                    'rows' => array_map(fn (array $row): array => array_map('strval', array_values($row)), array_values($section['table']['rows'] ?? [])),
                ]] : []),
            ], array_values($this->input('sections')));
        }

        if (is_array($this->input('flowLanes'))) {
            $data['flowLanes'] = array_values(array_map('strval', $this->input('flowLanes')));
        }

        if (is_array($this->input('flowSteps'))) {
            $data['flowSteps'] = array_values($this->input('flowSteps'));
        }

        if (is_array($this->input('appendix'))) {
            $data['appendix'] = array_map(fn (array $item): array => ['label' => (string) $item['label'], 'url' => (string) $item['url']], array_values($this->input('appendix')));
        }

        if (is_array($this->input('clauseIds'))) {
            $data['clauseIds'] = array_values(array_map('strval', $this->input('clauseIds')));
        }

        if (is_array($this->input('signatureAssignments'))) {
            $data['signatureAssignments'] = array_map(fn (mixed $id): ?string => $id === null || $id === '' ? null : (string) $id, array_values($this->input('signatureAssignments')));
        }

        if (is_array($this->input('extraReviewers'))) {
            $data['extraReviewers'] = array_map(fn (array $reviewer): array => [
                'label' => (string) ($reviewer['label'] ?? ''),
                'role' => isset($reviewer['role']) && $reviewer['role'] !== '' ? (string) $reviewer['role'] : null,
                'assignedUserId' => isset($reviewer['assignedUserId']) && $reviewer['assignedUserId'] !== '' ? (string) $reviewer['assignedUserId'] : null,
            ], array_values($this->input('extraReviewers')));
        }

        return $data;
    }
}
