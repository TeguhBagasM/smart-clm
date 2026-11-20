<?php

namespace App\Support\Dcs\Pdf\Php\Draw;

use App\Support\Dcs\Pdf\PdfEngineException;
use App\Support\Dcs\Pdf\Php\Model\Dict;
use App\Support\Dcs\Pdf\Php\Model\Name;
use App\Support\Dcs\Pdf\Php\Model\PdfFile;
use App\Support\Dcs\Pdf\Php\Model\PdfStream;

/**
 * Puts a PNG or JPEG into the file as an image XObject, like pdf-lib's embedPng and
 * embedJpg: a JPEG goes in as it is (DCT), a PNG is decoded to RGB with its alpha
 * channel as a soft mask.
 */
final class ImageEmbedder
{
    public function embed(PdfFile $file, string $bytes, string $mimeType): EmbeddedImage
    {
        return str_contains($mimeType, 'png') ? $this->png($file, $bytes) : $this->jpeg($file, $bytes);
    }

    private function png(PdfFile $file, string $bytes): EmbeddedImage
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw new PdfEngineException('The image is not a PNG that can be read.');
        }

        [$w, $h] = [imagesx($image), imagesy($image)];
        [$rgb, $alpha, $translucent] = ['', '', false];

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $pixel = imagecolorat($image, $x, $y);
                $a = ($pixel >> 24) & 127;
                $translucent = $translucent || $a > 0;
                $rgb .= chr($pixel >> 16 & 255).chr($pixel >> 8 & 255).chr($pixel & 255);
                $alpha .= chr(255 - (int) round($a * 255 / 127));
            }
        }

        $dict = ['Type' => new Name('XObject'), 'Subtype' => new Name('Image'), 'Width' => $w, 'Height' => $h, 'BitsPerComponent' => 8, 'ColorSpace' => new Name('DeviceRGB'), 'Filter' => new Name('FlateDecode')];

        if ($translucent) {
            $dict['SMask'] = $file->add(new PdfStream(new Dict(['Type' => new Name('XObject'), 'Subtype' => new Name('Image'), 'Width' => $w, 'Height' => $h, 'BitsPerComponent' => 8, 'ColorSpace' => new Name('DeviceGray'), 'Filter' => new Name('FlateDecode')]), gzcompress($alpha)));
        }

        return new EmbeddedImage($file->uniqueName('DcsIm'), $file->add(new PdfStream(new Dict($dict), gzcompress($rgb))), $w, $h);
    }

    private function jpeg(PdfFile $file, string $bytes): EmbeddedImage
    {
        [$w, $h, $components] = $this->jpegSize($bytes);
        $space = match ($components) {
            1 => 'DeviceGray',
            3 => 'DeviceRGB',
            4 => 'DeviceCMYK',
            default => throw new PdfEngineException("Unexpected number of components in the JPEG: {$components}"),
        };
        $dict = ['Type' => new Name('XObject'), 'Subtype' => new Name('Image'), 'Width' => $w, 'Height' => $h, 'BitsPerComponent' => 8, 'ColorSpace' => new Name($space), 'Filter' => new Name('DCTDecode')]
            + ($components === 4 ? ['Decode' => [1, 0, 1, 0, 1, 0, 1, 0]] : []);

        return new EmbeddedImage($file->uniqueName('DcsIm'), $file->add(new PdfStream(new Dict($dict), $bytes)), $w, $h);
    }

    /** @return array{0: int, 1: int, 2: int} width, height, colour components */
    private function jpegSize(string $bytes): array
    {
        $offset = 2;

        while ($offset + 9 < strlen($bytes)) {
            if ($bytes[$offset] !== "\xFF") {
                $offset++;

                continue;
            }

            $marker = ord($bytes[$offset + 1]);

            if (in_array($marker, [0xC0, 0xC1, 0xC2], true)) {
                return [unpack('n', substr($bytes, $offset + 7, 2))[1], unpack('n', substr($bytes, $offset + 5, 2))[1], ord($bytes[$offset + 9])];
            }

            $offset += 2 + unpack('n', substr($bytes, $offset + 2, 2))[1];
        }

        throw new PdfEngineException('The image is not a JPEG that can be read.');
    }
}
