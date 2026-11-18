<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\SetReviewSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\ReviewScheduleRequest;
use App\Models\Dcs\DocumentVersion;
use App\Support\Dcs\CurrentTenant;
use Illuminate\Http\JsonResponse;

class ReviewScheduleController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function update(ReviewScheduleRequest $request, string $id, SetReviewSchedule $setReviewSchedule): JsonResponse
    {
        $version = DocumentVersion::forTenant($this->currentTenant->get(), $id);

        $setReviewSchedule->handle($request->user(), $version, $request->dueAt(), $request->reminderDays());

        return response()->json(['success' => true]);
    }
}
