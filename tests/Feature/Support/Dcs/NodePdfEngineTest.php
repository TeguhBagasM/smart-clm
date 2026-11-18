<?php

use App\Support\Dcs\Pdf\NodePdfEngine;
use App\Support\Dcs\Pdf\PdfEngineException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakePdfEngine;

function nodeEngine(): NodePdfEngine
{
    return new NodePdfEngine('http://node.test', 'shared-secret', 5);
}

it('posts to the internal endpoint with the secret and sends binary parts as base64', function () {
    Http::fake(['node.test/*' => Http::response([
        'pdfBase64' => base64_encode(FakePdfEngine::pdf()),
        'signatureSheetPageIndex' => 2,
        'sigLayout' => ['colW' => 100],
        'headerLayout' => null,
    ])]);

    $result = nodeEngine()->compose([
        'flow' => ['mode' => 'image', 'image' => "\x89PNG-bytes", 'mimeType' => 'image/png'],
        'letterhead' => ['companyName' => 'PT A', 'companyAddress' => 'Jl. B', 'logo' => 'logo-bytes', 'logoMimeType' => 'image/png'],
    ]);

    expect($result['pdf'])->toStartWith('%PDF')->and($result['signatureSheetPageIndex'])->toBe(2)->and($result['headerLayout'])->toBeNull();
    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://node.test/internal/dcs/pdf/compose'
            && $request->header('X-Internal-Secret') === ['shared-secret']
            && $request['flow']['imageBase64'] === base64_encode("\x89PNG-bytes")
            && ! isset($request['flow']['image'])
            && $request['letterhead']['logoBase64'] === base64_encode('logo-bytes')
            && ! isset($request['letterhead']['logo']);
    });
});

it('fails clearly when unconfigured, on an error status, or when no PDF comes back', function (callable $arrange, ?string $url) {
    $arrange();
    $engine = $url === null ? new NodePdfEngine(null, null) : nodeEngine();

    expect(fn () => $engine->compose([]))->toThrow(PdfEngineException::class);
})->with([
    'unconfigured' => [fn () => null, null],
    'server error' => [fn () => Http::fake(['*' => Http::response(['error' => 'boom'], 500)]), 'x'],
    'unauthorised' => [fn () => Http::fake(['*' => Http::response([], 401)]), 'x'],
    'not a pdf' => [fn () => Http::fake(['*' => Http::response(['pdfBase64' => base64_encode('<html>')])]), 'x'],
]);

it('sends the PDF to the sign endpoint as base64 and returns the stamped bytes', function () {
    $stamped = FakePdfEngine::pdf('stamped');
    Http::fake(['node.test/*' => Http::response(['pdfBase64' => base64_encode($stamped)])]);

    $result = nodeEngine()->stampSignature(['mode' => 'generic', 'pdf' => FakePdfEngine::pdf('clean'), 'signerName' => 'Rina', 'signerRole' => 'legal', 'signedAtIso' => '2026-11-18T10:00:00+00:00']);

    expect($result)->toBe($stamped);
    Http::assertSent(fn (Request $request) => $request->url() === 'http://node.test/internal/dcs/pdf/sign'
        && $request->header('X-Internal-Secret') === ['shared-secret']
        && base64_decode($request['pdfBase64']) === FakePdfEngine::pdf('clean')
        && ! isset($request['pdf']));
});

it('fails when the sign endpoint errors or returns something that is not a PDF', function (int $status, array $body) {
    Http::fake(['*' => Http::response($body, $status)]);

    expect(fn () => nodeEngine()->stampSignature(['pdf' => 'x']))->toThrow(PdfEngineException::class);
})->with([[500, ['error' => 'boom']], [200, ['pdfBase64' => base64_encode('nope')]]]);

it('sends the PDF to the watermark endpoint as base64 and returns the stamped copy', function () {
    $stamped = FakePdfEngine::pdf('watermarked');
    Http::fake(['node.test/*' => Http::response(['pdfBase64' => base64_encode($stamped)])]);

    $result = nodeEngine()->watermark(['pdf' => FakePdfEngine::pdf('clean'), 'status' => 'effective', 'viewerName' => 'Rina', 'accessedAt' => '2026-11-18 10:00:00 UTC', 'documentNumber' => 'SOP/1', 'major' => 1, 'minor' => 0]);

    expect($result)->toBe($stamped);
    Http::assertSent(fn (Request $request) => $request->url() === 'http://node.test/internal/dcs/pdf/watermark'
        && base64_decode($request['pdfBase64']) === FakePdfEngine::pdf('clean') && ! isset($request['pdf']) && $request['status'] === 'effective');
});
