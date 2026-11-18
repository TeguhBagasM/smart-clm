<?php

use App\Support\Dcs\Ai\AiException;
use App\Support\Dcs\Ai\NodeDcsAi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function nodeAi(): NodeDcsAi
{
    return new NodeDcsAi('http://node.test', 'shared-secret', 5);
}

it('posts the source to the translate endpoint with the secret and returns the translation', function () {
    Http::fake(['node.test/*' => Http::response(['translated' => ['title' => 'T', 'sections' => []]])]);

    expect(nodeAi()->translate(['title' => 'Judul']))->toBe(['title' => 'T', 'sections' => []]);
    Http::assertSent(fn (Request $request) => $request->url() === 'http://node.test/internal/dcs/ai/translate' && $request->header('X-Internal-Secret') === ['shared-secret'] && $request['source'] === ['title' => 'Judul']);
});

it('sends the file as base64 to the extract endpoint', function () {
    Http::fake(['node.test/*' => Http::response(['result' => ['title' => 'T']])]);

    expect(nodeAi()->extractMetadata('%PDF-bytes', 'application/pdf', ['SOP']))->toBe(['title' => 'T']);
    Http::assertSent(fn (Request $request) => $request->url() === 'http://node.test/internal/dcs/ai/extract-metadata' && base64_decode($request['dataBase64']) === '%PDF-bytes' && $request['docTypeCodes'] === ['SOP'] && $request['mimeType'] === 'application/pdf');
});

it('keeps the status and message of busy, quota, connection and invalid file answers', function (int $status, string $message) {
    Http::fake(['*' => Http::response(['error' => $message], $status)]);

    try {
        nodeAi()->translate([]);
        $this->fail('Expected an AiException.');
    } catch (AiException $e) {
        expect($e->status)->toBe($status)->and($e->getMessage())->toBe($message);
    }
})->with([[503, 'Layanan AI Google sedang sibuk'], [429, 'Kuota habis'], [502, 'Koneksi terputus'], [400, 'Berkas PDF tidak valid']]);

it('hides unexpected failures behind the generic message', function (callable $arrange, ?NodeDcsAi $engine) {
    $arrange();

    try {
        ($engine ?? nodeAi())->translate([]);
        $this->fail('Expected an AiException.');
    } catch (AiException $e) {
        expect($e->status)->toBeIn([500, 502])->and($e->getMessage())->not->toContain('secret')->and($e->getMessage())->not->toContain('boom');
    }
})->with([
    'server error' => [fn () => Http::fake(['*' => Http::response(['error' => 'boom'], 500)]), null],
    'unauthorised' => [fn () => Http::fake(['*' => Http::response(['error' => 'Unauthorized'], 401)]), null],
    'no translation in the answer' => [fn () => Http::fake(['*' => Http::response(['translated' => 'not an array'])]), null],
    'unconfigured' => [fn () => null, new NodeDcsAi(null, null)],
    'unreachable' => [fn () => Http::fake(fn () => throw new ConnectionException('boom')), null],
]);
