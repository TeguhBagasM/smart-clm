<?php

/**
 * Regenerates the PDF golden files from the Node engine.
 *
 *   php tests/Fixtures/pdf-golden/generate.php
 *
 * The goldens are frozen: the application no longer uses the Node engine, and the PHP
 * engine is held to them by tests/Unit/Support/Dcs/Pdf. Regenerating needs the retired
 * Node engine running (DCS_PDF_ENGINE_URL / DCS_PDF_ENGINE_SECRET in the environment) and
 * pdftotext / pdfinfo / pdftocairo (poppler). Overwrites payloads/, golden/ and
 * manifest.json. The goldens are the contract the PHP engine is compared to:
 * regenerate only when the Node layout is changed on purpose.
 */

use App\Support\Dcs\Pdf\PdfEngineException;
use Illuminate\Contracts\Console\Kernel;
use Tests\Fixtures\PdfGolden\NodeEngineClient;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$root = __DIR__;
require_once __DIR__.'/NodeEngineClient.php';

$engine = new NodeEngineClient(env('DCS_PDF_ENGINE_URL'), env('DCS_PDF_ENGINE_SECRET'));
$nodeRoot = realpath($root.'/../../../../management-contract');

foreach (['assets', 'payloads/compose', 'payloads/sign', 'payloads/watermark', 'golden/compose', 'golden/sign', 'golden/watermark', 'golden/text'] as $dir) {
    is_dir("$root/$dir") || mkdir("$root/$dir", 0777, true);
}

/** A deterministic logo and flow image (GD), so binary inputs never change. */
function makePng(string $path, int $w, int $h, array $rgb): void
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    $ink = imagecolorallocate($im, ...$rgb);
    imagefilledrectangle($im, 4, 4, $w - 5, $h - 5, $ink);
    imagefilledellipse($im, intdiv($w, 2), intdiv($h, 2), intdiv($w, 2), intdiv($h, 2), imagecolorallocate($im, 255, 255, 255));
    imagepng($im, $path, 9);
}
makePng("$root/assets/logo.png", 120, 80, [36, 54, 97]);
makePng("$root/assets/flow.png", 640, 360, [67, 140, 90]);

$alpha = imagecreatetruecolor(100, 60);
imagealphablending($alpha, false);
imagesavealpha($alpha, true);
imagefill($alpha, 0, 0, imagecolorallocatealpha($alpha, 0, 0, 0, 127));
imagefilledellipse($alpha, 50, 30, 80, 44, imagecolorallocatealpha($alpha, 200, 50, 50, 40));
imagepng($alpha, "$root/assets/logo-alpha.png", 9);

// Over 4 KB on purpose: pdf-lib cannot read a JPEG that Node holds in a pooled (small) Buffer, so a tiny one would never show in the Node goldens.
$jpeg = imagecreatetruecolor(300, 180);
mt_srand(7);
for ($y = 0; $y < 180; $y += 6) {
    for ($x = 0; $x < 300; $x += 6) {
        imagefilledrectangle($jpeg, $x, $y, $x + 5, $y + 5, imagecolorallocate($jpeg, 20 + mt_rand(0, 200), 30 + mt_rand(0, 180), 60 + mt_rand(0, 150)));
    }
}
imagejpeg($jpeg, "$root/assets/logo.jpg", 90);

function resolveAssets(mixed $value, string $root): mixed
{
    if (is_array($value)) {
        return array_map(fn ($v) => resolveAssets($v, $root), $value);
    }

    return is_string($value) && str_starts_with($value, '@asset:') ? file_get_contents($root.'/assets/'.substr($value, 7)) : $value;
}

function save(string $path, string $bytes): void
{
    file_put_contents($path, $bytes);
}

function describe(string $pdfPath, string $textPath): array
{
    $info = (string) shell_exec('pdfinfo '.escapeshellarg($pdfPath).' 2>/dev/null');
    preg_match('/Pages:\s+(\d+)/', $info, $pages);
    file_put_contents($textPath, (string) shell_exec('pdftotext -layout '.escapeshellarg($pdfPath).' - 2>/dev/null'));

    return ['pages' => (int) ($pages[1] ?? 0), 'bytes' => filesize($pdfPath), 'sha256' => hash_file('sha256', $pdfPath)];
}

$manifest = ['entries' => []];
$composed = [];
$errors = [];

