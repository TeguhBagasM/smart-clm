<?php

namespace App\Console\Commands;

use App\Actions\Dcs\ActivateDueReleases;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dcs:release-due')]
#[Description('Bring into force every approved DCS version whose scheduled release date has arrived')]
class ReleaseDueDcsVersionsCommand extends Command
{
    public function handle(ActivateDueReleases $activateDueReleases): int
    {
        $this->components->info("Released {$activateDueReleases->handle()} scheduled version(s).");

        return self::SUCCESS;
    }
}
