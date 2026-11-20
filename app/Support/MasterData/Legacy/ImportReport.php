<?php

namespace App\Support\MasterData\Legacy;

/**
 * What one tenant's import did or would do. Holds counts, list names and
 * Node ids only: never names of people, salaries or contact details. It is
 * "clean" when no row failed to map and no value used in Node is missing from
 * the lists.
 */
class ImportReport
{
    /** @var array<string, array<string, int>> list => outcome (created|adopted|unchanged|skipped) => rows */
    public array $counts = [];

    /** @var array<string, array<string, int>> where the value was seen => value => rows using it */
    public array $unknown = [];

    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> */
    public array $warnings = [];

    public function count(string $list, string $outcome, int $by = 1): void
    {
        $this->counts[$list][$outcome] = ($this->counts[$list][$outcome] ?? 0) + $by;
    }

    public function unknown(string $where, string $value, int $rows = 1): void
    {
        $this->unknown[$where][$value] = ($this->unknown[$where][$value] ?? 0) + $rows;
    }

    public function error(string $ref, string $reason): void
    {
        $this->errors[] = "{$ref}: {$reason}";
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function isClean(): bool
    {
        return $this->errors === [] && $this->unknown === [];
    }

    public function total(string $outcome): int
    {
        return array_sum(array_column($this->counts, $outcome));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['counts' => $this->counts, 'unknown' => $this->unknown, 'errors' => $this->errors, 'warnings' => $this->warnings];
    }
}
