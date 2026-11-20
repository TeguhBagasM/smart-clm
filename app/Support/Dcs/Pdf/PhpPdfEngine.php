<?php

namespace App\Support\Dcs\Pdf;

use App\Support\Dcs\Pdf\Php\Compose\PdfComposer;
use App\Support\Dcs\Pdf\Php\Draw\SignaturePainter;
use App\Support\Dcs\Pdf\Php\Draw\WatermarkPainter;
use App\Support\Dcs\Pdf\Php\Model\PdfFile;
use Throwable;

/**
 * The PDF engine that runs inside Laravel, with no Node process. It replaces the
 * Node engine one operation at a time: the watermark and the signature stamp are
 * ported, and so is compose.
 */
class PhpPdfEngine implements PdfEngine
{
    public function __construct(private string $timezone = 'UTC') {}

    public function compose(array $input): array
    {
        $composed = $this->guard('compose the document', fn (): array => (new PdfComposer($this->timezone))->compose($input));

        return $composed;
    }

    public function stampSignature(array $input): string
    {
        return $this->guard('stamp the signature', function () use ($input): string {
            $file = PdfFile::parse($input['pdf']);
            (new SignaturePainter($this->timezone))->paint($file, $input);

            return $file->write();
        });
    }

    public function watermark(array $input): string
    {
        return $this->guard('watermark', function () use ($input): string {
            $file = PdfFile::parse($input['pdf']);
            (new WatermarkPainter($this->timezone))->paint($file, $input);

            return $file->write();
        });
    }

    /**
     * @param  callable(): mixed  $work
     */
    private function guard(string $operation, callable $work): mixed
    {
        try {
            return $work();
        } catch (PdfEngineException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new PdfEngineException("The PHP PDF engine failed to {$operation}: ".$e->getMessage(), previous: $e);
        }
    }
}
