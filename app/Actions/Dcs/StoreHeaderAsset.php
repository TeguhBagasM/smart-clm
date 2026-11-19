<?php

namespace App\Actions\Dcs;

use App\Models\Dcs\HeaderAsset;
use App\Models\Tenant;
use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Keeps an uploaded letterhead logo. The upload is decoded and encoded again, so
 * a file that only pretends to be an image fails here and EXIF/GPS and any other
 * metadata never reach the disk. PNG and WebP become PNG (the PDF engine reads
 * PNG and JPEG only), JPEG stays JPEG. The name is random and the folder is the tenant's.
 */
class StoreHeaderAsset
{
    public const MAX_PIXELS = 4000;

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Tenant $tenant, UploadedFile $file): HeaderAsset
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($bytes);

        if ($info === false || $info[0] > self::MAX_PIXELS || $info[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['file' => 'Berkas bukan gambar yang valid atau ukurannya melebihi '.self::MAX_PIXELS.' px.']);
        }

        $image = @imagecreatefromstring($bytes);

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages(['file' => 'Berkas bukan gambar yang valid.']);
        }

        $isJpeg = $info[2] === IMAGETYPE_JPEG;

        if ($isJpeg) {
            $image = $this->upright($image, $bytes);
        }

        $encoded = $this->encode($image, $isJpeg);
        $mime = $isJpeg ? 'image/jpeg' : 'image/png';
        $key = 'header-assets/'.$tenant->id.'/'.Str::uuid().($isJpeg ? '.jpg' : '.png');

        Storage::disk(config('dcs.disk'))->put($key, $encoded);

        $asset = (new HeaderAsset)->forceFill([
            'tenant_id' => $tenant->id,
            'disk_key' => $key,
            'mime' => $mime,
            'width_px' => imagesx($image),
            'height_px' => imagesy($image),
            'size_bytes' => strlen($encoded),
            'sha256' => hash('sha256', $encoded),
            'created_by_id' => $actor->id,
        ]);
        $asset->save();

        return $asset;
    }

    private function encode(GdImage $image, bool $jpeg): string
    {
        ob_start();

        if ($jpeg) {
            imagejpeg($image, null, 90);
        } else {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image);
        }

        return (string) ob_get_clean();
    }

    /**
     * A photo's EXIF orientation is applied to the pixels before the metadata is dropped.
     */
    private function upright(GdImage $image, string $bytes): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
