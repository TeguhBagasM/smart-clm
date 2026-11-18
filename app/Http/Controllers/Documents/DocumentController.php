<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Enums\Dcs\RenditionKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\Documents\DocumentDetailResource;
use App\Http\Resources\Documents\DocumentEventListResource;
use App\Http\Resources\Documents\DocumentListResource;
use App\Http\Resources\Documents\VersionDetailResource;
use App\Models\Dcs\Document;
use App\Models\User;
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

    /**
     * A document with its versions; `?version=` picks the one shown (default:
     * the version in force, else the newest). The version's own data is only
     * computed when asked for, so switching versions reloads just that prop.
     */
    public function show(Request $request, Document $document): Response
    {
        Gate::authorize('view', $document);

        $document->load([
            'type', 'department', 'owner',
            'versions' => fn ($versions) => $versions->withExists(['renditions as has_file' => fn (Builder $file) => $file->whereIn('kind', RenditionKind::mainValues())]),
        ]);

        $selected = $document->versions->firstWhere('uuid', $request->query('version'))
            ?? $document->versions->firstWhere('id', $document->current_version_id)
            ?? $document->versions->first();

        return Inertia::render('documents/Show', [
            'document' => DocumentDetailResource::make($document)->resolve($request),
            'version' => fn () => $selected === null ? null : VersionDetailResource::make($selected->load('creator'))->resolve($request),
            'recipient_options' => Inertia::optional(fn () => User::query()->where('tenant_id', $document->tenant_id)->where('is_active', true)->with('role')->orderBy('name')->limit(500)->get()
                ->map(fn (User $person): array => ['id' => $person->id, 'name' => $person->name, 'role' => $person->role->name])->all()),
            'recent_events' => DocumentEventListResource::collection($document->events()->with('version')->orderByDesc('occurred_at')->orderByDesc('id')->limit(6)->get())->resolve($request),
        ]);
    }
}
