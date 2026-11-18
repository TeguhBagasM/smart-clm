<?php

namespace App\Console\Commands;

use App\Actions\Rbac\SyncRbacCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rbac:sync-permissions')]
#[Description('Sync the permission catalog and system roles from code into the database (idempotent, run on every deploy)')]
class SyncRbacCatalogCommand extends Command
{
    public function handle(SyncRbacCatalog $syncRbacCatalog): int
    {
        $result = $syncRbacCatalog->handle();

        $this->components->info(sprintf(
            'RBAC catalog synced: %d permission(s) added, %d deprecated, %d system role(s) added.',
            $result['permissions_added'],
            $result['permissions_deprecated'],
            $result['roles_added'],
        ));

        return self::SUCCESS;
    }
}
