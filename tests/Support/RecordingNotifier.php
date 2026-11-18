<?php

namespace Tests\Support;

use App\Models\Dcs\Document;
use App\Support\Dcs\Notifications\DcsNotifier;
use Illuminate\Support\Collection;

class RecordingNotifier implements DcsNotifier
{
    /** @var list<array{recipients: list<int>, document: int, title: string, message: string, type: string}> */
    public array $sent = [];

    public function notify(Collection $recipients, Document $document, string $title, string $message, string $type = 'info'): void
    {
        $this->sent[] = ['recipients' => $recipients->pluck('id')->sort()->values()->all(), 'document' => $document->id, 'title' => $title, 'message' => $message, 'type' => $type];
    }
}
