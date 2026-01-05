<?php

declare(strict_types=1);

function is_ffmpeg_available(): bool
{
    $result = shell_exec('command -v ffmpeg');
    return !empty($result);
}

function generate_image_thumb(string $source, string $target): bool
{
    if (!extension_loaded('gd')) {
        return false;
    }

    $info = getimagesize($source);
    if (!$info) {
        return false;
    }
    [$width, $height, $type] = $info;
    $maxSize = 480;
    $scale = min($maxSize / $width, $maxSize / $height, 1);
    $newWidth = (int)round($width * $scale);
    $newHeight = (int)round($height * $scale);

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
        default:
            return false;
    }

    if (!$src) {
        return false;
    }

    $dst = imagecreatetruecolor($newWidth, $newHeight);
    if (!$dst) {
        imagedestroy($src);
        return false;
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    $result = imagejpeg($dst, $target, 82);
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
