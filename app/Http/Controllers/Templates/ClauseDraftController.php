<?php

namespace App\Http\Controllers\Templates;

use App\Actions\Templates\DraftClauseWithAi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\DraftClauseWithAiRequest;
use Illuminate\Http\JsonResponse;

/**
 * The clause assistant: returns a suggestion as JSON for the form to fill. Saves nothing.
 */
class ClauseDraftController extends Controller
{
    public function store(DraftClauseWithAiRequest $request, DraftClauseWithAi $draft): JsonResponse
    {
        return response()->json($draft->handle($request->user(), $request->tenant(), (string) $request->input('description')));
    }
}
