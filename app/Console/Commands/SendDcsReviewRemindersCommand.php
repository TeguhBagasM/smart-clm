<?php

namespace App\Console\Commands;

use App\Actions\Dcs\SendReviewReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dcs:review-reminders')]
#[Description('Remind owners of effective DCS documents that are due or overdue for periodic review')]
class SendDcsReviewRemindersCommand extends Command
{
    public function handle(SendReviewReminders $sendReviewReminders): int
    {
        $this->components->info("Sent {$sendReviewReminders->handle()} review reminder(s).");

        return self::SUCCESS;
    }
}
