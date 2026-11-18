<?php

namespace App\Http\Resources\Documents;

use App\Models\Dcs\DocumentVersion;
use App\Support\Dcs\ApiDate;
use App\Support\Dcs\Queue\TaskKind;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A version waiting for the person, with a one-line reason that depends on the
 * kind of task (the controller puts it in the request as `task_kind`). Loads
 * `document.type`, `document.department`, `document.owner` and `steps`, and
 * counts `signed_count` and `open_comments`.
 *
 * @mixin DocumentVersion
 */
class QueueItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $kind = $request->attributes->get('task_kind');
        $document = $this->document;

        return [
            'id' => $this->uuid,
            'document' => [
                'id' => $document->uuid,
                'number' => $document->document_number,
                'title' => $document->title,
                'type' => $document->type->code,
                'department' => $document->department->code,
                'owner' => $document->owner->name,
            ],
            'major' => $this->major,
            'minor' => $this->minor,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'waiting_since' => ApiDate::format($this->updated_at),
            'detail' => $kind instanceof TaskKind ? $this->detail($kind) : '',
            'urgent' => $kind === TaskKind::Review && $this->review_due_at?->isPast(),
        ];
    }

    private function detail(TaskKind $kind): string
    {
        return match ($kind) {
            TaskKind::Sign => $this->signDetail(),
            TaskKind::Revise => ((int) $this->getAttribute('open_comments')).' komentar belum selesai (putaran '.max(1, $this->review_round - 1).')',
            TaskKind::Release => $this->scheduled_effective_at === null ? 'Belum dijadwalkan' : 'Dijadwalkan berlaku '.$this->scheduled_effective_at->format('d/m/Y'),
            TaskKind::Read => 'Belum Anda konfirmasi sudah membaca',
            TaskKind::Review => $this->review_due_at === null ? '' : ($this->review_due_at->isPast() ? 'Terlambat sejak ' : 'Jatuh tempo ').$this->review_due_at->format('d/m/Y'),
        };
    }

    private function signDetail(): string
    {
        $signed = (int) $this->getAttribute('signed_count');
        $step = $this->steps->values()->get($signed);

        return 'Kolom "'.($step->label ?? 'Disetujui oleh').'" (giliran '.($signed + 1).' dari '.max(1, $this->steps->count()).')';
    }
}
