<?php

namespace App\Support\MasterData;

use App\Enums\MasterData\DocumentModule;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

/**
 * The server-side check behind the dependent dropdowns: every chosen id must be
 * one the company's options offer right now. Free text, an id of another
 * company, a hidden row, or a value that does not fit the earlier choice is
 * refused with a message that says how to fix it. All problems are reported at
 * once.
 *
 * @phpstan-import-type Option from DependentOptions
 */
class ClassificationGuard
{
    private const FIX = 'Pilih dari daftar. Kalau belum ada, minta admin menambahkannya di Master Data.';

    public function __construct(private DependentOptions $options) {}

    /**
     * External document: document type, platform, optional partner and category.
     *
     * @param  array{document_type_id?: int|null, platform_id?: int|null, partner_id?: int|null, category_id?: int|null}  $input
     * @return array{document_type_id: int, platform_id: int, partner_id: int|null, category_id: int|null}
     */
    public function external(Tenant $tenant, array $input): array
    {
        $errors = [];
        $categoryId = $this->check($errors, 'category_id', 'Kategori', $input['category_id'] ?? null, $this->options->categories($tenant, DocumentModule::External), false);
        $typeId = $this->check($errors, 'document_type_id', 'Jenis surat', $input['document_type_id'] ?? null, $this->options->documentTypes($tenant, DocumentModule::External, $categoryId), true);
        $platformId = $this->check($errors, 'platform_id', 'Platform', $input['platform_id'] ?? null, $this->options->platforms($tenant), true);
        $partnerId = $this->check($errors, 'partner_id', 'Mitra', $input['partner_id'] ?? null, $platformId === null ? [] : $this->options->partners($tenant, $platformId), false);

        $this->fail($errors);

        return ['document_type_id' => (int) $typeId, 'platform_id' => (int) $platformId, 'partner_id' => $partnerId, 'category_id' => $categoryId];
    }

    /**
     * Employee document: employment type, document type, optional department.
     *
     * @param  array{employment_type_id?: int|null, document_type_id?: int|null, department_id?: int|null}  $input
     * @return array{employment_type_id: int, document_type_id: int, department_id: int|null}
     */
    public function employee(Tenant $tenant, array $input): array
    {
        $errors = [];
        $employmentId = $this->check($errors, 'employment_type_id', 'Status hubungan kerja', $input['employment_type_id'] ?? null, $this->options->employmentTypes($tenant), true);
        $typeId = $this->check($errors, 'document_type_id', 'Jenis surat', $input['document_type_id'] ?? null, $this->options->documentTypes($tenant, DocumentModule::Employee), true);
        $departmentId = $this->check($errors, 'department_id', 'Departemen', $input['department_id'] ?? null, $this->options->departments($tenant), false);

        $this->fail($errors);

        return ['employment_type_id' => (int) $employmentId, 'document_type_id' => (int) $typeId, 'department_id' => $departmentId];
    }

    /**
     * @param  array<string, string>  $errors
     * @param  list<array{value: int, label: string}>  $options
     */
    private function check(array &$errors, string $field, string $label, mixed $value, array $options, bool $required): ?int
    {
        if ($value === null || $value === '') {
            if ($required) {
                $errors[$field] = "{$label} wajib dipilih. ".self::FIX;
            }

            return null;
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            foreach ($options as $option) {
                if ($option['value'] === (int) $value) {
                    return $option['value'];
                }
            }
        }

        $errors[$field] = "{$label} yang dipilih tidak ada di daftar. ".self::FIX;

        return null;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function fail(array $errors): void
    {
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
