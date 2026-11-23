<?php

use App\Enums\SystemRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Ai\AiException;
use App\Support\Dcs\Ai\DcsAi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeDcsAi;
use Tests\Support\FakePdfEngine;

beforeEach(function () {
    Storage::fake('public');
    config(['dcs.public_disk' => 'public']);
    $this->ai = new FakeDcsAi;
    app()->instance(DcsAi::class, $this->ai);
    $this->user = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
});

describe('reading an uploaded document', function () {
    it('needs a PDF or an image', function () {
        $post = fn (array $data) => $this->actingAs($this->user)->postJson(route('documents.extract-metadata'), $data);

        $post([])->assertStatus(422)->assertJsonValidationErrors('file');
        $post(['file' => UploadedFile::fake()->createWithContent('a.txt', 'hello')])->assertStatus(422)->assertJsonValidationErrors('file');
        expect($this->ai->extracted)->toBeEmpty();
    });

    it('passes the AI failure on with its status', function () {
        $this->ai->fail = new AiException(429, 'Kuota API Gemini habis untuk saat ini. Coba lagi nanti atau periksa kuota di Google AI Studio.');

        $this->actingAs($this->user)->postJson(route('documents.extract-metadata'), ['file' => UploadedFile::fake()->createWithContent('scan.pdf', FakePdfEngine::pdf())])->assertStatus(429);
    });
});

describe('appendix upload', function () {
    it('needs a PDF or Word file within the size limit', function () {
        $post = fn (array $data) => $this->actingAs($this->user)->postJson(route('documents.appendix-upload'), $data);

        $post([])->assertStatus(422)->assertJsonValidationErrors('file');
        $post(['file' => UploadedFile::fake()->createWithContent('a.exe', 'MZ')])->assertStatus(422)->assertJsonValidationErrors('file');
        $post(['file' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')])->assertStatus(422)->assertJsonValidationErrors('file');
        expect(Storage::disk('public')->allFiles())->toBeEmpty();
    });

    it('stores a PDF under the company folder with a safe file name', function () {
        $response = $this->actingAs($this->user)->postJson(route('documents.appendix-upload'), ['file' => UploadedFile::fake()->createWithContent('Form Cuti.pdf', FakePdfEngine::pdf())])->assertOk();

        expect($response->json('name'))->toBe('Form Cuti.pdf')->and($response->json('url'))->toContain('dcs-appendix/tenant-'.$this->user->tenant_id.'/')->and($response->json('url'))->toEndWith('Form_Cuti.pdf')
            ->and(Storage::disk('public')->allFiles('dcs-appendix'))->toHaveCount(1);
    });
});
