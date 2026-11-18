<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Resources\Documents\DocumentDetailResource;
use App\Http\Resources\Documents\DocumentEventListResource;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Support\Listing\EventListSpec;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DocumentTimelineController extends Controller
{
    public function index(Request $request, Document $document): Response
    {
        Gate::authorize('view', $document);

        $spec = new EventListSpec($document);
        $list = $spec->parse($request);

        return Inertia::render('documents/Timeline', [
            'document' => DocumentDetailResource::make($document->load(['type', 'department', 'owner', 'versions']))->resolve($request),
            'events' => DocumentEventListResource::collection($spec->paginate(DocumentEvent::query()->where('document_id', $document->id)->with('version'), $list)),
            'list' => $spec->meta($list),
        ]);
    }
}
