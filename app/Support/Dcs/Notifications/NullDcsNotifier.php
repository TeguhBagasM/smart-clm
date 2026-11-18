<?php

namespace App\Support\Dcs\Notifications;

use App\Models\Dcs\Document;
use Illuminate\Support\Collection;

/**
 * Placeholder until the notification channel for DCS is decided: sends nothing.
 */
class NullDcsNotifier implements DcsNotifier
{
    public function notify(Collection $recipients, Document $document, string $title, string $message, string $type = 'info'): void {}
}
