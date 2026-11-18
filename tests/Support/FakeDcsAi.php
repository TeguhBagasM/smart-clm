<?php

namespace Tests\Support;

use App\Support\Dcs\Ai\AiException;
use App\Support\Dcs\Ai\DcsAi;

/**
 * Stands in for the Node AI endpoints: translates by prefixing "EN: " and
 * returns a canned metadata answer, remembering what it was asked.
 */
class FakeDcsAi implements DcsAi
{
    /** @var list<array<string, mixed>> */
    public array $translated = [];

    /** @var list<array{mimeType: string, size: int, docTypeCodes: list<string>}> */
    public array $extracted = [];

    public ?AiException $fail = null;

    /** @var array<string, mixed> */
    public array $metadata = ['title' => 'Prosedur Cuti', 'docTypeCode' => 'SOP', 'department' => 'HRD'];

    /** @var array<string, mixed>|null */
    public ?array $translationOverride = null;

    public function translate(array $source): array
    {
        if ($this->fail !== null) {
            throw $this->fail;
        }

        $this->translated[] = $source;

        return $this->translationOverride ?? [
            'title' => 'EN: '.$source['title'],
            'purpose' => 'EN: '.$source['purpose'],
            'scope' => 'EN: '.$source['scope'],
            'sections' => array_map(fn (array $section): array => ['heading' => 'EN: '.$section['heading'], 'content' => 'EN: '.$section['content']], $source['sections']),
        ];
    }

    public function extractMetadata(string $bytes, string $mimeType, array $docTypeCodes): array
    {
        if ($this->fail !== null) {
            throw $this->fail;
        }

        $this->extracted[] = ['mimeType' => $mimeType, 'size' => strlen($bytes), 'docTypeCodes' => $docTypeCodes];

        return $this->metadata;
    }
}
