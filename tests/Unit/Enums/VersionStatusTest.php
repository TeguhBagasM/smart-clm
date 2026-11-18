<?php

use App\Enums\Dcs\VersionStatus;

it('only allows the transitions of the Node state machine', function (VersionStatus $from, VersionStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'draft to review' => [VersionStatus::Draft, VersionStatus::UnderReview, true],
    'review to approved' => [VersionStatus::UnderReview, VersionStatus::Approved, true],
    'review back to draft' => [VersionStatus::UnderReview, VersionStatus::Draft, true],
    'approved to effective' => [VersionStatus::Approved, VersionStatus::Effective, true],
    'approved back to review' => [VersionStatus::Approved, VersionStatus::UnderReview, true],
    'effective to superseded' => [VersionStatus::Effective, VersionStatus::Superseded, true],
    'draft straight to effective' => [VersionStatus::Draft, VersionStatus::Effective, false],
    'draft straight to approved' => [VersionStatus::Draft, VersionStatus::Approved, false],
    'review straight to effective' => [VersionStatus::UnderReview, VersionStatus::Effective, false],
    'effective back to approved' => [VersionStatus::Effective, VersionStatus::Approved, false],
    'reviving a superseded version' => [VersionStatus::Superseded, VersionStatus::Effective, false],
    'superseded to draft' => [VersionStatus::Superseded, VersionStatus::Draft, false],
    'staying in place' => [VersionStatus::Draft, VersionStatus::Draft, false],
]);

it('has no way out of superseded', function () {
    expect(VersionStatus::Superseded->allowedNext())->toBe([]);
});
