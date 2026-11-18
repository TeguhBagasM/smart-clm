<?php

namespace App\Support\Listing;

use App\Enums\Dcs\ActorType;
use App\Enums\Dcs\DocumentEventType;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The timeline of one document: search by who did it, filter by kind of event,
 * version, kind of actor, who and when, newest first by default.
 */
class EventListSpec extends ListSpec
{
    public function __construct(private Document $document) {}

    protected function defaultSort(): string
    {
        return 'occurred_at';
    }

    protected function search(Builder $query, string $term): void
    {
        $query->whereRaw("lower(dcs_document_events.actor_name) like ? escape '\\'", [$this->like(mb_strtolower($term))]);
    }

    /**
     * @return array<int, Sort>
     */
    protected function sorts(): array
    {
        return [
            Sort::column('occurred_at', 'Waktu kejadian', 'dcs_document_events.occurred_at'),
            Sort::column('type', 'Jenis kejadian', 'dcs_document_events.event_type'),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        return [
            Filter::multi('type', 'Jenis kejadian', array_map(fn (DocumentEventType $type): array => ['value' => $type->value, 'label' => $type->label()], DocumentEventType::cases()),
                fn (Builder $query, array $types) => $query->whereIn('dcs_document_events.event_type', $types), primary: true),
            Filter::select('version', 'Versi', $this->versionOptions(), fn (Builder $query, string $uuid) => $query->whereHas('version', fn (Builder $version) => $version->where('uuid', $uuid)), primary: true),
            Filter::select('actor_type', 'Pelaku', [['value' => ActorType::User->value, 'label' => 'Pengguna'], ['value' => ActorType::Guest->value, 'label' => 'Tamu eksternal'], ['value' => ActorType::System->value, 'label' => 'Sistem']],
                fn (Builder $query, string $type) => $query->where('dcs_document_events.actor_type', $type)),
            Filter::select('actor', 'Nama pelaku', $this->actorOptions(), fn (Builder $query, string $name) => $query->where('dcs_document_events.actor_name', $name)),
            Filter::dateRange('occurred', 'Tanggal kejadian', function (Builder $query, ?string $from, ?string $to) {
                $from === null || $query->where('dcs_document_events.occurred_at', '>=', CarbonImmutable::parse($from)->startOfDay());
                $to === null || $query->where('dcs_document_events.occurred_at', '<=', CarbonImmutable::parse($to)->endOfDay());
            }),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function versionOptions(): array
    {
        return $this->document->versions()->get()->map(fn ($version): array => ['value' => $version->uuid, 'label' => "Terbitan {$version->major} Revisi {$version->minor}"])->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function actorOptions(): array
    {
        return DocumentEvent::query()->where('document_id', $this->document->id)->whereNotNull('actor_name')->distinct()->orderBy('actor_name')->limit(200)
            ->pluck('actor_name')->map(fn (string $name): array => ['value' => $name, 'label' => $name])->all();
    }
}
