<?php

namespace App\Support\Listing;

use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\Department;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\DocumentVersion;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * The document list: what can be searched, filtered and sorted.
 *
 * "Status" is the status of the newest version (what the document is up to
 * now); "Berlaku" and the review and effective-date filters look at the version
 * in force. Options are limited to what the viewer can see.
 */
class DocumentListSpec extends ListSpec
{
    public function __construct(private User $viewer) {}

    protected function defaultSort(): string
    {
        return 'updated_at';
    }

    protected function search(Builder $query, string $term): void
    {
        $pattern = $this->like(mb_strtolower($term));

        $query->where(fn (Builder $inner) => $inner
            ->whereRaw("(lower(dcs_documents.document_number) like ? escape '\\' or lower(coalesce(dcs_documents.base_number, '')) like ? escape '\\')", [$pattern, $pattern])
            ->orWhereRaw("lower(dcs_documents.title) like ? escape '\\'", [$pattern]));
    }

    /**
     * @return array<int, Sort>
     */
    protected function sorts(): array
    {
        return [
            Sort::column('updated_at', 'Terakhir diubah', 'dcs_documents.updated_at'),
            Sort::column('number', 'Nomor dokumen', 'dcs_documents.document_number'),
            Sort::column('title', 'Judul', 'dcs_documents.title'),
            Sort::column('created_at', 'Tanggal dibuat', 'dcs_documents.created_at'),
            new Sort('effective_at', 'Tanggal berlaku', fn (Builder $query, string $direction) => $this->orderByCurrentVersion($query, 'effective_at', $direction)),
            new Sort('review_due_at', 'Jatuh tempo tinjau ulang', fn (Builder $query, string $direction) => $this->orderByCurrentVersion($query, 'review_due_at', $direction)),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        return array_values(array_filter([
            $this->viewer->isSuperAdmin() ? $this->tenantFilter() : null,
            Filter::multi('status', 'Status', $this->statusOptions(), fn (Builder $query, array $values) => $query->whereHas('latestVersion', fn (Builder $version) => $version->whereIn('status', $values)), primary: true),
            Filter::multi('type', 'Jenis dokumen', $this->typeOptions(), fn (Builder $query, array $codes) => $query->whereHas('type', fn (Builder $type) => $type->whereIn('code', $codes)), primary: true),
            Filter::multi('department', 'Departemen', $this->departmentOptions(), fn (Builder $query, array $codes) => $query->whereHas('department', fn (Builder $department) => $department->whereIn('code', $codes)), primary: true),
            Filter::select('owner', 'Pemilik', $this->ownerOptions(), fn (Builder $query, string $id) => $query->where('dcs_documents.owner_user_id', (int) $id)),
            Filter::boolean('in_force', 'Sedang berlaku', fn (Builder $query, bool $yes) => $yes ? $query->whereNotNull('dcs_documents.current_version_id') : $query->whereNull('dcs_documents.current_version_id')),
            Filter::select('review', 'Tinjau ulang', $this->reviewOptions(), fn (Builder $query, string $value) => $this->reviewFilter($query, $value)),
            Filter::dateRange('effective', 'Tanggal berlaku', fn (Builder $query, ?string $from, ?string $to) => $query->whereHas('currentVersion', fn (Builder $version) => $this->between($version, 'effective_at', $from, $to))),
            Filter::dateRange('created', 'Tanggal dibuat', fn (Builder $query, ?string $from, ?string $to) => $this->between($query, 'dcs_documents.created_at', $from, $to)),
            Filter::boolean('has_file', 'Sudah ada berkas PDF', fn (Builder $query, bool $yes) => $yes
                ? $query->whereHas('latestVersion', fn (Builder $version) => $version->whereHas('renditions', fn (Builder $file) => $file->whereIn('kind', RenditionKind::mainValues())))
                : $query->whereDoesntHave('latestVersion', fn (Builder $version) => $version->whereHas('renditions', fn (Builder $file) => $file->whereIn('kind', RenditionKind::mainValues())))),
            Filter::boolean('open_comments', 'Ada komentar review terbuka', fn (Builder $query, bool $yes) => $this->whenLatest($query, $yes, fn (Builder $version) => $version->whereHas('reviewComments', fn (Builder $comment) => $comment->where('status', 'open')))),
            Filter::boolean('external_review', 'Link review eksternal aktif', fn (Builder $query, bool $yes) => $this->whenLatest($query, $yes, fn (Builder $version) => $version->whereHas('externalReviews', fn (Builder $review) => $review->whereNull('disabled_at')->where(fn (Builder $live) => $live->whereNull('expires_at')->orWhere('expires_at', '>', now()))))),
            Filter::boolean('scheduled', 'Rilis terjadwal', fn (Builder $query, bool $yes) => $yes
                ? $query->whereHas('versions', fn (Builder $version) => $version->where('status', VersionStatus::Approved->value)->whereNotNull('scheduled_effective_at'))
                : $query->whereDoesntHave('versions', fn (Builder $version) => $version->where('status', VersionStatus::Approved->value)->whereNotNull('scheduled_effective_at'))),
        ]));
    }

    private function tenantFilter(): Filter
    {
        $options = Tenant::query()->orderBy('name')->get(['id', 'name'])->map(fn (Tenant $tenant): array => ['value' => (string) $tenant->id, 'label' => $tenant->name])->all();

        return Filter::select('tenant', 'Perusahaan', $options, fn (Builder $query, string $id) => $query->where('dcs_documents.tenant_id', (int) $id), primary: true);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(fn (VersionStatus $status): array => ['value' => $status->value, 'label' => $status->label()], VersionStatus::cases());
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return DocumentType::query()->when(! $this->viewer->isSuperAdmin(), fn (Builder $query) => $query->where('tenant_id', $this->viewer->tenant_id))
            ->orderBy('code')->get(['code', 'name'])->unique('code')->map(fn (DocumentType $type): array => ['value' => $type->code, 'label' => "{$type->code} — {$type->name}"])->values()->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function departmentOptions(): array
    {
        return Department::query()->when(! $this->viewer->isSuperAdmin(), fn (Builder $query) => $query->where('tenant_id', $this->viewer->tenant_id))
            ->orderBy('code')->pluck('code')->unique()->map(fn (string $code): array => ['value' => $code, 'label' => $code])->values()->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function ownerOptions(): array
    {
        return User::query()->whereIn('id', Document::query()->visibleTo($this->viewer)->select('owner_user_id'))->orderBy('name')->limit(300)
            ->get(['id', 'name'])->map(fn (User $user): array => ['value' => (string) $user->id, 'label' => $user->name])->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function reviewOptions(): array
    {
        return [
            ['value' => 'overdue', 'label' => 'Sudah lewat jatuh tempo'],
            ['value' => 'next_30', 'label' => 'Jatuh tempo dalam 30 hari'],
            ['value' => 'next_90', 'label' => 'Jatuh tempo dalam 90 hari'],
            ['value' => 'not_set', 'label' => 'Belum dijadwalkan'],
        ];
    }

    /**
     * @param  Builder<*>  $query
     */
    private function reviewFilter(Builder $query, string $value): void
    {
        $query->whereHas('currentVersion', function (Builder $version) use ($value): void {
            match ($value) {
                'overdue' => $version->where('review_due_at', '<', now()),
                'next_30' => $version->whereBetween('review_due_at', [now(), now()->addDays(30)]),
                'next_90' => $version->whereBetween('review_due_at', [now(), now()->addDays(90)]),
                default => $version->whereNull('review_due_at'),
            };
        });
    }

    /**
     * @param  Builder<*>  $query
     * @param  Closure(Builder<*>): mixed  $condition
     */
    private function whenLatest(Builder $query, bool $yes, Closure $condition): void
    {
        $yes ? $query->whereHas('latestVersion', $condition) : $query->whereDoesntHave('latestVersion', $condition);
    }

    /**
     * @param  Builder<*>  $query
     */
    private function between(Builder $query, string $column, ?string $from, ?string $to): void
    {
        $from === null || $query->where($column, '>=', CarbonImmutable::parse($from)->startOfDay());
        $to === null || $query->where($column, '<=', CarbonImmutable::parse($to)->endOfDay());
    }

    /**
     * Orders by a column of the version in force; documents without one go last in either direction.
     *
     * @param  Builder<*>  $query
     */
    private function orderByCurrentVersion(Builder $query, string $column, string $direction): void
    {
        $sub = DocumentVersion::query()->select($column)->whereColumn('dcs_document_versions.id', 'dcs_documents.current_version_id')->limit(1);

        $missing = match ($column) {
            'effective_at' => 'coalesce((select case when v.effective_at is null then 1 else 0 end from dcs_document_versions v where v.id = dcs_documents.current_version_id), 1)',
            default => 'coalesce((select case when v.review_due_at is null then 1 else 0 end from dcs_document_versions v where v.id = dcs_documents.current_version_id), 1)',
        };

        $query->orderByRaw($missing)->orderBy($sub, $direction === 'asc' ? 'asc' : 'desc');
    }
}
