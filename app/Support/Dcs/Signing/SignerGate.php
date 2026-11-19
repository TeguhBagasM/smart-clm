<?php

namespace App\Support\Dcs\Signing;

use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use App\Support\Dcs\DcsException;

/**
 * Decides who may act on the column whose turn it is. A column with a named
 * person is that person's alone: other holders of the role, of the same
 * department, are refused. A column set up before people were named ("legacy")
 * keeps the old rule: anyone holding its role (no role means anyone). A column of
 * the new kind that has nobody yet is closed to everyone. Unless the column allows
 * it, the person who created the document cannot act on a column after the first.
 * A super admin may always act. The same gate guards signing and asking for changes.
 */
class SignerGate
{
    /**
     * @throws DcsException
     */
    public function assertMaySign(User $user, SignatureColumn $column, array $creatorIds = []): void
    {
        $this->assert(
            $user,
            $column,
            $creatorIds,
            "Kolom \"{$column->label}\" ditunjuk untuk penandatangan tertentu — bukan giliran/hak Anda menandatanganinya.",
            "Kolom \"{$column->label}\" memerlukan role \"{$column->roleKey}\" — Anda login sebagai \"{$user->role->key}\".",
        );
    }

    /**
     * @throws DcsException
     */
    public function assertMayRequestChanges(User $user, SignatureColumn $column, array $creatorIds = []): void
    {
        $this->assert(
            $user,
            $column,
            $creatorIds,
            "Kolom \"{$column->label}\" ditunjuk untuk reviewer tertentu — bukan giliran/hak Anda meminta revisi.",
            "Meminta revisi pada kolom \"{$column->label}\" memerlukan role \"{$column->roleKey}\".",
        );
    }

    /**
     * Who made the document: its creator and its owner (the owner signs the first column).
     *
     * @return list<int>
     */
    public static function creatorIds(DocumentVersion $version): array
    {
        $document = $version->document;

        return array_values(array_filter([$document->created_by_id, $document->owner_user_id], fn (?int $id): bool => $id !== null));
    }

    /**
     * @param  list<int>  $creatorIds
     */
    private function assert(User $user, SignatureColumn $column, array $creatorIds, string $notAssigned, string $wrongRole): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $legacy = $column->fillMode === 'legacy';

        if ($column->assignedUserId !== null) {
            if ($user->id !== $column->assignedUserId) {
                throw new DcsException(403, $notAssigned);
            }
        } elseif (! $legacy) {
            throw new DcsException(403, "Kolom \"{$column->label}\" belum memiliki penandatangan yang ditetapkan.");
        } elseif ($column->roleId !== null && $user->role_id !== $column->roleId) {
            throw new DcsException(403, $wrongRole);
        }

        if (! $legacy && ! $column->allowCreator && $column->order > 1 && in_array($user->id, $creatorIds, true)) {
            throw new DcsException(403, 'Pembuat dokumen tidak boleh menyetujui dokumennya sendiri.');
        }
    }

    /**
     * Whether the user may sign this column now, without raising.
     */
    public function maySign(User $user, SignatureColumn $column, array $creatorIds = []): bool
    {
        try {
            $this->assertMaySign($user, $column, $creatorIds);

            return true;
        } catch (DcsException) {
            return false;
        }
    }

    /**
     * Whether the user may ask for changes at this column, without raising.
     */
    public function mayRequestChanges(User $user, SignatureColumn $column, array $creatorIds = []): bool
    {
        try {
            $this->assertMayRequestChanges($user, $column, $creatorIds);

            return true;
        } catch (DcsException) {
            return false;
        }
    }
}