// 1. compose
foreach ((require "$root/cases.php") as $id => $payload) {
    save("$root/payloads/compose/$id.json", json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    try {
        $result = $engine->compose(resolveAssets($payload, $root));
    } catch (PdfEngineException $e) {
        $errors[$id] = $e->getMessage();
        $manifest['entries']["compose/$id"] = ['kind' => 'compose', 'payload' => "payloads/compose/$id.json", 'error' => $e->getMessage()];
        echo "compose  $id  ERROR  {$e->getMessage()}\n";

        continue;
    }

    save("$root/golden/compose/$id.pdf", $result['pdf']);
    save("$root/golden/compose/$id.meta.json", json_encode(['signatureSheetPageIndex' => $result['signatureSheetPageIndex'], 'sigLayout' => $result['sigLayout'], 'headerLayout' => $result['headerLayout']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $composed[$id] = ['payload' => $payload, 'result' => $result];
    $manifest['entries']["compose/$id"] = ['kind' => 'compose', 'payload' => "payloads/compose/$id.json", 'golden' => "golden/compose/$id.pdf", 'meta' => "golden/compose/$id.meta.json"]
        + describe("$root/golden/compose/$id.pdf", "$root/golden/text/compose-$id.txt");
    echo sprintf("compose  %-26s %3d pages\n", $id, $manifest['entries']["compose/$id"]['pages']);
}

// A PDF written by something other than pdf-lib (PDF 1.5+, object streams): what an upload looks like.
shell_exec('pdftocairo -pdf '.escapeshellarg("$root/golden/compose/multipage-standard.pdf").' '.escapeshellarg("$root/assets/upload-cairo.pdf"));

// 2. sign (each step stamps the output of the one before)
$signedAt = '2026-11-20T03:15:00.000Z';
$signPlans = [
    'many-signers' => [[0, 'Dewi Ayu Kusumawardhani', 'staff'], [1, 'Budi Santoso', 'supervisor'], [2, 'Siti Rahmawati', 'manajemen'], [5, 'José Muñoz-Pérez', 'staff']],
    'many-signers-formal' => [[0, 'Agus Prasetyo', 'staff'], [3, 'Maria Magdalena Sugiharto Wijayakusuma', 'manajemen']],
    'short-iso' => [[0, 'Rina Marlina', 'staff'], [1, 'Hendra Gunawan', 'manajemen']],
];

foreach ($signPlans as $caseId => $steps) {
    if (! isset($composed[$caseId])) {
        continue;
    }

    $current = $composed[$caseId]['result']['pdf'];
    $previous = "golden/compose/$caseId.pdf";

    foreach ($steps as $n => [$column, $name, $role]) {
        $id = "$caseId-step".($n + 1);
        $input = [
            'mode' => 'column', 'pdf' => $current, 'signerName' => $name, 'signerRole' => $role, 'signedAtIso' => $signedAt,
            'groups' => $composed[$caseId]['payload']['signatureGroups'], 'pageIndex' => $composed[$caseId]['result']['signatureSheetPageIndex'],
            'columnIndex' => $column, 'sigLayout' => $composed[$caseId]['result']['sigLayout'],
        ];
        $current = $engine->stampSignature($input);
        save("$root/golden/sign/$id.pdf", $current);
        save("$root/payloads/sign/$id.json", json_encode(['input' => $previous] + array_diff_key($input, ['pdf' => 1]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $manifest['entries']["sign/$id"] = ['kind' => 'sign', 'payload' => "payloads/sign/$id.json", 'golden' => "golden/sign/$id.pdf", 'source' => $previous]
            + describe("$root/golden/sign/$id.pdf", "$root/golden/text/sign-$id.txt");
        $previous = "golden/sign/$id.pdf";
        echo sprintf("sign     %-26s %3d pages\n", $id, $manifest['entries']["sign/$id"]['pages']);
    }
}

// A sheet composed before the layout was kept has no style: the plain, non-table boxes.
$legacySheet = ['mode' => 'column', 'pdf' => file_get_contents("$root/golden/compose/no-signature-no-header.pdf"), 'signerName' => 'Maria Magdalena Sugiharto Wijayakusuma dari Divisi', 'signerRole' => 'manajemen', 'signedAtIso' => $signedAt,
    'groups' => [['heading' => 'Persetujuan', 'columns' => [['label' => 'Dibuat oleh', 'role' => 'staff'], ['label' => 'Disetujui oleh', 'role' => 'manajemen', 'roleName' => 'Direktur Utama']]]], 'pageIndex' => 0, 'columnIndex' => 1, 'sigLayout' => null];
save("$root/golden/sign/legacy-sheet.pdf", $engine->stampSignature($legacySheet));
save("$root/payloads/sign/legacy-sheet.json", json_encode(['input' => 'golden/compose/no-signature-no-header.pdf'] + array_diff_key($legacySheet, ['pdf' => 1]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
$manifest['entries']['sign/legacy-sheet'] = ['kind' => 'sign', 'payload' => 'payloads/sign/legacy-sheet.json', 'golden' => 'golden/sign/legacy-sheet.pdf', 'source' => 'golden/compose/no-signature-no-header.pdf']
    + describe("$root/golden/sign/legacy-sheet.pdf", "$root/golden/text/sign-legacy-sheet.txt");

$generic = ['mode' => 'generic', 'pdf' => file_get_contents("$root/assets/upload-cairo.pdf"), 'signerName' => 'Rina Marlina', 'signerRole' => 'staff', 'signedAtIso' => $signedAt];
save("$root/golden/sign/generic-upload.pdf", $engine->stampSignature($generic));
save("$root/payloads/sign/generic-upload.json", json_encode(['input' => 'assets/upload-cairo.pdf'] + array_diff_key($generic, ['pdf' => 1]), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$manifest['entries']['sign/generic-upload'] = ['kind' => 'sign', 'payload' => 'payloads/sign/generic-upload.json', 'golden' => 'golden/sign/generic-upload.pdf', 'source' => 'assets/upload-cairo.pdf']
    + describe("$root/golden/sign/generic-upload.pdf", "$root/golden/text/sign-generic-upload.txt");

// 3. watermark
$wm = fn (string $source, string $status, string $viewer, bool $expired, ?array $headerLayout, ?string $issued = null): array => [
    'status' => $status, 'viewerName' => $viewer, 'accessedAt' => '2026-11-20 02:00:00 UTC', 'documentNumber' => 'SOP-HED-27-00-26',
    'major' => 2, 'minor' => 3, 'expired' => $expired, 'headerLayout' => $headerLayout, 'issuedDateIso' => $issued,
];
$layout = fn (string $case): ?array => $composed[$case]['result']['headerLayout'] ?? null;
$issued = '2026-11-20T00:00:00.000Z';

$watermarkCases = [];
foreach ([['draft', false], ['under_review', false], ['approved', false], ['effective', false], ['effective', true], ['superseded', false]] as [$status, $expired]) {
    $watermarkCases["short-iso-$status".($expired ? '-expired' : '')] = ['golden/compose/short-iso.pdf', $wm('', $status, 'Dewi Ayu Kusumawardhani', $expired, $layout('short-iso'), $status === 'draft' ? null : $issued)];
}
$watermarkCases['multipage-formal-effective'] = ['golden/compose/multipage-formal.pdf', $wm('', 'effective', 'José Muñoz-Pérez', false, $layout('multipage-formal'), $issued)];
$watermarkCases['multipage-standard-superseded-longname'] = ['golden/compose/multipage-standard.pdf', $wm('', 'superseded', 'Maria Magdalena Sugiharto Wijayakusuma dari Divisi Pengadaan dan Logistik Wilayah Timur', false, $layout('multipage-standard'), $issued)];
$watermarkCases['signed-many-effective'] = ['golden/sign/many-signers-step4.pdf', $wm('', 'effective', 'Budi Santoso', false, $layout('many-signers'), $issued)];
$watermarkCases['upload-cairo-effective'] = ['assets/upload-cairo.pdf', $wm('', 'effective', 'Budi Santoso', false, null, $issued)];
$watermarkCases['upload-cairo-superseded'] = ['assets/upload-cairo.pdf', $wm('', 'superseded', 'Budi Santoso', false, null)];

foreach ($watermarkCases as $id => [$source, $input]) {
    if (! is_file("$root/$source")) {
        continue;
    }

    save("$root/golden/watermark/$id.pdf", $engine->watermark(['pdf' => file_get_contents("$root/$source")] + $input));
    save("$root/payloads/watermark/$id.json", json_encode(['input' => $source] + $input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $manifest['entries']["watermark/$id"] = ['kind' => 'watermark', 'payload' => "payloads/watermark/$id.json", 'golden' => "golden/watermark/$id.pdf", 'source' => $source]
        + describe("$root/golden/watermark/$id.pdf", "$root/golden/text/watermark-$id.txt");
    echo sprintf("watermark %-40s %3d pages\n", $id, $manifest['entries']["watermark/$id"]['pages']);
}

$hash = fn (string $f): string => is_file($f) ? hash_file('sha256', $f) : 'missing';
$manifest = [
    'provenance' => [
        'node' => trim((string) shell_exec('node --version')),
        'nodeTimeZone' => trim((string) shell_exec('node -e "console.log(Intl.DateTimeFormat().resolvedOptions().timeZone)"')),
        'pdfLib' => json_decode(file_get_contents("$nodeRoot/node_modules/pdf-lib/package.json"), true)['version'] ?? null,
        'managementContractCommit' => trim((string) shell_exec('git -C '.escapeshellarg($nodeRoot).' rev-parse --short HEAD')),
        'managementContractDcsDirty' => trim((string) shell_exec('git -C '.escapeshellarg($nodeRoot).' status --short dcs')) !== '',
        'engineSourceSha256' => array_combine(
            ['pdf-compose.ts', 'pdf-draw.ts', 'pdf-watermark.ts', 'pdf-signature.ts', 'pdf-header.ts', 'pdf-header-geometry.ts', 'pdf-flow-route.ts', 'pdf-flow-route-util.ts', 'internal-pdf.ts'],
            array_map(fn (string $f): string => $hash("$nodeRoot/dcs/$f"), ['pdf-compose.ts', 'pdf-draw.ts', 'pdf-watermark.ts', 'pdf-signature.ts', 'pdf-header.ts', 'pdf-header-geometry.ts', 'pdf-flow-route.ts', 'pdf-flow-route-util.ts', 'internal-pdf.ts']),
        ),
        'note' => 'pdf-lib stamps CreationDate/ModDate with the clock, so golden bytes are not reproducible; compare rendered pages, extracted text and page counts.',
    ],
] + $manifest;
save("$root/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo 'entries: '.count($manifest['entries'])."\n";
