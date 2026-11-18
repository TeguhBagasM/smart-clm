<?php

use App\Actions\Dcs\RecordDocumentEvent;
use App\Enums\Dcs\ActorType;
use App\Enums\Dcs\DocumentEventType;
use App\Models\Dcs\Document;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('records an event by a user or by the system', function () {
    $document = Document::factory()->create();
    $actor = User::factory()->for($document->tenant)->create(['name' => 'Siti']);

    $byUser = app(RecordDocumentEvent::class)->handle($document, DocumentEventType::DocumentCreated, $actor, payload: ['number' => 'X']);
    $bySystem = app(RecordDocumentEvent::class)->handle($document, DocumentEventType::DocumentCreated, null);

    expect($byUser->actor_type)->toBe(ActorType::User)
        ->and($byUser->actor_name)->toBe('Siti')
        ->and($byUser->payload)->toBe(['number' => 'X'])
        ->and($byUser->source)->toBe('live')
        ->and($bySystem->actor_type)->toBe(ActorType::System)
        ->and($bySystem->actor_id)->toBeNull()
        ->and($bySystem->payload)->toBeNull();
});

it('refuses to change or delete an event through the model', function () {
    $event = app(RecordDocumentEvent::class)->handle(Document::factory()->create(), DocumentEventType::DocumentCreated, null);

    expect(fn () => $event->forceFill(['source' => 'import'])->save())->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class);
});

it('refuses to change or delete an event in the database', function () {
    $event = app(RecordDocumentEvent::class)->handle(Document::factory()->create(), DocumentEventType::DocumentCreated, null);

    expect(fn () => DB::table('dcs_document_events')->where('id', $event->id)->update(['source' => 'import']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('dcs_document_events')->where('id', $event->id)->delete())->toThrow(QueryException::class);
})->skip(fn () => DB::getDriverName() !== 'pgsql', 'The append-only trigger exists on PostgreSQL only.');
