<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Enums\Dcs\RenditionKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\Documents\DocumentListResource;
use App\Models\Dcs\Document;
use App\Support\Listing\DocumentListSpec;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function index(Request $request, SeedDcsTenantDefaults $seedDcsTenantDefaults): Response
    {
        Gate::authorize('viewAny', Document::class);

        $user = $request->user();

        if ($user->tenant !== null) {
            $seedDcsTenantDefaults->handle($user->tenant);
        }

        $spec = new DocumentListSpec($user);
        $list = $spec->parse($request);

        $documents = $spec->paginate(
            Document::query()->visibleTo($user)->with([
                'type', 'department', 'owner', 'currentVersion',
                'latestVersion' => fn ($version) => $version
                    ->withExists(['renditions as has_file' => fn (Builder $file) => $file->whereIn('kind', RenditionKind::mainValues())])
                    ->withCount(['reviewComments as open_comments' => fn (Builder $comment) => $comment->where('status', 'open')]),
            ]),
            $list,
        );

        return Inertia::render('documents/Index', [
            'documents' => DocumentListResource::collection($documents),
            'list' => $spec->meta($list),
        ]);
    }
}
