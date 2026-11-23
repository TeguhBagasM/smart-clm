<?php

use App\Actions\Dcs\ComposeVersion;
use App\Actions\Dcs\UploadVersionFile;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\Rendition;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakePdfEngine;

/*
 * The rules of composing and uploading a draft's file, held at the Action (these used to be
 * checked only through the legacy Node-style API).
 */

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    $this->engine = new FakePdfEngine;
    app()->instance(PdfEngine::class, $this->engine);
    $this->admin = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
    $this->tenant = $this->admin->tenant;
    $this->document = Document::factory()->for($this->tenant)->create(['owner_user_id' => $this->admin->id, 'created_by_id' => $this->admin->id]);
});

function draftOf(VersionStatus $status = VersionStatus::Draft): DocumentVersion
{
    return DocumentVersion::factory()->for(test()->document)->create(['status' => $status, 'created_by_id' => test()->admin->id, 'major' => test()->document->versions()->count() + 1]);
}

describe('composing', function () {
    it('saves the content, draws the PDF once and records it', function () {
        $version = draftOf();

        app(ComposeVersion::class)->handle($this->admin, $this->tenant, $version, [
            'purpose' => 'Tujuan', 'scope' => 'Ruang lingkup', 'languageMode' => 'multi',
            'sections' => [['heading' => 'Bab 1', 'content' => 'Isi']], 'appendix' => [['label' => 'Form', 'url' => 'https://example.com/f']],
        ], null);

        $content = $version->content()->first();
        expect($content->purpose)->toBe('Tujuan')->and($content->language_mode)->toBe('multi')->and($version->sections()->first()->heading)->toBe('Bab 1')->and($version->attachments()->first()->label)->toBe('Form')
            ->and($this->engine->composed)->toHaveCount(1)->and($content->render_layout['sigLayout'])->toBe(['colW' => 170])
            ->and(Rendition::query()->where('version_id', $version->id)->where('kind', RenditionKind::Composed)->count())->toBe(1)
            ->and(DocumentEvent::query()->where('event_type', DocumentEventType::ContentComposed)->count())->toBe(1);
    });

    it('refuses a version that is no longer a draft, and draws nothing', function () {
        $version = draftOf(VersionStatus::UnderReview);

        expect(fn () => app(ComposeVersion::class)->handle($this->admin, $this->tenant, $version, ['purpose' => 'x'], null))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(409));

        expect($this->engine->composed)->toBeEmpty();
    });

    it('keeps the saved content when the engine fails, and hides the details', function () {
        $version = draftOf();
        $this->engine->fail = true;

        expect(fn () => app(ComposeVersion::class)->handle($this->admin, $this->tenant, $version, ['purpose' => 'Tetap tersimpan'], null))
            ->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(500)->and($e->getMessage())->toBe('Gagal menyusun dokumen.')->and($e->getMessage())->not->toContain('boom'));

        expect($version->content()->first()->purpose)->toBe('Tetap tersimpan')->and(Rendition::query()->count())->toBe(0);
    });
});

describe('uploading a finished PDF', function () {
    it('stores it as the main file and records the event', function () {
        $version = draftOf();

        $rendition = app(UploadVersionFile::class)->handle($this->admin, $version, UploadedFile::fake()->createWithContent('a.pdf', FakePdfEngine::pdf()));

        expect($rendition->kind)->toBe(RenditionKind::Uploaded)->and($version->fresh()->currentRendition()?->id)->toBe($rendition->id)
            ->and(DocumentEvent::query()->where('event_type', DocumentEventType::FileUploaded)->count())->toBe(1);
        Storage::disk('dcs')->assertExists($rendition->storage_key);
    });

    it('refuses an upload once the version left draft', function () {
        $version = draftOf(VersionStatus::Effective);

        expect(fn () => app(UploadVersionFile::class)->handle($this->admin, $version, UploadedFile::fake()->createWithContent('a.pdf', FakePdfEngine::pdf())))
            ->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(409));

        expect(Rendition::query()->count())->toBe(0);
    });
});
