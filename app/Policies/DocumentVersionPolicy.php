<?php

namespace App\Policies;

use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Enums\Permission;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use App\Support\Dcs\Signing\SignatureColumn;
use App\Support\Dcs\Signing\SignatureColumns;
use App\Support\Dcs\Signing\SignerGate;

/**
 * What each person may do to a version right now. The screens use these answers
 * to show or hide buttons (`can` in the resources). The actions behind the
 * buttons check the same rules again under a lock and answer with a message, so
 * a stale page can never do something the policy no longer allows.
 */
class DocumentVersionPolicy
{
    public function __construct(private SignerGate $signerGate) {}

    /**
     * Writing the content of a draft (the wizard).
     */
    public function edit(User $actor, DocumentVersion $version): bool
    {
        return $version->status === VersionStatus::Draft && $actor->can(Permission::DcsEdit);
    }

    /**
     * Setting when the version is next reviewed: allowed at any stage of its life.
     */
    public function scheduleReview(User $actor, DocumentVersion $version): bool
    {
        return $actor->can(Permission::DcsEdit);
    }

    /**
     * Closing or reopening the review link of outside parties.
     */
    public function manageExternalReview(User $actor, DocumentVersion $version): bool
    {
        return $actor->can(Permission::DcsEdit);
    }

    /**
     * A new link only fits a draft or a version waiting for signatures.
     */
    public function startExternalReview(User $actor, DocumentVersion $version): bool
    {
        return in_array($version->status, [VersionStatus::Draft, VersionStatus::UnderReview], true) && $actor->can(Permission::DcsEdit);
    }

    /**
     * An English reference copy needs content written in the system; an uploaded PDF has nothing to translate.
     */
    public function translate(User $actor, DocumentVersion $version): bool
    {
        return $actor->can(Permission::DcsEdit) && $version->content()->exists();
    }

    public function submit(User $actor, DocumentVersion $version): bool
    {
        $rendition = $version->currentRendition();

        return $version->status === VersionStatus::Draft && $rendition !== null && $rendition->kind !== RenditionKind::Signed;
    }

    public function sign(User $actor, DocumentVersion $version): bool
    {
        $column = $this->currentColumn($version);

        return $version->status === VersionStatus::UnderReview && $version->currentRendition() !== null && $column !== null && $this->signerGate->maySign($actor, $column, SignerGate::creatorIds($version));
    }

    public function requestChanges(User $actor, DocumentVersion $version): bool
    {
        $column = $this->currentColumn($version);

        return $version->status === VersionStatus::UnderReview && ($column === null || $this->signerGate->mayRequestChanges($actor, $column, SignerGate::creatorIds($version)));
    }

    public function returnToDraft(User $actor, DocumentVersion $version): bool
    {
        return $version->status === VersionStatus::UnderReview && ($actor->id === $version->document->owner_user_id || $actor->can(Permission::DcsRelease));
    }

    public function comment(User $actor, DocumentVersion $version): bool
    {
        return in_array($version->status, [VersionStatus::Draft, VersionStatus::UnderReview], true);
    }

    public function resolveComment(User $actor, DocumentVersion $version): bool
    {
        return $actor->id === $version->document->owner_user_id || $actor->isSuperAdmin();
    }

    public function release(User $actor, DocumentVersion $version): bool
    {
        return $version->status === VersionStatus::Approved && $actor->can(Permission::DcsRelease);
    }

    public function cancelRelease(User $actor, DocumentVersion $version): bool
    {
        return $version->status === VersionStatus::Approved && $version->scheduled_effective_at !== null && $actor->can(Permission::DcsRelease);
    }

    public function withdraw(User $actor, DocumentVersion $version): bool
    {
        return $version->status === VersionStatus::Effective && $actor->can(Permission::DcsRelease);
    }

    public function distribute(User $actor, DocumentVersion $version): bool
    {
        return $version->currentRendition() !== null && $actor->can(Permission::DcsRelease);
    }

    /**
     * Replacing the person of a column on a running document: the company's administrators only.
     */
    public function reassignSigner(User $actor, DocumentVersion $version): bool
    {
        return $actor->tenant_id !== null
            && $actor->tenant_id === $version->tenant_id
            && $actor->role->is_tenant_admin
            && in_array($version->status, [VersionStatus::Draft, VersionStatus::UnderReview], true);
    }

    public function acknowledge(User $actor, DocumentVersion $version): bool
    {
        return $version->currentRendition() !== null;
    }

    /**
     * The column whose turn it is, or null when none is waiting.
     */
    private function currentColumn(DocumentVersion $version): ?SignatureColumn
    {
        return SignatureColumns::of($version)->get($version->liveApprovals()->count());
    }
}
