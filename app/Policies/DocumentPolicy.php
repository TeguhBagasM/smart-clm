<?php

namespace App\Policies;

use App\Models\User;

/**
 * Who may open the controlled document screens. Reading needs no special
 * permission (as in the Node API) but does need a company: a super admin sees
 * every company's documents.
 */
class DocumentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->tenant_id !== null;
    }
}
