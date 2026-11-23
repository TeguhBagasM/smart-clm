<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Dcs\Document;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may open the controlled document screens. Reading needs no special
 * permission (as in the Node API) but does need a company: a super admin sees
 * every company's documents. Creating documents, the document settings and
 * the task queue belong to one company, so a super admin (who has none) is
 * refused them and the menus that lead there are hidden.
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

    public function create(User $actor): bool
    {
        return $actor->tenant_id !== null && $actor->can(Permission::DcsEdit);
    }

    /**
     * Starting the next revision or issue of a document of the actor's own company. The state
     * of the document is the Action's to judge; the page only offers the button when it fits.
     */
    public function createVersion(User $actor, Document $document): Response
    {
        return $this->ownCompany($actor, $document, Permission::DcsEdit);
    }

    /**
     * Giving back the number of a cancelled document (document control).
     */
    public function releaseNumber(User $actor, Document $document): Response
    {
        return $this->ownCompany($actor, $document, Permission::DcsRelease);
    }

    private function ownCompany(User $actor, Document $document, Permission $permission): Response
    {
        if ($actor->tenant_id === null || $actor->tenant_id !== $document->tenant_id) {
            return Response::denyAsNotFound();
        }

        return $actor->can($permission) ? Response::allow() : Response::deny('Anda tidak memiliki izin untuk tindakan ini.');
    }

    /**
     * Document types, clauses, departments and numbering of the actor's company.
     */
    public function configure(User $actor): bool
    {
        return $actor->tenant_id !== null && $actor->can(Permission::DcsConfig);
    }

    /**
     * "Tugas saya": the work waiting for the actor inside their company.
     */
    public function viewQueue(User $actor): bool
    {
        return $actor->tenant_id !== null;
    }
}
