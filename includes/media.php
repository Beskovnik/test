<?php

declare(strict_types=1);

function is_ffmpeg_available(): bool
{
    $result = shell_exec('command -v ffmpeg');
    return !empty($result);
}

/**
 * Generates an image thumbnail/preview using GD or Imagick, prioritizing Imagick for better quality/WebP support.
 *
 * @param string $source Path to source image
 * @param string $target Path to save the result
 * @param int $maxWidth Maximum width
 * @param int $maxHeight Maximum height
 * @param int $quality JPEG/WebP quality (0-100)
 * @return bool Success
 */
function generate_image_resized(string $source, string $target, int $maxWidth, int $maxHeight, int $quality = 80): bool
{
    $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));

    // Try Imagick first if available
    if (extension_loaded('imagick')) {
        try {
            $imagick = new Imagick($source);
            $imagick->setImageCompressionQuality($quality);

            // Strip metadata to save space
            $imagick->stripImage();

            // Resize (bestfit=true fills bounds while maintaining aspect ratio)
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
            error_log("Imagick failed: " . $e->getMessage());
        }
    }

    // Fallback to GD
    if (!extension_loaded('gd')) {
        return false;
    }

    $info = getimagesize($source);
    if (!$info) {
        return false;
    }
    [$width, $height, $type] = $info;

    $scale = min($maxWidth / $width, $maxHeight / $height, 1);
    // If image is smaller than target, keep original size
    if ($scale >= 1) {
        $newWidth = $width;
        $newHeight = $height;
    } else {
        $newWidth = (int)round($width * $scale);
        $newHeight = (int)round($height * $scale);
    }

    $src = null;
    switch ($type) {
        case IMAGETYPE_JPEG:
            if (function_exists('imagecreatefromjpeg')) {
                $src = imagecreatefromjpeg($source);
            }
            break;
        case IMAGETYPE_PNG:
            if (function_exists('imagecreatefrompng')) {
                $src = imagecreatefrompng($source);
            }
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $src = imagecreatefromwebp($source);
            }
            break;
        case IMAGETYPE_GIF:
            if (function_exists('imagecreatefromgif')) {
                $src = imagecreatefromgif($source);
            }
            break;
    }

    if (!$src) {
        return false;
    }

    $dst = imagecreatetruecolor($newWidth, $newHeight);
    if (!$dst) {
        imagedestroy($src);
        return false;
    }

    // Preserve transparency for PNG/WebP
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $result = false;
    if ($ext === 'webp' && function_exists('imagewebp')) {
        $result = imagewebp($dst, $target, $quality);
    } elseif (($ext === 'jpg' || $ext === 'jpeg') && function_exists('imagejpeg')) {
        $result = imagejpeg($dst, $target, $quality);
    } elseif ($ext === 'png' && function_exists('imagepng')) {
        // PNG quality is 0-9 (compression level), mapped from 0-100
        $pngQuality = (int)round(9 * (1 - $quality/100));
        $result = imagepng($dst, $target, $pngQuality);
    }

    imagedestroy($src);
    imagedestroy($dst);
    return $result;
}

function generate_placeholder_thumb(string $target): bool
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

function generate_video_thumb(string $source, string $target): bool
{
    if (is_ffmpeg_available()) {
        $cmd = sprintf(
            'ffmpeg -y -ss 1 -i %s -frames:v 1 -vf "scale=480:-1" %s 2>&1',
            escapeshellarg($source),
            escapeshellarg($target)
        );
        shell_exec($cmd);
        return file_exists($target);
    }

    return generate_placeholder_thumb($target);
}

function detect_media_type(string $mime): ?string
{
    if (str_starts_with($mime, 'image/')) {
        return 'image';
    }
    if (str_starts_with($mime, 'video/')) {
        return 'video';
    }
    return null;
}

function fetch_like_count(PDO $pdo, int $postId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM likes WHERE post_id = :id');
    $stmt->execute([':id' => $postId]);
    return (int)$stmt->fetchColumn();
}
