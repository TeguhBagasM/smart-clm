<?php

namespace App\Support\Dcs\Signing;

use App\Models\User;
use App\Support\Dcs\DcsException;

/**
 * Decides who may act on the column whose turn it is: the person it was
 * assigned to, otherwise anyone holding its role (no role means anyone). A
 * super admin may always act. The same gate guards signing and asking for changes.
 */
class SignerGate
{
    /**
     * @throws DcsException
     */
    public function assertMaySign(User $user, SignatureColumn $column): void
    {
        $this->assert(
            $user,
            $column,
            "Kolom \"{$column->label}\" ditunjuk untuk penandatangan tertentu — bukan giliran/hak Anda menandatanganinya.",
            "Kolom \"{$column->label}\" memerlukan role \"{$column->roleKey}\" — Anda login sebagai \"{$user->role->key}\".",
        );
    }

    /**
     * @throws DcsException
     */
    public function assertMayRequestChanges(User $user, SignatureColumn $column): void
    {
        $this->assert(
            $user,
            $column,
            "Kolom \"{$column->label}\" ditunjuk untuk reviewer tertentu — bukan giliran/hak Anda meminta revisi.",
            "Meminta revisi pada kolom \"{$column->label}\" memerlukan role \"{$column->roleKey}\".",
        );
    }

    private function assert(User $user, SignatureColumn $column, string $notAssigned, string $wrongRole): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if ($column->assignedUserId !== null) {
            if ($user->id !== $column->assignedUserId) {
                throw new DcsException(403, $notAssigned);
            }

            return;
        }

        if ($column->roleId !== null && $user->role_id !== $column->roleId) {
            throw new DcsException(403, $wrongRole);
        }
    }
}
