<?php
require __DIR__ . '/app/Bootstrap.php'; // For autoloader and App\Media

use App\Media;

// Basic configuration
$thumbsDir = __DIR__ . '/thumbs';
$uploadsDir = __DIR__ . '/uploads';

// Ensure thumbs directory exists
if (!is_dir($thumbsDir)) {
    if (!mkdir($thumbsDir, 0755, true)) {
        header('HTTP/1.1 500 Internal Server Error');
        die('Could not create thumbs directory.');
    }
}

// Get parameters
$src = $_GET['src'] ?? '';
$w = (int)($_GET['w'] ?? 420);
$h = (int)($_GET['h'] ?? 420);
$fit = $_GET['fit'] ?? 'cover'; // Not fully implemented in App\Media yet, but we'll stick to logic

// Validate path
// Security: Prevent directory traversal
$cleanSrc = str_replace(['..', '//'], '', ltrim($src, '/'));
if (strpos($cleanSrc, 'uploads/') === 0) {
    $cleanSrc = substr($cleanSrc, 8); // Remove 'uploads/' prefix
}
$srcPath = realpath($uploadsDir . '/' . $cleanSrc);

if (!$srcPath || strpos($srcPath, realpath($uploadsDir)) !== 0 || !file_exists($srcPath)) {
    header('HTTP/1.1 404 Not Found');
    die('Image not found or invalid path.');
}

// Generate cache filename
$ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
$hash = md5($src . $w . $h . $fit . filemtime($srcPath));
$cacheFile = $thumbsDir . '/' . $hash . '.' . $ext;

// Browser Caching
$etag = '"' . $hash . '"';
header('ETag: ' . $etag);
header('Cache-Control: public, max-age=31536000'); // 1 year
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    header('HTTP/1.1 304 Not Modified');
    exit;
}

// Check cache
if (file_exists($cacheFile)) {
    $mime = mime_content_type($cacheFile);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($cacheFile));
    readfile($cacheFile);
    exit;
}

// Use App\Media to generate logic
// Note: App\Media::generateResized mainly does resizing/contain.
// The complex 'cover' logic in old thumb.php is better.
// We will reimplement it here cleanly or update App\Media.
// For now, let's keep the specialized logic here but simplified.

if (!extension_loaded('gd')) {
    header('HTTP/1.1 501 Not Implemented');
    die('GD library is missing.');
}

// Load image
$sourceImage = null;
switch ($ext) {
    case 'jpg':
    case 'jpeg':
        $sourceImage = @imagecreatefromjpeg($srcPath);
        break;
    case 'png':
        $sourceImage = @imagecreatefrompng($srcPath);
        break;
    case 'gif':
        $sourceImage = @imagecreatefromgif($srcPath);
        break;
    case 'webp':
        $sourceImage = @imagecreatefromwebp($srcPath);
        break;
}

if (!$sourceImage) {
    // If it's a video file being requested (which shouldn't happen via src=... unless logic allows), fail.
    // thumb.php is strictly for images.
    header('HTTP/1.1 415 Unsupported Media Type');
    die('Unsupported image type.');
}

$origW = imagesx($sourceImage);
$origH = imagesy($sourceImage);

// Create new image
$destImage = imagecreatetruecolor($w, $h);

// Preserve transparency
if ($ext == 'png' || $ext == 'webp' || $ext == 'gif') {
    imagealphablending($destImage, false);
    imagesavealpha($destImage, true);
    $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
    imagefilledrectangle($destImage, 0, 0, $w, $h, $transparent);
}

// Crop to fit (Cover)
$srcRatio = $origW / $origH;
$dstRatio = $w / $h;

$srcX = 0;
$srcY = 0;
$srcW = $origW;
$srcH = $origH;

if ($srcRatio > $dstRatio) {
    // Source is wider, crop left/right
    $srcW = (int)($origH * $dstRatio);
    $srcX = (int)(($origW - $srcW) / 2);
} else {
    // Source is taller, crop top/bottom
    $srcH = (int)($origW / $dstRatio);
    $srcY = (int)(($origH - $srcH) / 2);
}

imagecopyresampled($destImage, $sourceImage, 0, 0, $srcX, $srcY, $w, $h, $srcW, $srcH);

// Save to cache
switch ($ext) {
    case 'jpg':
    case 'jpeg':
        imagejpeg($destImage, $cacheFile, 85);
        break;
    case 'png':
        imagepng($destImage, $cacheFile);
        break;
    case 'gif':
        imagegif($destImage, $cacheFile);
        break;
    case 'webp':
        imagewebp($destImage, $cacheFile, 85);
        break;
}

// Cleanup
imagedestroy($sourceImage);
imagedestroy($destImage);

// Output
$mime = mime_content_type($cacheFile);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($cacheFile));
readfile($cacheFile);
