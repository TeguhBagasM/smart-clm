<?php

namespace App\Support\Dcs\Legacy;

/**
 * What the import of one Node tenant found and did: how many rows of each kind
 * the source held, how many were imported or skipped, and every problem seen.
 * Any error means the tenant was not imported.
 */
class ImportReport
{
    /** @var array<string, array{source: int, imported: int, skipped: int}> */
    public array $counts = [];

    /** @var list<array{level: 'warning'|'error', message: string}> */
    public array $issues = [];

    public bool $committed = false;

    public function __construct(public readonly string $legacyTenantId) {}

    public function source(string $entity, int $count): void
    {
        $this->counts[$entity] = ['source' => $count, 'imported' => 0, 'skipped' => 0];
    }

    /**
     * Counts a row that is only known once it has been handled (a file found
     * while exporting), as both found and done.
     */
    public function found(string $entity): void
    {
        $this->counts[$entity] ??= ['source' => 0, 'imported' => 0, 'skipped' => 0];
        $this->counts[$entity]['source']++;
        $this->counts[$entity]['imported']++;
    }

    public function imported(string $entity): void
    {
        $this->counts[$entity]['imported']++;
    }

    public function skipped(string $entity): void
    {
        $this->counts[$entity]['skipped']++;
    }

    public function warn(string $message): void
    {
        $this->issues[] = ['level' => 'warning', 'message' => $message];
    }

    public function error(string $message): void
    {
        $this->issues[] = ['level' => 'error', 'message' => $message];
    }

    public function hasErrors(): bool
    {
        return collect($this->issues)->contains(fn (array $issue): bool => $issue['level'] === 'error');
    }

    /**
     * Rows that were neither imported nor knowingly skipped are errors: the
     * numbers on both sides must add up.
     */
    public function reconcile(): void
    {
        foreach ($this->counts as $entity => $count) {
            if ($count['source'] !== $count['imported'] + $count['skipped']) {
                $this->error("{$entity}: source has {$count['source']}, imported {$count['imported']} and skipped {$count['skipped']}.");
            }
        }
    }
}
