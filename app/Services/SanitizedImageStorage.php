<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SanitizedImageStorage
{
    public const MAX_SIDE = 4000;
    public const MAX_PIXELS = 8_000_000;

    public static function validationRules(): array
    {
        return [
            'file', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:5120',
            'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value instanceof UploadedFile && $value->isValid() && ($error = self::resolutionError($value))) {
                    $fail($error);
                }
            },
        ];
    }

    public static function resolutionError(UploadedFile $file): ?string
    {
        $dimensions = @getimagesize($file->getRealPath());
        if ($dimensions === false) {
            return 'The file must be a valid JPG or PNG image.';
        }

        [$width, $height] = $dimensions;
        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE || $width * $height > self::MAX_PIXELS) {
            return 'The image must be at most 4000 pixels on each side and 8 megapixels in total.';
        }

        return null;
    }

    public function store(UploadedFile $file, string $directory, string $attribute): string
    {
        if ($error = self::resolutionError($file)) {
            throw ValidationException::withMessages([$attribute => $error]);
        }

        $source = file_get_contents($file->getRealPath());
        $type = @exif_imagetype($file->getRealPath());
        $image = $source === false || ! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)
            ? false
            : @imagecreatefromstring($source);

        if ($image === false) {
            throw ValidationException::withMessages([
                $attribute => 'The file must be a valid JPG or PNG image.',
            ]);
        }

        $isPng = $type === IMAGETYPE_PNG;
        ob_start();
        try {
            $encoded = $isPng ? imagepng($image, null, 8) : imagejpeg($image, null, 90);
            $sanitized = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($image);
        }

        if (! $encoded || $sanitized === false) {
            throw ValidationException::withMessages([
                $attribute => 'The image could not be processed. Please try another file.',
            ]);
        }

        $path = trim($directory, '/').'/'.Str::uuid().($isPng ? '.png' : '.jpg');
        if (! Storage::disk('local')->put($path, $sanitized)) {
            throw ValidationException::withMessages([
                $attribute => 'The image could not be stored. Please try again.',
            ]);
        }

        return $path;
    }
}
