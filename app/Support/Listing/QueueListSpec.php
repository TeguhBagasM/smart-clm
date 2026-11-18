<?php

namespace App\Support\Listing;

use App\Models\Dcs\Department;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Tugas saya": the versions waiting for the person, one kind of task at a
 * time. Oldest waiting first by default, so what has waited longest comes first.
 */
class QueueListSpec extends ListSpec
{
    public function __construct(private User $viewer) {}

    public function defaultDirection(): string
    {
        return 'asc';
    }

    protected function defaultSort(): string
    {
        return 'waiting';
    }

    protected function search(Builder $query, string $term): void
    {
        $pattern = $this->like(mb_strtolower($term));

        $query->whereHas('document', fn (Builder $document) => $document
            ->whereRaw("lower(dcs_documents.document_number) like ? escape '\\'", [$pattern])
            ->orWhereRaw("lower(dcs_documents.title) like ? escape '\\'", [$pattern]));
    }

    /**
     * @return array<int, Sort>
     */
    protected function sorts(): array
    {
        return [
            Sort::column('waiting', 'Menunggu paling lama', 'dcs_document_versions.updated_at'),
            new Sort('number', 'Nomor dokumen', fn (Builder $query, string $direction) => $query->orderBy(
                Document::query()->select('document_number')->whereColumn('dcs_documents.id', 'dcs_document_versions.document_id'), $direction === 'asc' ? 'asc' : 'desc')),
            new Sort('title', 'Judul', fn (Builder $query, string $direction) => $query->orderBy(
                Document::query()->select('title')->whereColumn('dcs_documents.id', 'dcs_document_versions.document_id'), $direction === 'asc' ? 'asc' : 'desc')),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        $types = DocumentType::query()->where('tenant_id', $this->viewer->tenant_id)->orderBy('code')->get(['code', 'name'])->map(fn (DocumentType $type): array => ['value' => $type->code, 'label' => "{$type->code} — {$type->name}"])->all();
        $departments = Department::query()->where('tenant_id', $this->viewer->tenant_id)->orderBy('code')->pluck('code')->map(fn (string $code): array => ['value' => $code, 'label' => $code])->all();

        return [
            Filter::multi('type', 'Jenis dokumen', $types, fn (Builder $query, array $codes) => $query->whereHas('document.type', fn (Builder $type) => $type->whereIn('code', $codes)), primary: true),
            Filter::multi('department', 'Departemen', $departments, fn (Builder $query, array $codes) => $query->whereHas('document.department', fn (Builder $department) => $department->whereIn('code', $codes)), primary: true),
            Filter::select('waiting', 'Lama menunggu', [
                ['value' => '3', 'label' => 'Lebih dari 3 hari'],
                ['value' => '7', 'label' => 'Lebih dari 7 hari'],
                ['value' => '14', 'label' => 'Lebih dari 14 hari'],
            ], fn (Builder $query, string $days) => $query->where('dcs_document_versions.updated_at', '<', now()->subDays((int) $days)), primary: true),
        ];
    }
}
