<?php

use App\Actions\Dcs\SaveDraftContent;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Clause;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
    $this->tenant = $this->admin->tenant;
    $document = Document::factory()->for($this->tenant)->create(['owner_user_id' => $this->admin->id, 'created_by_id' => $this->admin->id]);
    $this->version = DocumentVersion::factory()->for($document)->create(['status' => VersionStatus::Draft, 'created_by_id' => $this->admin->id]);
});

it('links clauses of the company to the draft, in order', function () {
    $first = Clause::factory()->for($this->tenant)->create();
    $second = Clause::factory()->for($this->tenant)->create();

    app(SaveDraftContent::class)->handle($this->admin, $this->tenant, $this->version, ['clauseIds' => [$second->uuid, $first->uuid]]);

    expect($this->version->clauses()->pluck('uuid')->all())->toBe([$second->uuid, $first->uuid]);
});

it('refuses clauses that do not exist or belong to another company, and keeps the earlier links', function () {
    $own = Clause::factory()->for($this->tenant)->create();
    $foreign = Clause::factory()->create();
    app(SaveDraftContent::class)->handle($this->admin, $this->tenant, $this->version, ['clauseIds' => [$own->uuid]]);

    foreach ([fake()->uuid(), $foreign->uuid] as $uuid) {
        expect(fn () => app(SaveDraftContent::class)->handle($this->admin, $this->tenant, $this->version, ['clauseIds' => [$uuid]]))->toThrow(ValidationException::class);
    }

    expect($this->version->clauses()->pluck('uuid')->all())->toBe([$own->uuid]);
});
