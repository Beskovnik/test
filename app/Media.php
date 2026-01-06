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

    public static function getVideoInfo(string $source): ?array
    {
        if (!self::isFfmpegAvailable()) {
            return null;
        }

        $cmd = sprintf(
            'ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 %s',
            escapeshellarg($source)
        );
        $output = shell_exec($cmd);

        if ($output) {
            $parts = explode(',', trim($output));
            if (count($parts) === 2) {
                return [
                    'width' => (int)$parts[0],
                    'height' => (int)$parts[1]
                ];
            }
        }

        return null;
    }

    public static function generateResized(string $source, string $target, int $maxWidth, int $maxHeight, int $quality = 80): bool
    {
        $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));

        // Try Imagick
        if (extension_loaded('imagick')) {
            try {
                $imagick = new Imagick($source);
                $imagick->setImageCompressionQuality($quality);
                $imagick->stripImage();
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
                // Fallback
                error_log("Imagick error: " . $e->getMessage());
            }
        }

        // Fallback GD
        if (!extension_loaded('gd')) return false;

        $info = getimagesize($source);
        if (!$info) return false;
        [$width, $height, $type] = $info;

        $scale = min($maxWidth / $width, $maxHeight / $height, 1);
        if ($scale >= 1) {
            return copy($source, $target);
        }

        $newWidth = (int)round($width * $scale);
        $newHeight = (int)round($height * $scale);

        $src = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($source),
            IMAGETYPE_PNG => imagecreatefrompng($source),
            IMAGETYPE_WEBP => imagecreatefromwebp($source),
            default => null
        };

        if (!$src) return false;

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $res = match ($ext) {
            'webp' => imagewebp($dst, $target, $quality),
            'jpg', 'jpeg' => imagejpeg($dst, $target, $quality),
            'png' => imagepng($dst, $target, (int)round(9 * (1 - $quality/100))),
            default => false
        };

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
            // Fixed the duplicated format string bug
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
