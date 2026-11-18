<?php

namespace App\Actions\Dcs;

use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\VersionContent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Saves the content of a draft and draws its PDF: what "Susun" did in Node. The
 * content is saved first and stays saved if the engine then fails, so the
 * author can retry without retyping.
 */
class ComposeVersion
{
    public function __construct(private SaveDraftContent $saveDraftContent, private RenderVersion $renderVersion) {}

    /**
     * @param  array<string, mixed>  $content
     */
    public function handle(User $actor, Tenant $tenant, DocumentVersion $version, array $content, ?UploadedFile $flowImage): VersionContent
    {
        $saved = $this->saveDraftContent->handle($actor, $tenant, $version, $content, $flowImage);

        $this->renderVersion->handle($actor, $version);

        return $saved;
    }
}
