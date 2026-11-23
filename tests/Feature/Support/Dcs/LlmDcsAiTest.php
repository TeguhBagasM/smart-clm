<?php

use App\Models\AiCall;
use App\Support\Ai\LlmException;
use App\Support\Ai\LlmManager;
use App\Support\Dcs\Ai\AiException;
use App\Support\Dcs\Ai\DcsAi;
use App\Support\Dcs\Ai\LlmDcsAi;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeLlmClient;

function llmAi(): DcsAi
{
    return app(DcsAi::class);
}

function translationSource(): array
{
    return ['title' => 'Prosedur Rahasia', 'purpose' => 'Tujuan', 'scope' => 'Ruang', 'sections' => [['heading' => 'A', 'content' => 'Isi rahasia satu'], ['heading' => 'B', 'content' => 'Isi dua']]];
}

function failWith(FakeLlmClient $fake, LlmException $e): void
{
    $fake->reply(fn () => throw $e);
}

it('is the bound implementation', function () {
    expect(llmAi())->toBeInstanceOf(LlmDcsAi::class);
});

it('translates with the Node prompt and returns the model answer', function () {
    Http::fake();
    $fake = FakeLlmClient::install();
    $answer = ['title' => 'Secret Procedure', 'purpose' => 'Purpose', 'scope' => 'Scope', 'sections' => [['heading' => 'A', 'content' => 'One'], ['heading' => 'B', 'content' => 'Two']]];
    $fake->reply($answer);

    expect(llmAi()->translate(translationSource()))->toBe($answer);

    expect($fake->requests[0]->prompt)->toContain('Penerjemah Tersumpah')->toContain('Isi rahasia satu')->and($fake->requests[0]->files)->toBe([]);
    Http::assertNothingSent();
});

it('rejects a translation with another number of sections after one retry', function () {
    $fake = FakeLlmClient::install();
    $short = ['title' => 'T', 'purpose' => 'P', 'scope' => 'S', 'sections' => [['heading' => 'A', 'content' => 'One']]];
    $fake->reply($short)->reply($short);

    expect(fn () => llmAi()->translate(translationSource()))
        ->toThrow(fn (AiException $e) => expect($e->status)->toBe(502)->and($e->getMessage())->toContain('jumlah bagian berbeda'));
    expect($fake->requests)->toHaveCount(2);
});

it('answers the generic message when the reply is not JSON', function () {
    $fake = FakeLlmClient::install();
    $fake->reply('bukan json')->reply('tetap bukan json');

    expect(fn () => llmAi()->translate(translationSource()))
        ->toThrow(fn (AiException $e) => expect($e->status)->toBe(500)->and($e->getMessage())->toBe('Gagal menerjemahkan dokumen.'));
});

it('maps model failures to the statuses Node used', function (LlmException $failure, int $status, ?string $message) {
    $fake = FakeLlmClient::install();
    failWith($fake, $failure);
    failWith($fake, $failure);

    expect(fn () => llmAi()->translate(translationSource()))
        ->toThrow(fn (AiException $e) => expect($e->status)->toBe($status)->and($message === null || $e->getMessage() === $message)->toBeTrue());
})->with([
    'quota' => [new LlmException('Kuota habis.', true, '429'), 429, 'Kuota habis.'],
    'server error' => [new LlmException('Sedang bermasalah.', true, '503'), 503, 'Sedang bermasalah.'],
    'connection' => [new LlmException('Tidak dapat terhubung.', true, 'cURL error 28'), 502, 'Koneksi ke layanan AI terputus. Periksa jaringan lalu coba lagi.'],
    'empty answer' => [new LlmException('Tidak memberi jawaban.', true, 'empty candidate: STOP'), 500, 'Gagal menerjemahkan dokumen.'],
    'rejected' => [new LlmException('Ditolak.', false, '400'), 500, 'Gagal menerjemahkan dokumen.'],
    'not configured' => [new LlmException('Belum dikonfigurasi.'), 500, 'Gagal menerjemahkan dokumen.'],
]);

it('sends the file to the vision provider when extracting metadata', function () {
    $fake = FakeLlmClient::install();
    config(['ai.provider' => 'deepseek']);
    app(LlmManager::class)->extend('deepseek', fn () => throw new RuntimeException('text provider must not be used'));
    $fake->reply(['title' => 'Prosedur Cuti', 'docTypeCode' => 'SOP', 'department' => null]);

    $result = llmAi()->extractMetadata('%PDF-isi-rahasia', 'application/pdf', ['SOP', 'IK']);

    expect($result)->toBe(['title' => 'Prosedur Cuti', 'docTypeCode' => 'SOP', 'department' => null])
        ->and($fake->requests[0]->files)->toBe([['mime' => 'application/pdf', 'data' => '%PDF-isi-rahasia']])
        ->and($fake->requests[0]->prompt)->toContain('Document Control ISO 9001')->toContain('["SOP","IK"]');
});

it('maps an unreadable file to 400 and other failures like translation', function () {
    $fake = FakeLlmClient::install();
    failWith($fake, new LlmException('Ditolak.', false, '400'));

    expect(fn () => llmAi()->extractMetadata('x', 'application/pdf', ['SOP']))
        ->toThrow(fn (AiException $e) => expect($e->status)->toBe(400)->and($e->getMessage())->toContain('Berkas PDF tidak valid'));

    $fake->reply('bukan json')->reply('bukan json');

    expect(fn () => llmAi()->extractMetadata('x', 'application/pdf', ['SOP']))
        ->toThrow(fn (AiException $e) => expect($e->status)->toBe(500)->and($e->getMessage())->toBe('Gagal membaca dokumen via OCR AI.'));
});

it('keeps document content out of ai_calls', function () {
    $fake = FakeLlmClient::install();
    $fake->reply(['title' => 'Secret Procedure', 'purpose' => 'P', 'scope' => 'S', 'sections' => [['heading' => 'A', 'content' => 'One'], ['heading' => 'B', 'content' => 'Two']]]);
    $fake->reply(['title' => 'Prosedur', 'docTypeCode' => 'SOP', 'department' => 'HRD']);

    llmAi()->translate(translationSource());
    llmAi()->extractMetadata('%PDF-isi-rahasia', 'application/pdf', ['SOP']);

    $calls = AiCall::orderBy('id')->get();

    expect($calls->pluck('stage')->all())->toBe(['dcs_translate', 'dcs_extract_metadata'])
        ->and($calls->pluck('ok')->all())->toBe([true, true])
        ->and(json_encode($calls->toArray()))->not->toContain('rahasia')->not->toContain('Secret');
});
