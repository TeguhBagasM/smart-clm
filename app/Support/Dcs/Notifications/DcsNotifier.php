<?php

namespace App\Support\Dcs\Notifications;

use App\Models\Dcs\Document;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Tells people that something needs their attention on a document. The channel
 * (the Node app's bell and push, until a Laravel one exists) is decided behind
 * this interface; actions call it after their transaction has committed, so an
 * implementation reports its own failures and never throws: failing to notify
 * must not undo the change.
 */
interface DcsNotifier
{
    /**
     * @param  Collection<int, User>  $recipients
     * @param  'info'|'warning'|'success'|'danger'  $type
     */
    public function notify(Collection $recipients, Document $document, string $title, string $message, string $type = 'info'): void;
}
