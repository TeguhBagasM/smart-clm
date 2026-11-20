<?php

namespace App\Policies\Templates;

use App\Enums\Permission;
use App\Models\User;
use App\Policies\MasterData\MasterDataPolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Templates: templates.manage in the own company. Working on the draft, publishing,
 * starting a new draft and duplicating all need the same right; each has its own
 * name so a route can say what it asks for.
 */
class ContractTemplatePolicy extends MasterDataPolicy
{
    protected function permission(): Permission
    {
        return Permission::TemplatesManage;
    }

    public function saveDraft(User $actor, Model $record): Response
    {
        return $this->update($actor, $record);
    }

    public function publish(User $actor, Model $record): Response
    {
        return $this->update($actor, $record);
    }

    public function startDraft(User $actor, Model $record): Response
    {
        return $this->update($actor, $record);
    }

    public function duplicate(User $actor, Model $record): Response
    {
        return $this->update($actor, $record);
    }
}
