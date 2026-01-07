<?php
declare(strict_types=1);

namespace App;

use Imagick;
use Exception;

class Media
{
    public static function isFfmpegAvailable(): bool
    {
        $result = shell_exec('command -v ffmpeg');
        return !empty($result);
    }

    public static function checkMemoryForImage(int $width, int $height, int $bpp = 4): bool
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') return true; // No limit

        // Parse limit (e.g. 128M)
        $limitInt = (int)$limit;
        $unit = strtoupper(substr($limit, -1));
        if ($unit === 'G') $limitInt *= 1024 * 1024 * 1024;
        elseif ($unit === 'M') $limitInt *= 1024 * 1024;
        elseif ($unit === 'K') $limitInt *= 1024;

        // Approximate memory needed: width * height * bytes_per_pixel * overhead_factor (1.7)
        $needed = $width * $height * $bpp * 1.7;

        // Current usage
        $usage = memory_get_usage();

        return ($usage + $needed) < $limitInt;
    }

    public static function generateResized(string $source, string $target, int $maxWidth, int $maxHeight, int $quality = 80): bool
    {
        if (!file_exists($source)) return false;

        $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));

        // 1. Try Imagick
        if (extension_loaded('imagick')) {
            try {
                $imagick = new Imagick($source);

                // Rotation (before strip)
                $orientation = $imagick->getImageOrientation();
                if ($orientation !== Imagick::ORIENTATION_TOPLEFT) {
                    switch ($orientation) {
                        case Imagick::ORIENTATION_BOTTOMRIGHT:
                            $imagick->rotateImage("#000000", 180);
                            break;
                        case Imagick::ORIENTATION_RIGHTTOP:
                            $imagick->rotateImage("#000000", 90);
                            break;
                        case Imagick::ORIENTATION_LEFTBOTTOM:
                            $imagick->rotateImage("#000000", -90);
                            break;
                    }
                    $imagick->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
                }

                $imagick->setImageCompressionQuality($quality);
                $imagick->stripImage(); // Remove metadata

                // Resize (best fit)
                $imagick->resizeImage($maxWidth, $maxHeight, Imagick::FILTER_LANCZOS, 1, true);

                if ($ext === 'webp') {
                    $imagick->setImageFormat('webp');
                } elseif ($ext === 'jpg' || $ext === 'jpeg') {
                    $imagick->setImageFormat('jpeg');
                } elseif ($ext === 'png') {
                    $imagick->setImageFormat('png');
                }

                $imagick->writeImage($target);
                $imagick->clear();
                $imagick->destroy();
                return true;
            } catch (Exception $e) {
                // Fallback to GD if Imagick fails
                error_log("Imagick error: " . $e->getMessage());
            }
        }

        // 2. Fallback GD
        if (!extension_loaded('gd')) {
            error_log("GD extension missing. Cannot resize image.");
            return false;
        }

        // Get dimensions and type
        $info = @getimagesize($source);
        if (!$info) return false;
        [$width, $height, $type] = $info;

        // Check Memory
        if (!self::checkMemoryForImage($width, $height)) {
             error_log("Memory limit reached for image resizing: {$width}x{$height}");
             return false;
        }

        // Load Source
        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
            default => null
        };

        if (!$src) return false;

        // Handle Rotation (GD) - Only for JPEG usually
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($source);
            if ($exif && isset($exif['Orientation'])) {
                $orientation = $exif['Orientation'];
                $src = match ($orientation) {
                    3 => imagerotate($src, 180, 0),
                    6 => imagerotate($src, -90, 0),
                    8 => imagerotate($src, 90, 0),
                    default => $src
                };
                // Update dimensions after rotation
                $width = imagesx($src);
                $height = imagesy($src);
            }
        }

        // Calculate new dimensions (Aspect Ratio)
        $ratio = $width / $height;
        $targetRatio = $maxWidth / $maxHeight;

        // Logic: Fit within box
        if ($targetRatio > $ratio) {
            $newWidth = (int)round($maxHeight * $ratio);
            $newHeight = $maxHeight;
        } else {
            $newHeight = (int)round($maxWidth / $ratio);
            $newWidth = $maxWidth;
        }

        // Don't upscale
        if ($newWidth > $width || $newHeight > $height) {
            $newWidth = $width;
            $newHeight = $height;
        }

        $dst = imagecreatetruecolor($newWidth, $newHeight);

        // Handle Transparency
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        // Resample
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Save
        $res = match ($ext) {
            'webp' => imagewebp($dst, $target, $quality),
            'jpg', 'jpeg' => imagejpeg($dst, $target, $quality),
            'png' => imagepng($dst, $target, (int)round(9 * (1 - $quality/100))),
            default => false
        };

        // Cleanup
        imagedestroy($src);
        imagedestroy($dst);

        return $res;
    }

    public static function generatePlaceholderThumb(string $target): bool
    {
        if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
            return false;
        }

        $width = 480;
        $height = 270;
        $image = imagecreatetruecolor($width, $height);
        if (!$image) {
            return false;
        }

        $bg = imagecolorallocate($image, 28, 31, 36);
        $fg = imagecolorallocate($image, 200, 200, 200);
        imagefilledrectangle($image, 0, 0, $width, $height, $bg);
        imagestring($image, 5, 140, 120, 'Video Preview', $fg);
        $result = imagejpeg($image, $target, 82);
        imagedestroy($image);
        return $result;
    }

    public static function generateVideoThumb(string $source, string $target, int $maxWidth = 480): bool
    {
        if (self::isFfmpegAvailable()) {
            // Seek to 0.1s instead of 1s to better handle short videos
            $cmd = sprintf(
                'ffmpeg -y -ss 0.1 -i %s -frames:v 1 -vf "scale=\'min(%d,iw)\':-1" %s 2>&1',
                escapeshellarg($source),
                $maxWidth,
                escapeshellarg($target)
            );
            shell_exec($cmd);
            if (file_exists($target) && filesize($target) > 0) {
                return true;
            }
        }
        // Fallback to placeholder if FFmpeg fails or is missing
        return self::generatePlaceholderThumb($target);
    }
}
