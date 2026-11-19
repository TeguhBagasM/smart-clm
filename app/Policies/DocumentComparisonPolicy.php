<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AiTools\DocumentComparison;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * "Bandingkan 2 Dokumen" belongs to one company and needs the `ai.compare` permission, so a super
 * admin (who has no company) is refused. A comparison holds the text of two documents, so only the
 * person who ran it opens it; anyone else, in the company or outside, is told it does not exist.
 */
class DocumentComparisonPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->tenant_id !== null && $actor->can(Permission::AiCompare);
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function view(User $actor, DocumentComparison $comparison): Response
    {
        return $this->viewAny($actor) && $actor->tenant_id === $comparison->tenant_id && $actor->id === $comparison->created_by_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function delete(User $actor, DocumentComparison $comparison): Response
    {
        return $this->view($actor, $comparison);
    }
}
