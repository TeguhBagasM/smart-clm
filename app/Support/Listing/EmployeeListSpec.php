<?php

namespace App\Support\Listing;

use Illuminate\Database\Eloquent\Builder;

/**
 * The employees of a company: search by name, number or position; filter by
 * status, department and employment type (the page passes the company's own
 * options); order by name, start date or creation. The query is the company's
 * relation, so other companies never appear.
 */
class EmployeeListSpec extends ListSpec
{
    /**
     * @param  array<int, array{value: string, label: string}>  $departmentOptions
     * @param  array<int, array{value: string, label: string}>  $employmentTypeOptions
     */
    public function __construct(private array $departmentOptions, private array $employmentTypeOptions) {}

    public function defaultDirection(): string
    {
        return 'asc';
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function search(Builder $query, string $term): void
    {
        $pattern = $this->like(mb_strtolower($term));

        $query->where(fn (Builder $inner) => $inner
            ->whereRaw("lower(employees.name) like ? escape '\\'", [$pattern])
            ->orWhereRaw("lower(employees.employee_number) like ? escape '\\'", [$pattern])
            ->orWhereRaw("lower(employees.position) like ? escape '\\'", [$pattern]));
    }

    /**
     * @return array<int, Sort>
     */
    protected function sorts(): array
    {
        return [
            Sort::column('name', 'Nama', 'employees.name'),
            Sort::column('start_date', 'Tanggal mulai', 'employees.start_date'),
            Sort::column('created_at', 'Tanggal dibuat', 'employees.created_at'),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        return [
            Filter::select('status', 'Status', [['value' => 'active', 'label' => 'Aktif'], ['value' => 'inactive', 'label' => 'Disembunyikan']],
                fn (Builder $query, string $status) => $query->where('employees.is_active', $status === 'active'), primary: true),
            Filter::select('department', 'Departemen', $this->departmentOptions, fn (Builder $query, string $id) => $query->where('employees.department_id', (int) $id)),
            Filter::select('employment_type', 'Status hubungan kerja', $this->employmentTypeOptions, fn (Builder $query, string $id) => $query->where('employees.employment_type_id', (int) $id)),
        ];
    }
}
