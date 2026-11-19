<?php

use App\Actions\Dcs\RenderVersion;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\HeaderAsset;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Header\HeaderLayout;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakePdfEngine;

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    $this->engine = new FakePdfEngine;
    app()->instance(PdfEngine::class, $this->engine);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
    $this->actingAs($this->admin)->get(route('documents.config.types.index'))->assertOk();
});

function uploadLogo($test, ?UploadedFile $file = null)
{
    return $test->actingAs($test->admin)->postJson(route('documents.config.header-assets.store'), ['file' => $file ?? UploadedFile::fake()->image('logo.png', 120, 60)]);
}

describe('logo upload', function () {
    it('stores a re-encoded image under the company folder with a random name', function () {
        $response = uploadLogo($this)->assertCreated()->assertJsonPath('width', 120)->assertJsonPath('height', 60);

        $asset = HeaderAsset::query()->where('uuid', $response->json('id'))->firstOrFail();
        expect($asset->tenant_id)->toBe($this->tenant->id)
            ->and($asset->mime)->toBe('image/png')
            ->and($asset->disk_key)->toStartWith('header-assets/'.$this->tenant->id.'/')
            ->and($asset->disk_key)->not->toContain('logo')
            ->and(hash('sha256', Storage::disk('dcs')->get($asset->disk_key)))->toBe($asset->sha256);
    });

    it('turns a WebP into a PNG, which is what the PDF engine can embed', function () {
        $webp = UploadedFile::fake()->createWithContent('logo.webp', (function (): string {
            ob_start();
            imagewebp(imagecreatetruecolor(40, 40));

            return (string) ob_get_clean();
        })());

        $asset = HeaderAsset::query()->where('uuid', uploadLogo($this, $webp)->assertCreated()->json('id'))->firstOrFail();

        expect($asset->mime)->toBe('image/png')->and(substr(Storage::disk('dcs')->get($asset->disk_key), 1, 3))->toBe('PNG');
    });

    it('refuses a file that only carries an image extension, SVG, a wrong type, a huge file and a guest', function () {
        uploadLogo($this, UploadedFile::fake()->createWithContent('logo.png', 'this is not an image'))->assertUnprocessable()->assertJsonValidationErrors('file');
        uploadLogo($this, UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))->assertUnprocessable();
        uploadLogo($this, UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'))->assertUnprocessable();
        uploadLogo($this, UploadedFile::fake()->image('big.png', 100, 100)->size(3000))->assertUnprocessable();
        uploadLogo($this, UploadedFile::fake()->image('wide.png', 4100, 10))->assertUnprocessable();

        expect(HeaderAsset::query()->count())->toBe(0);

        auth()->logout();
        $this->postJson(route('documents.config.header-assets.store'), ['file' => UploadedFile::fake()->image('a.png')])->assertUnauthorized();
    });

    it('keeps one company away from the logos of another', function () {
        $uuid = uploadLogo($this)->json('id');
        $other = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();

        $this->actingAs($this->admin)->get(route('documents.config.header-assets.show', $uuid))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($other)->get(route('documents.config.header-assets.show', $uuid))->assertNotFound();

        $layout = HeaderLayout::default();
        $layout['logo'] = [...$layout['logo'], 'source' => 'custom', 'assetId' => $uuid];
        $type = ['code' => 'PRG', 'name' => 'Program', 'layoutStyle' => 'standard', 'headerStyle' => 'iso', 'headerLayout' => $layout];
        $this->actingAs($other)->post(route('documents.config.types.store'), $type)->assertSessionHasErrors('headerLayout.logo.assetId');
        $this->actingAs($this->admin)->post(route('documents.config.types.store'), $type)->assertSessionHasNoErrors();
    });
});

describe('company colours', function () {
    it('saves the company list in upper-case hex, once each, and refuses anything else', function () {
        $this->actingAs($this->admin)->putJson(route('documents.config.brand-colors.update'), ['colors' => [['hex' => '#1a2b3c', 'name' => 'Biru <b>kami</b>'], ['hex' => '#1A2B3C', 'name' => 'dobel'], ['hex' => '#ff8800']]])
            ->assertOk()->assertJsonPath('colors.0.hex', '#1A2B3C')->assertJsonPath('colors.0.name', 'Biru kami')->assertJsonCount(2, 'colors');

        $this->actingAs($this->admin)->putJson(route('documents.config.brand-colors.update'), ['colors' => [['hex' => 'blue']]])->assertUnprocessable();
        $this->actingAs($this->admin)->putJson(route('documents.config.brand-colors.update'), ['colors' => array_fill(0, 25, ['hex' => '#112233'])])->assertUnprocessable();

        $other = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
        $this->actingAs($other)->get(route('documents.config.types.create'))->assertInertia(fn ($page) => $page->where('brand_colors', []));
        $this->actingAs($this->admin)->get(route('documents.config.types.create'))->assertInertia(fn ($page) => $page->where('brand_colors.0.hex', '#1A2B3C'));
    });
});

describe('composing with the type logo', function () {
    it('prints the uploaded logo and keeps the letterhead used in the version, whatever the type becomes', function () {
        $uuid = uploadLogo($this)->json('id');
        $asset = HeaderAsset::query()->where('uuid', $uuid)->firstOrFail();
        $layout = HeaderLayout::default();
        $layout['logo'] = [...$layout['logo'], 'source' => 'custom', 'assetId' => $uuid];
        $layout['rows'][0]['cells'][0]['background'] = '#ABCDEF';
        $sop = $this->tenant->dcsDocumentTypes()->where('code', 'SOP')->firstOrFail();
        $sop->forceFill(['header_layout' => $layout])->save();

        $this->actingAs($this->admin)->post(route('documents.store'), ['mode' => 'compose', 'docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'Prosedur'])->assertRedirect();
        $version = Document::query()->where('tenant_id', $this->tenant->id)->firstOrFail()->versions()->firstOrFail();
        app(RenderVersion::class)->handle($this->admin, $version);

        $sent = $this->engine->composed[0];
        expect($sent['letterhead']['logo'])->toBe(Storage::disk('dcs')->get($asset->disk_key))
            ->and($sent['header']['layout']['rows'][0]['cells'][0]['background'])->toBe('#ABCDEF');

        $snapshot = $version->content()->first()->render_layout['header'];
        expect($snapshot['logoSha256'])->toBe($asset->sha256)->and($snapshot['layout']['rows'][0]['cells'][0]['background'])->toBe('#ABCDEF');

        $sop->forceFill(['header_layout' => HeaderLayout::default()])->save();
        expect($version->content()->first()->refresh()->render_layout['header']['layout']['rows'][0]['cells'][0]['background'])->toBe('#ABCDEF');
    });
});
