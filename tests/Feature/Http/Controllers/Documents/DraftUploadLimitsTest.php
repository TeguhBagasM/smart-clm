<?php

use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\Rendition;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakePdfEngine;

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    $this->admin = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
    $document = Document::factory()->for($this->admin->tenant)->create(['owner_user_id' => $this->admin->id, 'created_by_id' => $this->admin->id]);
    $this->version = DocumentVersion::factory()->for($document)->create(['status' => VersionStatus::Draft, 'created_by_id' => $this->admin->id]);
    $this->url = route('documents.versions.upload', [$document, $this->version]);
});

it('needs a PDF within the size limit', function (?UploadedFile $file, string $message) {
    $this->actingAs($this->admin)->post($this->url, $file === null ? [] : ['file' => $file])->assertSessionHasErrors('file');

    expect(session('errors')->first('file'))->toContain($message)->and(Rendition::query()->count())->toBe(0);
})->with([
    'no file' => [null, 'Tidak ada berkas'],
    'not a PDF' => [fn () => UploadedFile::fake()->createWithContent('a.txt', 'hello'), 'PDF'],
    'over 10 MB' => [fn () => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf'), '10 MB'],
]);

it('stores a PDF within the limit', function () {
    $this->actingAs($this->admin)->post($this->url, ['file' => UploadedFile::fake()->createWithContent('a.pdf', FakePdfEngine::pdf())])->assertSessionHasNoErrors();

    expect(Rendition::query()->count())->toBe(1);
});
