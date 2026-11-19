<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// DCS: scheduled releases are checked often (cheap query), review reminders every six hours.
Schedule::command('dcs:release-due')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('dcs:review-reminders')->everySixHours()->withoutOverlapping();

// AI tools: uploaded files and results of document comparisons are kept for the retention period only.
Schedule::command('comparisons:prune')->daily()->withoutOverlapping();
