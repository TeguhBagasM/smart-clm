<?php

namespace App\Policies;

use App\Models\Dcs\Document;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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

    /**
     * A document of another company is reported as missing, so its existence is not revealed.
     */
    public function view(User $actor, Document $document): Response
    {
        return $actor->isSuperAdmin() || $actor->tenant_id === $document->tenant_id ? Response::allow() : Response::denyAsNotFound();
    }
}
