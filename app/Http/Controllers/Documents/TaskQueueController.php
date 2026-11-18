<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Resources\Documents\QueueItemResource;
use App\Models\Dcs\Document;
use App\Support\Dcs\Queue\TaskKind;
use App\Support\Dcs\Queue\TaskQueue;
use App\Support\Listing\QueueListSpec;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Tugas saya": what is waiting for the signed-in person, one kind at a time
 * (`?kind=`), with the same search, filters, sort and paging as every list.
 */
class TaskQueueController extends Controller
{
    public function index(Request $request, TaskQueue $taskQueue): Response
    {
        Gate::authorize('viewAny', Document::class);

        $user = $request->user();
        $counts = $taskQueue->counts($user);
        $kind = TaskKind::tryFrom((string) $request->query('kind')) ?? $this->busiestKind($counts);

        $spec = new QueueListSpec($user);
        $list = $spec->parse($request);
        $request->attributes->set('task_kind', $kind);

        $items = $spec->paginate(
            $taskQueue->for($user, $kind)->with(['document.type', 'document.department', 'document.owner', 'steps'])
                ->withCount(['liveApprovals as signed_count', 'reviewComments as open_comments' => fn ($comment) => $comment->where('status', 'open')]),
            $list,
        );

        return Inertia::render('documents/Queue', [
            'items' => QueueItemResource::collection($items),
            'list' => $spec->meta($list),
            'kind' => ['value' => $kind->value, 'label' => $kind->label(), 'description' => $kind->description(), 'empty' => $kind->emptyText(), 'action' => $kind->action()],
            'kinds' => array_map(fn (TaskKind $each): array => ['value' => $each->value, 'label' => $each->label(), 'count' => $counts[$each->value]], TaskKind::cases()),
        ]);
    }

    /**
     * Without a chosen tab, open the first one that has something waiting (the
     * first tab when nothing does).
     *
     * @param  array<string, int>  $counts
     */
    private function busiestKind(array $counts): TaskKind
    {
        foreach (TaskKind::cases() as $kind) {
            if ($counts[$kind->value] > 0) {
                return $kind;
            }
        }

        return TaskKind::Sign;
    }
}
