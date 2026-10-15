<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageProcessor
{
    public const MAX_SIDE = 1600;        // লম্বা দিক সর্বোচ্চ ১৬০০ পিক্সেল
    public const MAX_PIXELS = 16000000;  // সর্বোচ্চ ১৬ মেগাপিক্সেল (সার্ভারের মেমোরি বাঁচাতে)
    public const QUALITY = 82;           // WebP কোয়ালিটি

    /**
     * ছবি পড়ে, দরকার হলে ছোট করে WebP বানিয়ে public ডিস্কে রাখে।
     * ফেরত দেয়: সংরক্ষিত ফাইলের পাথ (যেমন products/xxxx.webp)
     */
    public static function storeAsWebp(string $sourcePath, string $directory, string $errorField = 'data.image_paths'): string
    {
        $info = @getimagesize($sourcePath);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages([$errorField => 'Only JPG, PNG or WebP images are allowed.']);
        }

        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages([$errorField => 'This image is too large in pixels (max 16 megapixels, about 4000 x 4000). Please resize it and try again.']);
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
        };

        if ($image === false) {
            throw ValidationException::withMessages([$errorField => 'This image could not be read. Please try another file.']);
        }

        imagepalettetotruecolor($image);

        // ফোনের ছবি ঘুরে যাওয়া ঠিক করা (JPEG-এর EXIF অনুযায়ী)
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($sourcePath);
            $orientation = $exif['Orientation'] ?? 1;
            $angle = match ($orientation) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };

            if ($angle !== 0) {
                $rotated = imagerotate($image, $angle, 0);
                if ($rotated !== false) {
                    $image = $rotated;
                }
            }
        }

        // বড় ছবি ছোট করা (ছোট ছবি বড় করা হবে না)
        $width = imagesx($image);
        $height = imagesy($image);

        if (max($width, $height) > self::MAX_SIDE) {
            $scale = self::MAX_SIDE / max($width, $height);
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale), IMG_BICUBIC);

            if ($resized !== false) {
                $image = $resized;
            }
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, self::QUALITY);
        $data = ob_get_clean();

        if ($data === false || $data === '') {
            throw ValidationException::withMessages([$errorField => 'Image conversion failed. Please try another file.']);
        }

        $path = trim($directory, '/') . '/' . Str::uuid() . '.webp';
        Storage::disk('public')->put($path, $data);

        return $path;
    }
}