<?php

namespace App\Policies\MasterData;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may manage one master data list. Company users act on their own company
 * only; a super admin acts on any company (audit P1). A row of another company
 * answers 404 so its existence is not revealed. Reading the active options of
 * a list (to fill a form) is not governed here: every user of the company may.
 */
abstract class MasterDataPolicy
{
    abstract protected function permission(): Permission;

    protected function allows(User $actor): bool
    {
        return $actor->can($this->permission());
    }

    public function viewAny(User $actor): Response
    {
        return $this->decide($actor);
    }

    public function create(User $actor): Response
    {
        return $this->decide($actor);
    }

    public function reorder(User $actor): Response
    {
        return $this->decide($actor);
    }

    public function view(User $actor, Model $record): Response
    {
        return $this->onRecord($actor, $record);
    }

    public function update(User $actor, Model $record): Response
    {
        return $this->onRecord($actor, $record);
    }

    public function deactivate(User $actor, Model $record): Response
    {
        return $this->onRecord($actor, $record);
    }

    public function reactivate(User $actor, Model $record): Response
    {
        return $this->onRecord($actor, $record);
    }

    private function onRecord(User $actor, Model $record): Response
    {
        if (! $actor->isSuperAdmin() && $actor->tenant_id !== $record->getAttribute('tenant_id')) {
            return Response::denyAsNotFound();
        }

        return $this->decide($actor);
    }

    private function decide(User $actor): Response
    {
        return $this->allows($actor) ? Response::allow() : Response::denyWithStatus(403, 'Anda tidak berhak mengubah daftar ini.');
    }
}
